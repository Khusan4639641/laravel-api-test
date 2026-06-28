<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BinaryTreeService;
use App\Services\BonusService;
use App\Services\DepositPurchaseService;
use App\Services\StatusBonusService;
use App\Services\StatusService;
use App\Services\X2BonusService;
use Database\Seeders\StatusBonusDefinitionSeeder;
use Database\Seeders\X2BonusDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesBinaryBonusEligibility;
use Tests\TestCase;

class MlmNotificationPersistenceTest extends TestCase
{
    use CreatesBinaryBonusEligibility;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_notification_created_when_status_changes(): void
    {
        $user = User::factory()->create([
            'status' => 'user',
            'left_pv' => 1000,
            'right_pv' => 1200,
        ]);

        app(StatusService::class)->recalculate($user);
        app(StatusService::class)->recalculate($user->refresh());

        $notification = $this->latestNotificationOfType($user->refresh(), 'status_achieved');

        $this->assertNotNull($notification);
        $this->assertSame('manager', $notification->data['status']);
        $this->assertSame(1, $this->notificationCount($user, 'status_achieved'));
        $this->assertLocalizedPayload($notification->data);
    }

    public function test_notification_created_when_referral_bonus_paid_and_not_duplicated(): void
    {
        $sponsor = User::factory()->create();
        $referral = User::factory()->create(['sponsor_id' => $sponsor->id]);

        app(BonusService::class)->accrueReferralBonus($sponsor, $referral, '50000', [], 'referral-notification-key');
        app(BonusService::class)->accrueReferralBonus($sponsor->refresh(), $referral, '50000', [], 'referral-notification-key');

        $notification = $this->latestNotificationOfType($sponsor->refresh(), 'referral_bonus');

        $this->assertNotNull($notification);
        $this->assertSame('referral', $notification->data['bonus_type']);
        $this->assertSame('5000.00', $notification->data['amount']);
        $this->assertSame(1, $this->notificationCount($sponsor, 'referral_bonus'));
        $this->assertLocalizedPayload($notification->data);
    }

    public function test_notification_created_when_binary_bonus_paid(): void
    {
        $package = $this->createPackage('START', 60000, 100, 100, 7);
        $user = User::factory()->create([
            'current_package_id' => $package->id,
            'left_pv' => 1000,
            'right_pv' => 2000,
            'remaining_left_pv' => 1000,
            'remaining_right_pv' => 2000,
        ]);
        $this->makeBinaryBonusEligible($user);

        app(BonusService::class)->calculateBinaryBonus($user);

        $notification = $this->latestNotificationOfType($user->refresh(), 'binary_bonus');

        $this->assertNotNull($notification);
        $this->assertSame('binary', $notification->data['bonus_type']);
        $this->assertSame('35000.00', $notification->data['amount']);
        $this->assertLocalizedPayload($notification->data);
    }

    public function test_notification_created_when_status_bonus_paid(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);

        $package = $this->createPackage('ELITE', 300000, 500, 200, 10);
        $user = User::factory()->create([
            'current_package_id' => $package->id,
            'left_pv' => 5000,
            'right_pv' => 6000,
        ]);

        app(StatusBonusService::class)->checkMissedStatusBonuses($user);

        $notification = $this->latestNotificationOfType($user->refresh(), 'status_bonus');

