<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min(50, $request->integer('limit', 10)));
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->limit($limit * 3)
            ->get()
            ->filter(fn (DatabaseNotification $notification): bool => $this->isVisible($notification))
            ->take($limit)
            ->map(fn (DatabaseNotification $notification): array => $this->serialize($notification))
            ->values();

        return response()->json([
            'notifications' => $notifications,
            'data' => $notifications,
            'unread_count' => $request->user()
                ->unreadNotifications()
                ->get()
                ->filter(fn (DatabaseNotification $notification): bool => $this->isVisible($notification))
                ->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'type' => (string) ($data['type'] ?? class_basename($notification->type)),
            'notification_type' => $notification->type,
            'title' => $data['title'] ?? null,
            'message' => $data['message'] ?? null,
            'data' => $data,
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
        ];
    }

    private function isVisible(DatabaseNotification $notification): bool
    {
        $transactionId = $notification->data['transaction_id']
            ?? $notification->data['wallet_transaction_id']
            ?? null;

        if (! $transactionId) {
            return true;
        }

        return WalletTransaction::query()
            ->whereKey($transactionId)
            ->visible()
            ->exists();
    }
}
