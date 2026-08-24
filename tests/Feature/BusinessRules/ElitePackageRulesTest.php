<?php

namespace Tests\Feature\BusinessRules;

use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\User;
use App\Services\BinaryTreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ElitePackageRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_elite_upgrade_adds_first_two_hundred_pv_to_turnover(): void
    {
        [$vip, $elite] = $this->createVipAndElitePackages();
        $user = User::factory()->create([
            'current_package_id' => $vip->id,
            'total_pv' => 300,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$elite->id}/upgrade")
            ->assertOk()
            ->assertJsonPath('additional_pv', '200.00');

        $this->assertSame('500.00', $user->refresh()->total_pv);
    }

    public function test_first_two_hundred_elite_pv_does_not_create_referral_bonus(): void
    {
        [$vip, $elite] = $this->createVipAndElitePackages();
        $sponsor = User::factory()->create([
            'current_package_id' => $vip->id,
        ]);
        $user = User::factory()->create([
            'sponsor_id' => $sponsor->id,
            'current_package_id' => $vip->id,
            'total_pv' => 300,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$elite->id}/upgrade")
            ->assertOk();

        $this->assertSame(0, BonusTransaction::query()->where('bonus_type', 'referral')->count());
    }

    public function test_first_two_hundred_elite_pv_does_not_create_binary_bonus(): void
    {
        [$vip, $elite] = $this->createVipAndElitePackages();
        $sponsor = User::factory()->create([
            'current_package_id' => $vip->id,
            'right_pv' => 200,
            'remaining_right_pv' => 200,
        ]);
        $user = User::factory()->create([
            'sponsor_id' => $sponsor->id,
            'current_package_id' => $vip->id,
            'total_pv' => 300,
        ]);

        $tree = app(BinaryTreeService::class);
        $tree->placeUser($sponsor);
        $tree->placeUser($user, $sponsor, 'L');

        Sanctum::actingAs($user);
        $this->postJson("/api/packages/{$elite->id}/upgrade")->assertOk();

        $sponsor->refresh();

        $this->assertSame('200.00', $sponsor->left_pv);
        $this->assertSame('0.00', $sponsor->remaining_left_pv);
        $this->assertSame('200.00', $sponsor->remaining_right_pv);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->postJson('/api/admin/bonuses/binary/calculate', [
            'user_id' => $sponsor->refresh()->id,
        ])
            ->assertOk()
            ->assertJsonPath('bonus_transaction', null);

        $this->assertSame(0, BonusTransaction::query()->where('bonus_type', 'binary')->count());
    }

    /**
     * @return array{0: Package, 1: Package}
     */
    private function createVipAndElitePackages(): array
    {
        return [
            $this->createPackage('VIP', 180000, 300, 8, 2),
            $this->createPackage('ELITE', 300000, 500, 10, 3),
        ];
    }

    private function createPackage(string $code, int $price, int $pv, int $binaryPercent, int $sortOrder): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => $price,
            'pv' => $pv,
            'activity_pv' => $pv,
            'turnover_pv' => $code === 'ELITE' ? 200 : $pv,
            'referral_percent' => 10,
            'binary_percent' => $binaryPercent,
            'sort_order' => $sortOrder,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}
