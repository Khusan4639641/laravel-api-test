<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TipTopPayService
{
    private const PROVIDER = 'tiptoppay';

    /**
     * @return array<string, mixed>
     */
    public function createIntent(Order $order, User $actor): array
    {
        $this->assertActorCanPay($order, $actor);
        $this->assertPaymentConfigIsUsable();

        $order->loadMissing(['user.profile', 'items.product', 'items.package']);

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

        if (strtoupper((string) config('tiptoppay.currency', 'KZT')) !== 'KZT') {
            throw ValidationException::withMessages([
                'currency' => 'Онлайн-оплата доступна только в KZT',
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

        $externalId = sprintf('order-%s-%s', $order->id, (string) Str::uuid());
        $successUrl = $this->urlWithOrder((string) config('tiptoppay.success_url'), $order, $externalId);
        $failUrl = $this->urlWithOrder((string) config('tiptoppay.fail_url'), $order, $externalId);

        $intent = [
            'publicTerminalId' => (string) config('tiptoppay.public_terminal_id'),
            'description' => sprintf('Оплата заказа #%s на Safi Life', $order->order_number ?: $order->id),
            'paymentSchema' => $this->paymentSchema(),
            'currency' => 'KZT',
            'amount' => $this->numericAmount($order->total_amount),
            'externalId' => $externalId,
            'successRedirectUrl' => $successUrl,
            'failRedirectUrl' => $failUrl,
            'userInfo' => $this->userInfo($order),
            'items' => $this->items($order),
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'user_id' => $order->user_id,
            ],
        ];

        $paymentMeta = is_array($order->payment_meta) ? $order->payment_meta : [];
        $paymentMeta['last_intent'] = [
            'external_id' => $externalId,
            'created_at' => now()->toISOString(),
            'amount' => (string) $order->total_amount,
            'currency' => 'KZT',
            'payment_schema' => $intent['paymentSchema'],
        ];

        $order->forceFill([
            'payment_provider' => self::PROVIDER,
            'payment_status' => 'pending',
            'payment_external_id' => $externalId,
            'payment_meta' => $paymentMeta,
        ])->save();

        return $intent;
    }

    /**
     * @return array<string, mixed>
     */
    public function handleCheck(Request $request): array
    {
        if (! $this->validWebhookSignature($request)) {
            return ['code' => 13];
        }

        $payload = $this->payload($request);
        $order = $this->findOrder($payload);

        if (! $order) {
            return ['code' => 10];
        }

        if (! $this->amountMatches($order, $payload) || ! $this->currencyMatches($payload)) {
            return ['code' => 12];
        }

        if (in_array($order->payment_status, ['paid', 'refunded', 'cancelled'], true) || in_array($order->status, ['cancelled', 'voided'], true)) {
            return ['code' => 13];
        }

        $this->appendWebhookMeta($order, 'check', $payload);

        return ['code' => 0];
    }

    /**
     * @return array<string, mixed>
     */
    public function handlePay(Request $request): array
    {
        if (! $this->validWebhookSignature($request)) {
            return ['code' => 13];
        }

        $payload = $this->payload($request);
        $order = $this->findOrder($payload);

        if (! $order) {
            return ['code' => 10];
        }

        if (! $this->amountMatches($order, $payload) || ! $this->currencyMatches($payload)) {
            return ['code' => 12];
        }

        DB::transaction(function () use ($order, $payload): void {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $transactionId = $this->transactionId($payload);

            if ($lockedOrder->payment_status === 'paid') {
                $this->appendWebhookMeta($lockedOrder, 'pay_duplicate', $payload);

                return;
            }

            $lockedOrder->forceFill([
                'status' => $lockedOrder->status === 'pending' ? 'confirmed' : $lockedOrder->status,
                'payment_provider' => self::PROVIDER,
                'payment_status' => 'paid',
                'payment_transaction_id' => $transactionId ?: $lockedOrder->payment_transaction_id,
                'paid_at' => $lockedOrder->paid_at ?: now(),
            ])->save();

            $this->appendWebhookMeta($lockedOrder, 'pay', $payload);
        });

        return ['code' => 0];
    }

    /**
     * @return array<string, mixed>
     */
    public function handleFail(Request $request): array
    {
        if (! $this->validWebhookSignature($request)) {
            return ['code' => 13];
        }

        $payload = $this->payload($request);
        $order = $this->findOrder($payload);

        if (! $order) {
            return ['code' => 10];
        }

        DB::transaction(function () use ($order, $payload): void {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->payment_status !== 'paid') {
                $lockedOrder->forceFill([
                    'payment_provider' => self::PROVIDER,
                    'payment_status' => 'failed',
                    'payment_transaction_id' => $this->transactionId($payload) ?: $lockedOrder->payment_transaction_id,
                ])->save();
            }

            $this->appendWebhookMeta($lockedOrder, 'fail', $payload);
        });

        return ['code' => 0];
    }

    /**
     * @return array<string, mixed>
     */
    public function handleRefund(Request $request): array
    {
        return $this->handleTerminalPaymentStatus($request, 'refunded', 'cancelled', 'refund');
    }

    /**
     * @return array<string, mixed>
     */
    public function handleCancel(Request $request): array
    {
        return $this->handleTerminalPaymentStatus($request, 'cancelled', 'cancelled', 'cancel');
    }

    private function handleTerminalPaymentStatus(Request $request, string $paymentStatus, string $orderStatus, string $event): array
    {
        if (! $this->validWebhookSignature($request)) {
            return ['code' => 13];
        }

        $payload = $this->payload($request);
        $order = $this->findOrder($payload);

        if (! $order) {
            return ['code' => 10];
        }

        DB::transaction(function () use ($order, $payload, $paymentStatus, $orderStatus, $event): void {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $lockedOrder->forceFill([
                'status' => $orderStatus,
                'payment_provider' => self::PROVIDER,
                'payment_status' => $paymentStatus,
                'payment_transaction_id' => $this->transactionId($payload) ?: $lockedOrder->payment_transaction_id,
            ])->save();

            $this->appendWebhookMeta($lockedOrder, $event, $payload);
        });

        return ['code' => 0];
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
                'publicTerminalId' => 'Публичный терминал TipTop Pay не настроен',
            ]);
        }
    }

    private function paymentSchema(): string
    {
        $schema = (string) config('tiptoppay.payment_schema', 'Single');

        return in_array($schema, ['Single', 'Dual'], true) ? $schema : 'Single';
    }

    private function urlWithOrder(string $baseUrl, Order $order, string $externalId): string
    {
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl.$separator.http_build_query([
            'order' => $order->id,
            'external_id' => $externalId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function userInfo(Order $order): array
    {
        $user = $order->user;
        $profile = $user?->profile;

        return array_filter([
            'accountId' => (string) $order->user_id,
            'fullName' => $order->recipient_name ?: $user?->name,
            'phone' => $order->phone ?: $user?->phone ?: $profile?->phone,
            'email' => $user?->email,
            'city' => $order->city ?: $profile?->city,
            'address' => $order->delivery_address,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function items(Order $order): array
    {
        return $order->items->map(function ($item): array {
            $snapshot = is_array($item->item_snapshot) ? $item->item_snapshot : [];

            return [
                'id' => (string) ($item->product_id ?: $item->id),
                'name' => $item->product_name ?: ($snapshot['name'] ?? 'Safi Life product'),
                'count' => (int) $item->quantity,
                'price' => $this->numericAmount($item->unit_price),
            ];
        })->values()->all();
    }

    private function numericAmount(mixed $value): float|int
    {
        $amount = round((float) $value, 2);

        return floor($amount) === $amount ? (int) $amount : $amount;
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
            return true;
        }

        $received = $request->header('X-Content-HMAC') ?: $request->header('Content-HMAC');

        if (! is_string($received) || trim($received) === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        return hash_equals($expected, $received);
    }

    private function findOrder(array $payload): ?Order
    {
        $externalId = $this->externalId($payload);

        if ($externalId !== '') {
            $order = Order::query()->where('payment_external_id', $externalId)->first();

            if ($order) {
                return $order;
            }

            if (preg_match('/^order-(\d+)-/i', $externalId, $matches)) {
                return Order::query()->find((int) $matches[1]);
            }
        }

        $orderId = Arr::get($payload, 'Data.order_id') ?? Arr::get($payload, 'data.order_id') ?? Arr::get($payload, 'metadata.order_id');

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

    private function amountMatches(Order $order, array $payload): bool
    {
        $amount = $payload['Amount'] ?? $payload['amount'] ?? null;

        if (! is_numeric($amount)) {
            return true;
        }

        return bccomp((string) $order->total_amount, (string) $amount, 2) === 0;
    }

    private function currencyMatches(array $payload): bool
    {
        $currency = strtoupper((string) ($payload['Currency'] ?? $payload['currency'] ?? 'KZT'));

        return $currency === 'KZT';
    }

    private function appendWebhookMeta(Order $order, string $event, array $payload): void
    {
        $meta = is_array($order->payment_meta) ? $order->payment_meta : [];
        $events = is_array($meta['webhooks'] ?? null) ? $meta['webhooks'] : [];
        $events[] = [
            'event' => $event,
            'received_at' => now()->toISOString(),
            'transaction_id' => $this->transactionId($payload),
            'reason' => $payload['Reason'] ?? $payload['reason'] ?? null,
            'reason_code' => $payload['ReasonCode'] ?? $payload['reasonCode'] ?? null,
            'raw' => $payload,
        ];

        $meta['webhooks'] = array_slice($events, -20);
        $order->forceFill(['payment_meta' => $meta])->save();
    }
}
