<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Services\BinaryTreeService;
use App\Services\StatusService;
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
        $package = $this->createPackage('DIRECTOR', 5000);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$package->id}/activate")
            ->assertOk()
            ->assertJsonPath('user.status', 'director');

        $this->assertSame('director', $user->refresh()->status);
    }

    public function test_pv_accrual_up_tree_recalculates_parent_status(): void
    {
        $treeService = app(BinaryTreeService::class);
        $root = User::factory()->create([
            'status' => 'user',
            'total_pv' => 0,
        ]);
        $leftChild = User::factory()->create();
        $package = $this->createPackage('BRONZE', 10000);

        $treeService->placeUser($leftChild, $root, 'L');

        Sanctum::actingAs($leftChild);

        $this->postJson("/api/packages/{$package->id}/activate")
            ->assertOk();

        $this->assertSame('bronze_director', $root->refresh()->status);
        $this->assertSame('bronze_director', $leftChild->refresh()->status);
    }

    public function test_recalculate_statuses_command_updates_all_users(): void
    {
        User::factory()->create([
            'status' => 'user',
            'total_pv' => 2500,
        ]);
        User::factory()->create([
            'status' => 'user',
            'total_pv' => 500000,
        ]);

        $this->artisan('mlm:recalculate-statuses')
            ->expectsOutput('Recalculated statuses for 2 users.')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'total_pv' => 2500,
            'status' => 'leader',
        ]);
        $this->assertDatabaseHas('users', [
            'total_pv' => 500000,
            'status' => 'diamond_director',
        ]);
    }

    private function createPackage(string $code, int $pv): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => $pv,
            'pv' => $pv,
            'referral_percent' => 0,
            'binary_percent' => 0,
            'sort_order' => 1,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}