        $this->assertNotNull($notification);
        $this->assertSame('status_bonus', $notification->data['bonus_type']);
        $this->assertSame('250000.00', $notification->data['amount']);
        $this->assertLocalizedPayload($notification->data);
    }

    public function test_notification_created_when_x2_bonus_paid_and_not_duplicated(): void
    {
        $this->seed(X2BonusDefinitionSeeder::class);

        $user = User::factory()->create();
        $this->createFirstLinePartners($user, 'gold_director', 2, 3);

        app(X2BonusService::class)->awardEligible($user);
        app(X2BonusService::class)->awardEligible($user->refresh());

        $notification = $this->notificationOfX2Code($user->refresh(), 'five_gold_directors');

        $this->assertNotNull($notification);
        $this->assertSame('five_gold_directors', $notification->data['code']);
        $this->assertSame('5000000.00', $notification->data['amount']);
        $this->assertSame(1, $this->x2NotificationCountForCode($user, 'five_gold_directors'));
        $this->assertLocalizedPayload($notification->data);
    }

    public function test_notification_created_when_cashback_paid(): void
    {
        $user = User::factory()->create();
        Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'currency' => 'KZT',
            'balance' => 1000,
            'hold_balance' => 0,
            'status' => 'active',
        ]);

        app(DepositPurchaseService::class)->purchase($user, '1000');

        $notification = $this->latestNotificationOfType($user->refresh(), 'cashback');

        $this->assertNotNull($notification);
        $this->assertSame('cashback', $notification->data['bonus_type']);
        $this->assertSame('200.00', $notification->data['amount']);
        $this->assertLocalizedPayload($notification->data);
    }

    public function test_dashboard_notifications_endpoint_returns_latest_notifications(): void
    {
        $sponsor = User::factory()->create();
        $referral = User::factory()->create(['sponsor_id' => $sponsor->id]);

        app(BonusService::class)->accrueReferralBonus($sponsor, $referral, '50000');

        Sanctum::actingAs($sponsor);

        $this->getJson('/api/dashboard/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.type', 'referral_bonus')
            ->assertJsonPath('notifications.0.data.type', 'referral_bonus')
            ->assertJsonPath('notifications.0.data.message.ru', 'Начислен реферальный бонус: 5 000 ₸.');
    }

    private function createPackage(
        string $code,
        int $price,
        int $activityPv,
        int $turnoverPv,
        int $binaryPercent,
    ): Package {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code).'-'.str()->random(6),
            'price' => $price,
            'pv' => $activityPv,
            'activity_pv' => $activityPv,
            'turnover_pv' => $turnoverPv,
            'referral_percent' => 10,
            'binary_percent' => $binaryPercent,
            'sort_order' => 1,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }

    private function createFirstLinePartners(User $sponsor, string $status, int $leftCount, int $rightCount): void
    {
        foreach (['L' => $leftCount, 'R' => $rightCount] as $side => $count) {
            for ($index = 0; $index < $count; $index++) {
                $this->placePersonalPartner($sponsor, $status, $side);
            }
        }
    }

    private function placePersonalPartner(User $sponsor, string $status, string $side): User
    {
        $this->ensureBinaryRoot($sponsor);

        $partner = User::factory()->create([
            'sponsor_id' => $sponsor->id,
            'status' => $status,
        ]);

        app(BinaryTreeService::class)->placeUser($partner, $sponsor, $side);

        return $partner;
    }

    private function ensureBinaryRoot(User $user): void
    {
        if (! $user->binaryNode()->exists()) {
            app(BinaryTreeService::class)->placeUser($user);
        }
    }

    private function latestNotificationOfType(User $user, string $type): ?object
    {
        return $user->notifications()
            ->latest()
            ->get()
            ->first(fn ($notification): bool => ($notification->data['type'] ?? null) === $type);
    }

    private function notificationCount(User $user, string $type): int
    {
        return $user->notifications()
            ->get()
            ->filter(fn ($notification): bool => ($notification->data['type'] ?? null) === $type)
            ->count();
    }

    private function notificationOfX2Code(User $user, string $code): ?object
    {
        return $user->notifications()
            ->latest()
            ->get()
            ->first(fn ($notification): bool => ($notification->data['type'] ?? null) === 'x2_bonus'
                && ($notification->data['code'] ?? null) === $code);
    }

    private function x2NotificationCountForCode(User $user, string $code): int
    {
        return $user->notifications()
            ->get()
            ->filter(fn ($notification): bool => ($notification->data['type'] ?? null) === 'x2_bonus'
                && ($notification->data['code'] ?? null) === $code)
            ->count();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertLocalizedPayload(array $data): void
    {
        foreach (['ru', 'kz', 'kg', 'en', 'mn'] as $language) {
            $this->assertArrayHasKey($language, $data['title']);
            $this->assertArrayHasKey($language, $data['message']);
            $this->assertNotSame('', $data['title'][$language]);
            $this->assertNotSame('', $data['message'][$language]);
        }
    }
}
