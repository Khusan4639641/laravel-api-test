<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\PackageAutoUpgradeFromPaidOrdersService;
use App\Services\PackagePurchaseAvailabilityService;
use App\Services\PackageService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TipTopPayService
{
    public function __construct(
        private readonly PackageService $packageService,
        private readonly WalletService $walletService,
        private readonly PackagePurchaseAvailabilityService $packagePurchaseAvailability,
        private readonly PackageAutoUpgradeFromPaidOrdersService $packageAutoUpgradeFromPaidOrders,
    ) {
    }

    /**
     * Backward-compatible order-only intent payload.
     *
     * @return array<string, mixed>
     */
    public function createIntent(Order $order, User $actor): array
    {
        return $this->createOrderPaymentIntent($actor, $order)['intent'];
    }

    /**
     * @return array{payment_id: int, external_id: string, intent: array<string, mixed>}
     */
    public function createOrderPaymentIntent(User $user, Order $order): array
    {
        $this->assertActorCanPay($order, $user);
        $this->assertPaymentConfigIsUsable();

        $order->loadMissing(['user.profile', 'items.product', 'items.package']);
        $this->assertOrderPayable($order);

        return DB::transaction(function () use ($order): array {
            $currency = $this->currency();

            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->with(['user.profile', 'items.product', 'items.package'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertOrderPayable($lockedOrder);

            $externalId = $this->makeExternalId('order-'.$lockedOrder->id);
            $description = sprintf('Оплата заказа #%s на Safi Life', $lockedOrder->order_number ?: $lockedOrder->id);

            $payment = Payment::query()->create([
                'user_id' => $lockedOrder->user_id,
                'payable_type' => Order::class,
                'payable_id' => $lockedOrder->id,
                'type' => Payment::TYPE_ORDER,
                'provider' => Payment::PROVIDER_TIPTOPPAY,
                'external_id' => $externalId,
                'amount' => (string) $lockedOrder->total_amount,
                'currency' => $currency,
                'status' => Payment::STATUS_PENDING,
                'description' => $description,
                'payload' => [
                    'order_id' => $lockedOrder->id,
                    'order_number' => $lockedOrder->order_number,
                    'type' => Payment::TYPE_ORDER,
                ],
            ]);

            $intent = $this->orderIntent($payment, $lockedOrder);
            $payment->forceFill(['payload' => array_merge($payment->payload ?? [], ['intent' => $intent])])->save();

            $paymentMeta = is_array($lockedOrder->payment_meta) ? $lockedOrder->payment_meta : [];
            $paymentMeta['last_intent'] = [
                'payment_id' => $payment->id,
                'external_id' => $externalId,
                'created_at' => now()->toISOString(),
                'amount' => (string) $lockedOrder->total_amount,
                'currency' => $currency,
                'payment_schema' => $intent['paymentSchema'],
            ];

            $lockedOrder->forceFill([
                'payment_provider' => Payment::PROVIDER_TIPTOPPAY,
                'payment_status' => 'pending',
                'payment_external_id' => $externalId,
                'payment_meta' => $paymentMeta,
            ])->save();

            return [
                'payment_id' => $payment->id,
                'external_id' => $payment->external_id,
                'intent' => $intent,
            ];
        });
    }

    /**
     * @return array{payment_id: int, external_id: string, intent: array<string, mixed>}
     */
    public function createPackagePaymentIntent(User $user, Package $package, ?string $upgradeFrom = null): array
    {
        $this->assertPaymentConfigIsUsable();

        return DB::transaction(function () use ($user, $package, $upgradeFrom): array {
            $currency = $this->currency();

            /** @var User $lockedUser */
            $lockedUser = User::query()
                ->with(['profile', 'currentPackage'])
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Package $targetPackage */
            $targetPackage = Package::query()->whereKey($package->id)->lockForUpdate()->firstOrFail();
            $transition = $this->packagePurchaseAvailability->transitionForPayment($lockedUser, $targetPackage, $upgradeFrom);
            $externalId = $this->makeExternalId('package-'.$lockedUser->id.'-'.strtolower($targetPackage->code));
            $description = $transition['description'];

            $payment = Payment::query()->create([
                'user_id' => $lockedUser->id,
                'payable_type' => Package::class,
                'payable_id' => $targetPackage->id,
                'type' => Payment::TYPE_PACKAGE,
                'provider' => Payment::PROVIDER_TIPTOPPAY,
                'external_id' => $externalId,
                'amount' => $transition['amount'],
                'currency' => $currency,
                'status' => Payment::STATUS_PENDING,
                'description' => $description,
                'payload' => [
                    'type' => Payment::TYPE_PACKAGE,
                    'package_id' => $targetPackage->id,
                    'package_code' => $targetPackage->code,
                    'transition' => $transition['transition'],
                    'upgrade_from' => $transition['upgrade_from'],
                    'amount' => $transition['amount'],
                    'final_activity_pv' => $transition['final_activity_pv'],
                    'turnover_delta_pv' => $transition['turnover_delta_pv'],
                ],
            ]);

            $intent = $this->packageIntent($payment, $lockedUser, $targetPackage, $transition);
            $payment->forceFill(['payload' => array_merge($payment->payload ?? [], ['intent' => $intent])])->save();

            return [
                'payment_id' => $payment->id,
                'external_id' => $payment->external_id,
                'intent' => $intent,
            ];
        });
    }

    /**
     * @return array<string, bool|string>
     */
    public function publicStatus(): array
    {
        return [
            'enabled' => (bool) config('tiptoppay.enabled'),
            'test_mode' => (bool) config('tiptoppay.test_mode', true),
            'currency' => $this->currency(),
            'public_terminal_id_set' => trim((string) config('tiptoppay.public_terminal_id')) !== '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function handleCheck(Request $request): array
    {
        if (! $this->validWebhookSignature($request)) {
            return ['code' => 13];
        }

        return $this->handleCheckWebhook($this->payload($request));
    }

    /**
     * @return array<string, mixed>
     */
    public function handlePay(Request $request): array
    {
        if (! $this->validWebhookSignature($request)) {
            return ['code' => 13];
        }

        return $this->handlePayWebhook($this->payload($request), 'pay');
    }

    /**
     * @return array<string, mixed>
     */
    public function handleConfirm(Request $request): array
    {
        if (! $this->validWebhookSignature($request)) {
            return ['code' => 13];
        }

        return $this->handlePayWebhook($this->payload($request), 'confirm');
    }

    /**
     * @return array<string, mixed>
     */
    public function handleFail(Request $request): array
    {
        if (! $this->validWebhookSignature($request)) {
            return ['code' => 13];
        }

        return $this->handleFailWebhook($this->payload($request));
    }

    /**
     * @return array<string, mixed>
     */
    public function handleRefund(Request $request): array
    {
        if (! $this->validWebhookSignature($request)) {
            return ['code' => 13];
        }

        return $this->handleTerminalPaymentStatus($this->payload($request), Payment::STATUS_REFUNDED, 'refunded', 'cancelled', 'refund');
    }

    /**
     * @return array<string, mixed>
     */
    public function handleCancel(Request $request): array
    {
        if (! $this->validWebhookSignature($request)) {
            return ['code' => 13];
        }

        return $this->handleTerminalPaymentStatus($this->payload($request), Payment::STATUS_CANCELLED, 'cancelled', 'cancelled', 'cancel');
    }

    /**
     * @return array<string, mixed>
     */
    public function handleCheckWebhook(array $payload): array
    {
        $payment = $this->findPaymentForWebhook($payload);

        if (! $payment) {
            return ['code' => 10];
        }

        if (! $this->amountMatches($payment, $payload) || ! $this->currencyMatches($payload)) {
            $this->appendPaymentProviderResponse($payment, 'check_rejected', $payload);

            return ['code' => 12];
        }

        if (in_array($payment->status, [Payment::STATUS_PAID, Payment::STATUS_REFUNDED, Payment::STATUS_CANCELLED], true)) {
            $this->appendPaymentProviderResponse($payment, 'check_rejected_status', $payload);

            return ['code' => 13];
        }

        $this->appendPaymentProviderResponse($payment, 'check', $payload);
        $this->appendPayableWebhookMeta($payment, 'check', $payload);

        return ['code' => 0];
    }

    /**
     * @return array<string, mixed>
     */
    public function handlePayWebhook(array $payload, string $event = 'pay'): array
    {
        $payment = $this->findPaymentForWebhook($payload);

        if (! $payment) {
            return ['code' => 10];
        }

        if (! $this->amountMatches($payment, $payload) || ! $this->currencyMatches($payload)) {
            $this->appendPaymentProviderResponse($payment, $event.'_rejected', $payload);

            return ['code' => 12];
        }

        DB::transaction(function () use ($payment, $payload, $event): void {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->status === Payment::STATUS_PAID) {
                $duplicateEvent = $event.'_duplicate';
                $this->appendPaymentProviderResponse($lockedPayment, $duplicateEvent, $payload);
                $this->appendPayableWebhookMeta($lockedPayment, $duplicateEvent, $payload);

                return;
            }

            $lockedPayment->forceFill([
                'status' => Payment::STATUS_PAID,
                'provider_response' => $this->providerResponseWithEvent($lockedPayment, $event, $payload),
                'paid_at' => $lockedPayment->paid_at ?: now(),
                'failed_at' => null,
            ])->save();

            if ($lockedPayment->type === Payment::TYPE_ORDER) {
                $this->markOrderPaid($lockedPayment, $payload);
            }

            if ($lockedPayment->type === Payment::TYPE_PACKAGE) {
                $this->activatePaidPackage($lockedPayment);
            }
        });

        return ['code' => 0];
    }

    /**
     * @return array<string, mixed>
     */
    public function handleFailWebhook(array $payload): array
    {
        $payment = $this->findPaymentForWebhook($payload);

        if (! $payment) {
            return ['code' => 10];
        }

        DB::transaction(function () use ($payment, $payload): void {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->status !== Payment::STATUS_PAID) {
                $lockedPayment->forceFill([
                    'status' => Payment::STATUS_FAILED,
                    'provider_response' => $this->providerResponseWithEvent($lockedPayment, 'fail', $payload),
                    'failed_at' => now(),
                ])->save();
            } else {
                $this->appendPaymentProviderResponse($lockedPayment, 'fail_ignored_paid', $payload);
            }

            $this->markPayableFailed($lockedPayment, $payload);
        });

        return ['code' => 0];
    }

    public function findPaymentByExternalId(string $externalId): Payment
    {
        return Payment::query()
            ->where('external_id', $externalId)
            ->firstOrFail();
    }

    private function assertActorCanPay(Order $order, User $actor): void
    {
        if ((int) $order->user_id === (int) $actor->id) {
            return;
        }

        if (in_array($actor->role, [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN], true)) {
            return;
        }

        abort(404);
    }

    private function assertPaymentConfigIsUsable(): void
    {
        if (! (bool) config('tiptoppay.enabled')) {
            throw ValidationException::withMessages([
                'payment' => 'Онлайн-оплата временно недоступна',
            ]);
        }

        if (! is_string(config('tiptoppay.public_terminal_id')) || trim((string) config('tiptoppay.public_terminal_id')) === '') {
            throw ValidationException::withMessages([
                'publicTerminalId' => 'TipTop Pay terminal is not configured',
            ]);
        }

        if ($this->currency() !== 'KZT') {
            throw ValidationException::withMessages([
                'currency' => 'Онлайн-оплата доступна только в KZT',
            ]);
        }
    }

    private function assertOrderPayable(Order $order): void
    {
        if ($order->items->isEmpty()) {
            throw ValidationException::withMessages([
                'order' => 'В заказе нет товаров для оплаты',
            ]);
        }

        if (bccomp((string) $order->total_amount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Сумма заказа должна быть больше нуля',
            ]);
        }

        if (in_array($order->status, ['cancelled', 'voided', 'completed'], true)) {
            throw ValidationException::withMessages([
                'order' => 'Этот заказ нельзя оплатить онлайн',
            ]);
        }

        if (in_array($order->payment_status, ['paid', 'refunded', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'payment_status' => 'Этот заказ уже закрыт по оплате',
            ]);
        }

        foreach (['recipient_name', 'phone', 'city', 'delivery_address'] as $field) {
            if (trim((string) $this->orderDeliveryValue($order, $field)) === '') {
                throw ValidationException::withMessages([
                    $field => 'Заполните данные доставки перед оплатой.',
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function orderIntent(Payment $payment, Order $order): array
    {
        $intent = [
            'publicTerminalId' => (string) config('tiptoppay.public_terminal_id'),
            'description' => (string) $payment->description,
            'paymentSchema' => $this->paymentSchema(),
            'currency' => $this->currency(),
            'amount' => $this->numericAmount($payment->amount),
            'externalId' => $payment->external_id,
            'accountId' => 'user-'.$order->user_id,
            'receiptEmail' => $order->user?->email,
            'emailBehavior' => 'Optional',
            'language' => 'ru-RU',
            'successRedirectUrl' => $this->urlWithPayment((string) config('tiptoppay.success_url'), $payment, ['order' => $order->id]),
            'failRedirectUrl' => $this->urlWithPayment((string) config('tiptoppay.fail_url'), $payment, ['order' => $order->id]),
            'userInfo' => $this->orderUserInfo($order),
            'items' => $this->orderItems($order),
            'metadata' => [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'user_id' => $order->user_id,
                'type' => Payment::TYPE_ORDER,
            ],
        ];

        return $this->compactIntent($intent);
    }

    /**
     * @param  array<string, string|null>  $transition
     * @return array<string, mixed>
     */
    private function packageIntent(Payment $payment, User $user, Package $package, array $transition): array
    {
        $intent = [
            'publicTerminalId' => (string) config('tiptoppay.public_terminal_id'),
            'description' => (string) $payment->description,
            'paymentSchema' => $this->paymentSchema(),
            'currency' => $this->currency(),
            'amount' => $this->numericAmount($payment->amount),
            'externalId' => $payment->external_id,
            'accountId' => 'user-'.$user->id,
            'receiptEmail' => $user->email,
            'emailBehavior' => 'Optional',
            'language' => 'ru-RU',
            'successRedirectUrl' => $this->urlWithPayment((string) config('tiptoppay.success_url'), $payment, ['package' => $package->code]),
            'failRedirectUrl' => $this->urlWithPayment((string) config('tiptoppay.fail_url'), $payment, ['package' => $package->code]),
            'userInfo' => $this->packageUserInfo($user),
            'items' => [[
                'id' => 'package-'.strtolower((string) $package->code),
                'count' => 1,
                'name' => 'Пакет '.$package->code,
                'price' => $this->numericAmount($payment->amount),
            ]],
            'metadata' => array_filter([
                'payment_id' => $payment->id,
                'user_id' => $user->id,
                'type' => Payment::TYPE_PACKAGE,
                'package_id' => $package->id,
                'package_code' => $package->code,
                'transition' => $transition['transition'],
                'upgrade_from' => $transition['upgrade_from'],
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
        ];

        return $this->compactIntent($intent);
    }

    private function paymentSchema(): string
    {
        $schema = (string) config('tiptoppay.payment_schema', 'Single');

        return in_array($schema, ['Single', 'Dual'], true) ? $schema : 'Single';
    }

    private function currency(): string
    {
        return strtoupper((string) config('tiptoppay.currency', 'KZT'));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function urlWithPayment(string $baseUrl, Payment $payment, array $extra = []): string
    {
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl.$separator.http_build_query(array_filter([
            ...$extra,
            'payment_id' => $payment->id,
            'external_id' => $payment->external_id,
        ], fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function orderUserInfo(Order $order): array
    {
        $user = $order->user;
        $profile = $user?->profile;

        return array_filter([
            'accountId' => 'user-'.$order->user_id,
            'fullName' => $this->orderDeliveryValue($order, 'recipient_name') ?: $user?->name,
            'phone' => $this->orderDeliveryValue($order, 'phone') ?: $user?->phone ?: $profile?->phone,
            'email' => $user?->email,
            'city' => $this->orderDeliveryValue($order, 'city') ?: $profile?->city,
            'address' => $this->orderDeliveryValue($order, 'delivery_address'),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function packageUserInfo(User $user): array
    {
        $user->loadMissing('profile');

        return array_filter([
            'accountId' => 'user-'.$user->id,
            'fullName' => $user->name,
            'phone' => $user->phone ?: $user->profile?->phone,
            'email' => $user->email,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function orderItems(Order $order): array
    {
        return $order->items->map(function ($item): array {
            $snapshot = is_array($item->item_snapshot) ? $item->item_snapshot : [];
            $productId = $item->product_id ?: $item->id;

            return [
                'id' => 'product-'.$productId,
                'name' => $item->product_name ?: ($snapshot['name'] ?? 'Safi Life product'),
                'count' => (int) $item->quantity,
                'price' => $this->numericAmount($item->unit_price),
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>  $intent
     * @return array<string, mixed>
     */
    private function compactIntent(array $intent): array
    {
        return array_filter($intent, fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function numericAmount(mixed $value): float|int
    {
        $amount = round((float) $value, 2);

        return floor($amount) === $amount ? (int) $amount : $amount;
    }

    private function orderDeliveryValue(Order $order, string $field): mixed
    {
        $shippingAddress = is_array($order->shipping_address) ? $order->shipping_address : [];

        return $order->{$field}
            ?: $shippingAddress[$field]
            ?? ($field === 'delivery_address' ? ($shippingAddress['address'] ?? null) : null);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $payload = $request->all();

        foreach (['Data', 'data'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key])) {
                $decoded = json_decode($payload[$key], true);

                if (is_array($decoded)) {
                    $payload[$key] = $decoded;
                }
            }
        }

        return $payload;
    }

    private function validWebhookSignature(Request $request): bool
    {
        $secret = (string) config('tiptoppay.webhook_secret', '');

        if (trim($secret) === '') {
            // TODO: Enable strict TipTop Pay webhook auth after cabinet secret is configured.
            return true;
        }

        $received = $request->header('X-Content-HMAC') ?: $request->header('Content-HMAC');

        if (! is_string($received) || trim($received) === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        return hash_equals($expected, $received);
    }

    private function makeExternalId(string $prefix): string
    {
        do {
            $externalId = $prefix.'-'.(string) Str::uuid();
        } while (Payment::query()->where('external_id', $externalId)->exists());

        return $externalId;
    }

    private function findPaymentForWebhook(array $payload): ?Payment
    {
        $externalId = $this->externalId($payload);

        if ($externalId === '') {
            return null;
        }

        $payment = Payment::query()->where('external_id', $externalId)->first();

        if ($payment) {
            return $payment;
        }

        $order = $this->findOrderFallback($payload, $externalId);

        if (! $order) {
            return null;
        }

        return Payment::query()->firstOrCreate(
            ['external_id' => $externalId],
            [
                'user_id' => $order->user_id,
                'payable_type' => Order::class,
                'payable_id' => $order->id,
                'type' => Payment::TYPE_ORDER,
                'provider' => Payment::PROVIDER_TIPTOPPAY,
                'amount' => (string) $order->total_amount,
                'currency' => $this->currency(),
                'status' => $order->payment_status === 'paid' ? Payment::STATUS_PAID : Payment::STATUS_PENDING,
                'description' => sprintf('Оплата заказа #%s на Safi Life', $order->order_number ?: $order->id),
                'payload' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'type' => Payment::TYPE_ORDER,
                    'legacy_order_payment' => true,
                ],
            ],
        );
    }

    private function findOrderFallback(array $payload, string $externalId): ?Order
    {
        $order = Order::query()->where('payment_external_id', $externalId)->first();

        if ($order) {
            return $order;
        }

        if (preg_match('/^order-(\d+)-/i', $externalId, $matches)) {
            return Order::query()->find((int) $matches[1]);
        }

        $orderId = Arr::get($payload, 'Data.order_id')
            ?? Arr::get($payload, 'data.order_id')
            ?? Arr::get($payload, 'metadata.order_id');

        return is_numeric($orderId) ? Order::query()->find((int) $orderId) : null;
    }

    private function externalId(array $payload): string
    {
        foreach (['InvoiceId', 'invoiceId', 'ExternalId', 'externalId', 'external_id'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) || is_numeric($value)) {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function transactionId(array $payload): ?string
    {
        foreach (['TransactionId', 'transactionId', 'PaymentTransactionId'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) || is_numeric($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function amountMatches(Payment $payment, array $payload): bool
    {
        $amount = $payload['Amount'] ?? $payload['amount'] ?? null;

        if (! is_numeric($amount)) {
            return true;
        }

        return bccomp((string) $payment->amount, (string) $amount, 2) === 0;
    }

    private function currencyMatches(array $payload): bool
    {
        $currency = strtoupper((string) ($payload['Currency'] ?? $payload['currency'] ?? 'KZT'));

        return $currency === 'KZT';
    }

    private function markOrderPaid(Payment $payment, array $payload): void
    {
        /** @var Order|null $order */
        $order = $payment->payable_type === Order::class
            ? Order::query()->whereKey($payment->payable_id)->lockForUpdate()->first()
            : null;

        if (! $order) {
            return;
        }

        if ($order->payment_status !== 'paid') {
            $order->forceFill([
                'status' => $order->status === 'pending' ? 'confirmed' : $order->status,
                'payment_provider' => Payment::PROVIDER_TIPTOPPAY,
                'payment_status' => 'paid',
                'payment_external_id' => $payment->external_id,
                'payment_transaction_id' => $this->transactionId($payload) ?: $order->payment_transaction_id,
                'paid_at' => $order->paid_at ?: ($payment->paid_at ?: now()),
            ])->save();

            $this->recordOrderPaymentTransaction($payment, $order);
            $this->packageAutoUpgradeFromPaidOrders->handlePaidOrder($order->refresh());
        }

        $this->appendOrderWebhookMeta($order, 'pay', $payload, $payment);
    }

    private function markPayableFailed(Payment $payment, array $payload): void
    {
        if ($payment->type !== Payment::TYPE_ORDER || $payment->payable_type !== Order::class) {
            return;
        }

        /** @var Order|null $order */
        $order = Order::query()->whereKey($payment->payable_id)->lockForUpdate()->first();

        if (! $order) {
            return;
        }

        if ($order->payment_status !== 'paid') {
            $order->forceFill([
                'payment_provider' => Payment::PROVIDER_TIPTOPPAY,
                'payment_status' => 'failed',
                'payment_external_id' => $payment->external_id,
                'payment_transaction_id' => $this->transactionId($payload) ?: $order->payment_transaction_id,
            ])->save();
        }

        $this->appendOrderWebhookMeta($order, 'fail', $payload, $payment);
    }

    private function activatePaidPackage(Payment $payment): void
    {
        /** @var Package|null $targetPackage */
        $targetPackage = $payment->payable_type === Package::class
            ? Package::query()->whereKey($payment->payable_id)->first()
            : null;

        if (! $targetPackage) {
            return;
        }

        /** @var User $user */
        $user = User::query()->with('currentPackage')->findOrFail($payment->user_id);

        if ((int) $user->current_package_id === (int) $targetPackage->id) {
            return;
        }

        if (! $user->currentPackage) {
            $this->packageService->upgradePackage($user, $targetPackage);
        } else {
            $this->packageService->upgradeExistingPackage($user, $targetPackage);
        }

        $this->attachPaymentToLatestPackageTransaction($payment, $targetPackage);
    }

    private function attachPaymentToLatestPackageTransaction(Payment $payment, Package $package): void
    {
        $transaction = WalletTransaction::query()
            ->where('user_id', $payment->user_id)
            ->whereIn('type', ['package_activation', 'package_upgrade'])
            ->where('source_type', Package::class)
            ->where('source_id', $package->id)
            ->latest()
            ->first();

        if (! $transaction) {
            return;
        }

        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
        $metadata['payment_id'] = $payment->id;
        $metadata['payment_external_id'] = $payment->external_id;
        $metadata['payment_provider'] = Payment::PROVIDER_TIPTOPPAY;

        $transaction->forceFill(['metadata' => $metadata])->save();
    }

    private function recordOrderPaymentTransaction(Payment $payment, Order $order): void
    {
        $this->walletService->createUserWallets($order->user);

        $wallet = $order->user->wallets()
            ->where('type', 'main')
            ->lockForUpdate()
            ->firstOrFail();

        $this->walletService->recordNonBalanceOperation(
            $wallet,
            (string) $payment->amount,
            'order_payment',
            $payment,
            [
                'payment_id' => $payment->id,
                'payment_external_id' => $payment->external_id,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'provider' => Payment::PROVIDER_TIPTOPPAY,
                'affects_balance' => false,
            ],
            sprintf('Оплата заказа #%s через TipTop Pay', $order->order_number ?: $order->id),
            'debit',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function handleTerminalPaymentStatus(array $payload, string $paymentStatus, string $orderPaymentStatus, string $orderStatus, string $event): array
    {
        $payment = $this->findPaymentForWebhook($payload);

        if (! $payment) {
            return ['code' => 10];
        }

        DB::transaction(function () use ($payment, $payload, $paymentStatus, $orderPaymentStatus, $orderStatus, $event): void {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $lockedPayment->forceFill([
                'status' => $paymentStatus,
                'provider_response' => $this->providerResponseWithEvent($lockedPayment, $event, $payload),
                'failed_at' => $paymentStatus === Payment::STATUS_CANCELLED ? now() : $lockedPayment->failed_at,
            ])->save();

            if ($lockedPayment->type === Payment::TYPE_ORDER && $lockedPayment->payable_type === Order::class) {
                /** @var Order|null $order */
                $order = Order::query()->whereKey($lockedPayment->payable_id)->lockForUpdate()->first();

                if ($order) {
                    $order->forceFill([
                        'status' => $orderStatus,
                        'payment_provider' => Payment::PROVIDER_TIPTOPPAY,
                        'payment_status' => $orderPaymentStatus,
                        'payment_external_id' => $lockedPayment->external_id,
                        'payment_transaction_id' => $this->transactionId($payload) ?: $order->payment_transaction_id,
                    ])->save();

                    $this->appendOrderWebhookMeta($order, $event, $payload, $lockedPayment);
                }
            }
        });

        return ['code' => 0];
    }

    private function appendPaymentProviderResponse(Payment $payment, string $event, array $payload): void
    {
        $payment->forceFill([
            'provider_response' => $this->providerResponseWithEvent($payment, $event, $payload),
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function providerResponseWithEvent(Payment $payment, string $event, array $payload): array
    {
        $response = is_array($payment->provider_response) ? $payment->provider_response : [];
        $events = is_array($response['webhooks'] ?? null) ? $response['webhooks'] : [];
        $events[] = $this->webhookEvent($event, $payload);
        $response['webhooks'] = array_slice($events, -20);

        return $response;
    }

    private function appendPayableWebhookMeta(Payment $payment, string $event, array $payload): void
    {
        if ($payment->type !== Payment::TYPE_ORDER || $payment->payable_type !== Order::class) {
            return;
        }

        /** @var Order|null $order */
        $order = Order::query()->find($payment->payable_id);

        if ($order) {
            $this->appendOrderWebhookMeta($order, $event, $payload, $payment);
        }
    }

    private function appendOrderWebhookMeta(Order $order, string $event, array $payload, ?Payment $payment = null): void
    {
        $meta = is_array($order->payment_meta) ? $order->payment_meta : [];
        $events = is_array($meta['webhooks'] ?? null) ? $meta['webhooks'] : [];
        $events[] = array_merge($this->webhookEvent($event, $payload), [
            'payment_id' => $payment?->id,
            'payment_external_id' => $payment?->external_id,
        ]);

        $meta['webhooks'] = array_slice($events, -20);
        $order->forceFill(['payment_meta' => $meta])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function webhookEvent(string $event, array $payload): array
    {
        return [
            'event' => $event,
            'received_at' => now()->toISOString(),
            'transaction_id' => $this->transactionId($payload),
            'reason' => $payload['Reason'] ?? $payload['reason'] ?? null,
            'reason_code' => $payload['ReasonCode'] ?? $payload['reasonCode'] ?? null,
            'raw' => $this->sanitizeWebhookPayload($payload),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizeWebhookPayload(array $payload): array
    {
        $blockedKeys = [
            'CardCryptogramPacket',
            'CardFirstSix',
            'CardLastFour',
            'CardType',
            'Token',
            'Name',
            'IpAddress',
        ];

        foreach ($blockedKeys as $key) {
            unset($payload[$key], $payload[lcfirst($key)]);
        }

        return $payload;
    }
}
