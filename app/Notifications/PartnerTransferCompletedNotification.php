<?php

namespace App\Notifications;

use App\Models\PartnerTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PartnerTransferCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly PartnerTransfer $partnerTransfer,
        public readonly string $direction,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $isIncoming = $this->direction === 'incoming';
        $counterparty = $isIncoming ? $this->partnerTransfer->sender : $this->partnerTransfer->recipient;
        $counterpartyName = $counterparty?->name ?: 'партнёр';
        $amount = $this->formatMoney((string) $this->partnerTransfer->amount);

        return [
            'type' => $isIncoming ? 'partner_transfer_in' : 'partner_transfer_out',
            'partner_transfer_id' => $this->partnerTransfer->id,
            'transfer_id' => $this->partnerTransfer->id,
            'transfer_uuid' => $this->partnerTransfer->uuid,
            'transaction_id' => $isIncoming
                ? $this->partnerTransfer->recipient_transaction_id
                : $this->partnerTransfer->sender_transaction_id,
            'counterparty_user_id' => $counterparty?->id,
            'amount' => $this->partnerTransfer->amount,
            'title' => [
                'ru' => $isIncoming ? 'Перевод от партнёра' : 'Перевод партнёру',
                'en' => $isIncoming ? 'Partner transfer received' : 'Partner transfer sent',
            ],
            'message' => [
                'ru' => $isIncoming
                    ? "Вам поступил перевод от партнёра {$counterpartyName}: {$amount}."
                    : "Вы перевели партнёру {$counterpartyName}: {$amount}.",
                'en' => $isIncoming
                    ? "You received a partner transfer from {$counterpartyName}: {$amount}."
                    : "You sent a partner transfer to {$counterpartyName}: {$amount}.",
            ],
        ];
    }

    private function formatMoney(string $amount): string
    {
        return number_format((float) $amount, 0, ',', ' ').' ₸';
    }
}
