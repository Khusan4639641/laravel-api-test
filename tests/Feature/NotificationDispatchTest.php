<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\BonusAccruedNotification;
use App\Notifications\UserRegisteredNotification;
use App\Notifications\WithdrawalRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_notification_to_user(): void
    {
        Notification::fake();

        $this->postJson('/api/register', [
            'name' => 'Notify User',
            'login' => 'notify_user',
            'email' => 'notify@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::query()->where('login', 'notify_user')->firstOrFail();

        Notification::assertSentTo($user, UserRegisteredNotification::class);
    }

    public function test_referral_bonus_sends_notification_to_sponsor(): void
    {
        Notification::fake();

        $sponsorPackage = $this->createPackage('VIP', 180000, 10, 0);
        $activatedPackage = $this->createPackage('START', 30000, 5, 0);
        $sponsor = User::factory()->create([
            'current_package_id' => $sponsorPackage->id,
        ]);
        $user = User::factory()->create([
            'sponsor_id' => $sponsor->id,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$activatedPackage->id}/activate")
            ->assertOk();

        Notification::assertSentTo($sponsor, BonusAccruedNotification::class);
    }

    public function test_binary_bonus_sends_notification_to_user(): void
    {
        Notification::fake();

        $package = $this->createPackage('BUSINESS', 60000, 0, 7);
        $user = User::factory()->create([
            'current_package_id' => $package->id,
            'remaining_left_pv' => 1000,
            'remaining_right_pv' => 1000,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/bonuses/binary/calculate')
            ->assertOk();

        Notification::assertSentTo($user, BonusAccruedNotification::class);
    }

    public function test_withdrawal_request_sends_notification_to_user(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'main',
            'currency' => 'USD',
            'balance' => 1000,
            'hold_balance' => 0,
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/withdrawals', [
            'amount' => 250,
        ])->assertCreated();

        Notification::assertSentTo($user, WithdrawalRequestedNotification::class);
    }

    private function createPackage(string $code, int $price, int $referralPercent, int $binaryPercent): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => $price,
            'pv' => $price,
            'referral_percent' => $referralPercent,
            'binary_percent' => $binaryPercent,
            'sort_order' => 1,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}
