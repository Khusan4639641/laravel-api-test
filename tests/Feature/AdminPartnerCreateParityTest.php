<?php

namespace Tests\Feature;

use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPartnerCreateParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_single_create_uses_same_package_pv_and_placement_logic_as_bulk_create(): void
    {
        $start = $this->createPackage('START');
        $singleSponsor = User::factory()->create();
        $bulkSponsor = User::factory()->create();

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->postJson('/api/admin/partners', $this->payload('single-parity', [
            'sponsor_id' => $singleSponsor->id,
            'branch' => 'left',
            'package_id' => $start->id,
        ]))->assertCreated();

        $this->postJson('/api/admin/partners/bulk-create', [
            'partners' => [
                $this->payload('bulk-parity', [
                    'sponsor_id' => $bulkSponsor->id,
                    'branch' => 'left',
                    'package_id' => $start->id,
                ]),
            ],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'created')
            ->assertJsonCount(0, 'failed');

        $single = User::query()->where('login', 'single-parity')->firstOrFail();
        $bulk = User::query()->where('login', 'bulk-parity')->firstOrFail();

        $this->assertSame($start->id, $single->current_package_id);
        $this->assertSame($start->id, $bulk->current_package_id);
        $this->assertSame('100.00', $single->total_pv);
        $this->assertSame('100.00', $bulk->total_pv);
        $this->assertSame('L', $single->binaryNode()->firstOrFail()->position);
        $this->assertSame('L', $bulk->binaryNode()->firstOrFail()->position);
        $this->assertSame('100.00', $singleSponsor->refresh()->left_pv);
        $this->assertSame('100.00', $bulkSponsor->refresh()->left_pv);
        $this->assertSame(1, WalletTransaction::query()->where('user_id', $single->id)->where('type', 'package_assignment')->count());
        $this->assertSame(1, WalletTransaction::query()->where('user_id', $bulk->id)->where('type', 'package_assignment')->count());
    }

    public function test_admin_bulk_create_still_works(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->postJson('/api/admin/partners/bulk-create', [
            'partners' => [
                $this->payload('bulk-still-one'),
                $this->payload('bulk-still-two'),
            ],
        ])
            ->assertOk()
            ->assertJsonCount(2, 'created')
            ->assertJsonCount(0, 'failed');

        $this->assertDatabaseHas('users', ['login' => 'bulk-still-one']);
        $this->assertDatabaseHas('users', ['login' => 'bulk-still-two']);
    }

    public function test_admin_single_create_with_referral_checkbox_false_does_not_pay_referral(): void
    {
        $vip = $this->createPackage('VIP');
        $sponsor = User::factory()->create();

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->postJson('/api/admin/partners', $this->payload('admin-no-referral', [
            'sponsor_id' => $sponsor->id,
            'branch' => 'right',
            'package_id' => $vip->id,
            'pay_referral_bonus' => false,
        ]))->assertCreated();

        $this->assertSame(0, BonusTransaction::query()->where('bonus_type', 'referral')->count());
        $this->assertSame(0, WalletTransaction::query()->where('type', 'referral_bonus')->count());
    }

    public function test_admin_single_create_with_referral_checkbox_true_pays_referral_for_assigned_package(): void
    {
        $vip = $this->createPackage('VIP');
        $adminSponsor = User::factory()->create(['login' => 'admin_sponsor']);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->postJson('/api/admin/partners', $this->payload('admin-referral', [
            'sponsor_id' => $adminSponsor->id,
            'branch' => 'left',
            'package_id' => $vip->id,
            'pay_referral_bonus' => true,
        ]))->assertCreated();

        $adminBonus = BonusTransaction::query()->where('user_id', $adminSponsor->id)->firstOrFail();

        $this->assertSame('15000.00', $adminBonus->amount);
        $this->assertSame('150000.00', $adminBonus->metadata['base_amount']);
    }

    public function test_admin_single_create_with_referral_checkbox_true_does_not_duplicate_referral_bonus(): void
    {
        $start = $this->createPackage('START');
        $sponsor = User::factory()->create();

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->postJson('/api/admin/partners', $this->payload('admin-no-duplicate', [
            'sponsor_id' => $sponsor->id,
            'branch' => 'left',
            'package_id' => $start->id,
            'pay_referral_bonus' => true,
        ]))->assertCreated();

        $createdUser = User::query()->where('login', 'admin-no-duplicate')->firstOrFail();

        $this->assertSame(1, BonusTransaction::query()
            ->where('user_id', $sponsor->id)
            ->where('source_user_id', $createdUser->id)
            ->where('bonus_type', 'referral')
            ->count());
        $this->assertSame(1, WalletTransaction::query()
            ->where('user_id', $sponsor->id)
            ->where('type', 'referral_bonus')
            ->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(string $login, array $overrides = []): array
    {
        return [
            'name' => "Admin {$login}",
            'login' => $login,
            'email' => "{$login}@example.test",
            'phone' => '+7700'.str_pad((string) (abs(crc32($login)) % 10000000), 7, '0', STR_PAD_LEFT),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => User::ROLE_USER,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function publicPayload(string $login, array $overrides = []): array
    {
        return [
            'name' => "Public {$login}",
            'login' => $login,
            'email' => "{$login}@example.test",
            'phone' => '+77000000003',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            ...$overrides,
        ];
    }

    private function createPackage(string $code): Package
    {
        $matrix = [
            'START' => ['price' => 60000, 'activity_pv' => 100, 'turnover_pv' => 100, 'binary_percent' => 7, 'sort_order' => 1],
            'VIP' => ['price' => 180000, 'activity_pv' => 300, 'turnover_pv' => 300, 'binary_percent' => 8, 'sort_order' => 2],
        ];
        $values = $matrix[$code];

        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => $values['price'],
            'pv' => $values['activity_pv'],
            'activity_pv' => $values['activity_pv'],
            'turnover_pv' => $values['turnover_pv'],
            'referral_percent' => 10,
            'binary_percent' => $values['binary_percent'],
            'sort_order' => $values['sort_order'],
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}
