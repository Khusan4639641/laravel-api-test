<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Validation\ValidationException;

class OrderPaymentSplitService
{
    /**
     * @return array{payment_strategy: string, total_amount: string, card_amount: string, deposit_amount: string}
     */
    public function split(float|int|string $totalAmount, ?string $strategy): array
    {
        $strategy = $strategy ?: Order::PAYMENT_STRATEGY_CARD_100;
        $totalAmount = $this->decimal($totalAmount);

        if (! in_array($strategy, [
            Order::PAYMENT_STRATEGY_CARD_100,
            Order::PAYMENT_STRATEGY_CARD_50_DEPOSIT_50,
            Order::PAYMENT_STRATEGY_DEPOSIT_100,
        ], true)) {
            throw ValidationException::withMessages([
                'payment_strategy' => 'Недопустимый способ оплаты.',
            ]);
        }

        if ($strategy === Order::PAYMENT_STRATEGY_CARD_100) {
            return [
                'payment_strategy' => $strategy,
                'total_amount' => $totalAmount,
                'card_amount' => $totalAmount,
                'deposit_amount' => '0.00',
            ];
        }

        if ($strategy === Order::PAYMENT_STRATEGY_DEPOSIT_100) {
            return [
                'payment_strategy' => $strategy,
                'total_amount' => $totalAmount,
                'card_amount' => '0.00',
                'deposit_amount' => $totalAmount,
            ];
        }

        $cardAmount = $this->decimal(min((float) $totalAmount, ceil(((float) $totalAmount) / 2)));
        $depositAmount = $this->decimal(bcsub($totalAmount, $cardAmount, 2));

        return [
            'payment_strategy' => $strategy,
            'total_amount' => $totalAmount,
            'card_amount' => $cardAmount,
            'deposit_amount' => $depositAmount,
        ];
    }

    private function decimal(float|int|string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

}
