<?php

namespace App\Services;

use App\Models\Package;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PackagePurchaseAvailabilityService
{
    private const PACKAGE_PURCHASE_DISABLED_MESSAGE = 'Покупка пакетов пользователем временно недоступна. Обратитесь к администратору.';

    private const PACKAGE_ORDER = [
        'START' => 1,
        'VIP' => 2,
        'ELITE' => 3,
    ];

    private const UPGRADE_CHAIN = [
        'START' => 'VIP',
        'VIP' => 'ELITE',
    ];

    /**
     * @return array<string, mixed>
     */
    public function actionFor(User $user, Package $package, bool $respectPaymentConfig = true): array
    {
        if (! config('safi.user_package_purchases_enabled', false)) {
            return $this->informationalActionFor($user, $package);
        }

        $action = $this->baseActionFor($user, $package);

        if ($respectPaymentConfig && $action['available'] && ! $this->tipTopPayIsConfigured()) {
            return [
                ...$action,
                'available' => false,
                'action' => 'locked',
                'button_label' => 'Недоступно',
                'disabled_reason' => 'Онлайн-оплата временно недоступна',
            ];
        }

        return $action;
    }

    /**
     * @return array{transition: string, amount: string, upgrade_from: string|null, description: string, final_activity_pv: string, turnover_delta_pv: string}
     */
    public function transitionForPayment(User $user, Package $targetPackage, ?string $upgradeFrom = null): array
    {
        if (! config('safi.user_package_purchases_enabled', false)) {
            throw ValidationException::withMessages([
                'package' => self::PACKAGE_PURCHASE_DISABLED_MESSAGE,
            ]);
        }

        $action = $this->actionFor($user, $targetPackage, false);

        if (! $action['available']) {
            throw ValidationException::withMessages([
                'package' => $action['disabled_reason'] ?: 'Пакет недоступен для оплаты',
            ]);
        }

        $expectedUpgradeFrom = $action['upgrade_from'];

        if ($upgradeFrom !== null && strtoupper($upgradeFrom) !== (string) $expectedUpgradeFrom) {
            throw ValidationException::withMessages([
                'upgrade_from' => 'Текущий пакет пользователя изменился. Обновите страницу и повторите оплату.',
            ]);
        }

        $targetCode = $this->code($targetPackage);
        $transition = $action['action'] === 'upgrade' ? 'upgrade' : 'activation';

        return [
            'transition' => $transition,
            'amount' => $this->decimal((string) $action['amount']),
            'upgrade_from' => $expectedUpgradeFrom,
            'description' => $transition === 'upgrade'
                ? sprintf('Upgrade пакета %s -> %s на Safi Life', $expectedUpgradeFrom, $targetCode)
                : sprintf('Оплата пакета %s на Safi Life', $targetCode),
            'final_activity_pv' => $targetPackage->activityPv(),
            'turnover_delta_pv' => $action['turnover_delta_pv'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseActionFor(User $user, Package $package): array
    {
        $user->loadMissing('currentPackage');

        $targetCode = $this->code($package);
        $currentPackage = $user->currentPackage;
        $currentCode = $currentPackage ? $this->code($currentPackage) : '';

        if (! $package->is_active || $package->status !== 'active') {
            return $this->locked($package, 'Пакет недоступен');
        }

        if ($currentCode === '') {
            return match ($targetCode) {
                'START' => $this->pay($package),
                'VIP' => $this->locked($package, 'Сначала подключите START'),
                'ELITE' => $this->locked($package, 'Сначала подключите START и VIP'),
                default => $this->locked($package, 'Пакет недоступен'),
            };
        }

        if ($targetCode === $currentCode) {
            return $this->current($package);
        }

        if ($currentCode === 'ELITE' && in_array($targetCode, ['START', 'VIP'], true)) {
            return $this->passed($package);
        }

        if ($currentCode === 'VIP' && $targetCode === 'START') {
            return $this->passed($package);
        }

        $expectedNextCode = self::UPGRADE_CHAIN[$currentCode] ?? null;

        if ($expectedNextCode === $targetCode && $currentPackage && $package->is_upgradeable) {
            return $this->upgrade($currentPackage, $package);
        }

        if ($currentCode === 'START' && $targetCode === 'ELITE') {
            return $this->locked($package, 'Сначала перейдите на VIP');
        }

        return $this->locked($package, 'Недоступный переход пакета');
    }

    /**
     * @return array<string, mixed>
     */
    private function informationalActionFor(User $user, Package $package): array
    {
        $user->loadMissing('currentPackage');

        $targetCode = $this->code($package);
        $currentCode = $user->currentPackage ? $this->code($user->currentPackage) : '';
        $targetRank = self::PACKAGE_ORDER[$targetCode] ?? null;
        $currentRank = self::PACKAGE_ORDER[$currentCode] ?? null;

        if ($targetCode !== '' && $targetCode === $currentCode) {
            return $this->payload(
                package: $package,
                action: 'current',
                available: false,
                buttonLabel: 'Ваш текущий пакет',
                current: true,
            );
        }

        if ($targetRank !== null && $currentRank !== null && $targetRank < $currentRank) {
            return $this->payload(
                package: $package,
                action: 'acquired',
                available: false,
                buttonLabel: 'Уже приобрели',
            );
        }

        return $this->payload(
            package: $package,
            action: 'not_purchased',
            available: false,
            buttonLabel: 'Вы еще не приобрели',
            disabledReason: 'Пакет назначается администратором.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function pay(Package $package): array
    {
        return $this->payload(
            package: $package,
            action: 'pay',
            available: true,
            buttonLabel: 'Оплатить онлайн',
            amount: (string) $package->price,
            finalActivityPv: $package->activityPv(),
            turnoverDeltaPv: $package->turnoverPv(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function upgrade(Package $currentPackage, Package $targetPackage): array
    {
        $amount = bcsub((string) $targetPackage->price, (string) $currentPackage->price, 2);
        $additionalPv = bcsub($targetPackage->activityPv(), $currentPackage->activityPv(), 2);

        if (bccomp($amount, '0', 2) <= 0 || bccomp($additionalPv, '0', 2) <= 0) {
            return $this->locked($targetPackage, 'Недоступный переход пакета');
        }

        return $this->payload(
            package: $targetPackage,
            action: 'upgrade',
            available: true,
            buttonLabel: 'Upgrade онлайн',
            amount: $amount,
            upgradeFrom: $this->code($currentPackage),
            finalActivityPv: $targetPackage->activityPv(),
            turnoverDeltaPv: $this->decimal($additionalPv),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function current(Package $package): array
    {
        return $this->payload(
            package: $package,
            action: 'current',
            available: false,
            current: true,
            buttonLabel: 'Текущий пакет',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function passed(Package $package): array
    {
        return $this->payload(
            package: $package,
            action: 'passed',
            available: false,
            buttonLabel: 'Пройден',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function locked(Package $package, string $reason): array
    {
        return $this->payload(
            package: $package,
            action: 'locked',
            available: false,
            buttonLabel: 'Недоступно',
            disabledReason: $reason,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(
        Package $package,
        string $action,
        bool $available,
        string $buttonLabel,
        bool $current = false,
        ?string $disabledReason = null,
        ?string $amount = null,
        ?string $upgradeFrom = null,
        ?string $finalActivityPv = null,
        ?string $turnoverDeltaPv = null,
    ): array {
        return [
            'code' => $this->code($package),
            'current' => $current,
            'available' => $available,
            'action' => $action,
            'button_label' => $buttonLabel,
            'disabled_reason' => $disabledReason,
            'amount' => $amount !== null ? $this->decimal($amount) : null,
            'upgrade_from' => $upgradeFrom,
            'final_activity_pv' => $finalActivityPv,
            'turnover_delta_pv' => $turnoverDeltaPv,
        ];
    }

    private function tipTopPayIsConfigured(): bool
    {
        return (bool) config('tiptoppay.enabled')
            && trim((string) config('tiptoppay.public_terminal_id')) !== ''
            && strtoupper((string) config('tiptoppay.currency', 'KZT')) === 'KZT';
    }

    private function code(Package $package): string
    {
        $code = mb_strtoupper(trim((string) ($package->code ?: $package->name)));

        return match ($code) {
            'СТАРТ' => 'START',
            'ЭЛИТ', 'ЭЛИТНЫЙ' => 'ELITE',
            default => $code,
        };
    }

    private function decimal(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
