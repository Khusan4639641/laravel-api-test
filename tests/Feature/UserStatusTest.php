<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Models\UserStatusBonus;
use App\Services\BinaryTreeService;
use App\Services\StatusService;
use Database\Seeders\StatusBonusDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_service_resolves_status_by_total_pv_thresholds(): void
    {
        $service = app(StatusService::class);

        $this->assertSame('user', $service->statusForPv(999));
        $this->assertSame('manager', $service->statusForPv(1000));
        $this->assertSame('leader', $service->statusForPv(2500));
        $this->assertSame('director', $service->statusForPv(5000));
        $this->assertSame('bronze_director', $service->statusForPv(10000));
        $this->assertSame('silver_director', $service->statusForPv(25000));
        $this->assertSame('gold_director', $service->statusForPv(50000));
        $this->assertSame('platinum_director', $service->statusForPv(100000));
        $this->assertSame('emerald_director', $service->statusForPv(250000));
        $this->assertSame('diamond_director', $service->statusForPv(500000));
    }

    public function test_package_activation_recalculates_user_status(): void
    {
        $user = User::factory()->create([
            'status' => 'user',
            'total_pv' => 0,
        ]);
        $package = $this->createPackage('START', 5000);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$package->id}/activate")
            ->assertOk()
            ->assertJsonPath('user.status', 'user');

        $this->assertSame('user', $user->refresh()->status);
        $this->assertSame('5000.00', $user->total_pv);
        $this->assertSame('0.00', $user->left_pv);
        $this->assertSame('0.00', $user->right_pv);
    }

    public function test_pv_accrual_up_tree_recalculates_parent_status(): void
    {
        $treeService = app(BinaryTreeService::class);
        $root = User::factory()->create([
            'status' => 'user',
            'total_pv' => 0,
        ]);
        $leftPackage = $this->createPackage('LEFT', 5000);
        $leftChild = User::factory()->create([
            'current_package_id' => $leftPackage->id,
        ]);
        $rightChild = User::factory()->create();
        $package = $this->createPackage('START', 10000);

        $treeService->placeUser($root);
        $treeService->placeUser($leftChild, $root, 'L');
        $treeService->placeUser($rightChild, $root, 'R');

        Sanctum::actingAs($rightChild);

        $this->postJson("/api/packages/{$package->id}/activate")
            ->assertOk();

        $this->assertSame('director', $root->refresh()->status);
        $this->assertSame('user', $rightChild->refresh()->status);
    }

    public function test_recalculate_statuses_command_updates_all_users(): void
    {
        User::factory()->create([
            'status' => 'user',
            'left_pv' => 2500,
            'right_pv' => 3000,
        ]);
        User::factory()->create([
            'status' => 'user',
            'left_pv' => 500000,
            'right_pv' => 600000,
        ]);

        $this->artisan('mlm:recalculate-statuses')
            ->expectsOutput('Recalculated statuses for 2 users.')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'left_pv' => 2500,
            'status' => 'leader',
        ]);
        $this->assertDatabaseHas('users', [
            'left_pv' => 500000,
            'status' => 'diamond_director',
        ]);
    }

    public function test_status_rewards_match_business_tz_text(): void
    {
        app()->setLocale('ru');

        $statuses = collect(app(StatusService::class)->publicStatuses())->keyBy('id');

        $this->assertSame('Путевка в санаторий + 100 000 ₸, при отказе 400 000 ₸', $statuses['bronze_director']['reward']);
        $this->assertSame('Зарубежная поездка + 250 000 ₸, при отказе 750 000 ₸', $statuses['silver_director']['reward']);
        $this->assertSame('100000.00', $statuses['bronze_director']['cash_amount']);
        $this->assertSame('400000.00', $statuses['bronze_director']['compensation_amount']);
        $this->assertSame('250000.00', $statuses['silver_director']['cash_amount']);
        $this->assertSame('750000.00', $statuses['silver_director']['compensation_amount']);
    }

    public function test_status_bonus_is_created_once_for_eligible_status(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);
        $elite = $this->createPackage('ELITE', 500);

        $user = User::factory()->create([
            'current_package_id' => $elite->id,
            'status' => 'user',
            'left_pv' => 5000,
            'right_pv' => 5000,
        ]);

        app(StatusService::class)->recalculate($user);
        app(StatusService::class)->recalculate($user->refresh());

        $statusBonus = UserStatusBonus::query()->where('status_code', 'director')->firstOrFail();
        $wallet = $user->wallets()->where('type', 'main')->firstOrFail();

        $this->assertDatabaseCount('user_status_bonuses', 3);
        $this->assertDatabaseCount('bonus_transactions', 1);
        $this->assertSame($user->id, $statusBonus->user_id);
        $this->assertSame('250000.00', $statusBonus->amount);
        $this->assertSame('250000.00', $wallet->balance);
    }

    private function createPackage(string $code, int $pv): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => $pv,
            'pv' => $pv,
            'activity_pv' => $pv,
            'turnover_pv' => $pv,
            'referral_percent' => 0,
            'binary_percent' => 0,
            'sort_order' => 1,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}
