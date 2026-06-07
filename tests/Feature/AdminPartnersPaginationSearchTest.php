<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPartnersPaginationSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_partners_supports_limit_offset(): void
    {
        User::factory()->count(30)->create(['role' => User::ROLE_USER, 'status' => 'user']);
        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/partners?limit=10&offset=0&sort_by=id&sort_dir=asc')
            ->assertOk();

        $this->assertCount(10, $response->json('data'));
        $this->assertSame(30, $response->json('pagination.total'));
        $this->assertSame(30, $response->json('pagination.filtered_total'));
        $this->assertSame(10, $response->json('pagination.limit'));
        $this->assertSame(0, $response->json('pagination.offset'));
        $this->assertTrue($response->json('pagination.has_next'));
        $this->assertFalse($response->json('pagination.has_prev'));
    }

    public function test_admin_partners_offset_returns_second_page(): void
    {
        User::factory()->count(30)->create(['role' => User::ROLE_USER, 'status' => 'user']);
        Sanctum::actingAs($this->admin());

        $firstPageIds = $this->idsFrom('/api/admin/partners?limit=10&offset=0&sort_by=id&sort_dir=asc');
        $secondPageIds = $this->idsFrom('/api/admin/partners?limit=10&offset=10&sort_by=id&sort_dir=asc');

        $this->assertCount(10, $secondPageIds);
        $this->assertEmpty(array_intersect($firstPageIds, $secondPageIds));
    }

    public function test_search_finds_partner_outside_first_page(): void
    {
        User::factory()->count(40)->create(['role' => User::ROLE_USER, 'status' => 'user']);
        $target = $this->partner([
            'name' => 'Specific Search Partner',
            'login' => 'specific_search_partner',
            'email' => 'specific.search.partner@safilife.test',
        ]);
        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/partners?search=specific.search.partner@safilife.test&limit=10&offset=0')
            ->assertOk();

        $this->assertSame(1, $response->json('pagination.filtered_total'));
        $this->assertSame($target->id, $response->json('data.0.id'));
    }

    public function test_search_by_name_login_email_and_phone(): void
    {
        $target = $this->partner([
            'name' => 'Demo Root 003',
            'login' => 'demo_root_003',
            'email' => 'demo_root_003@safilife.test',
        ], phone: '+77000000399');
        Sanctum::actingAs($this->admin());

        $this->assertContains($target->id, $this->idsFrom('/api/admin/partners?search=Demo%20Root%20003&limit=10'));
        $this->assertContains($target->id, $this->idsFrom('/api/admin/partners?search=demo_root_003&limit=10'));
        $this->assertContains($target->id, $this->idsFrom('/api/admin/partners?search=demo_root_003@safilife.test&limit=10'));
        $this->assertContains($target->id, $this->idsFrom('/api/admin/partners?search=%2B77000000399&limit=10'));
    }

    public function test_search_by_sponsor_name_login_and_email(): void
    {
        $sponsor = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'name' => 'Sponsor Needle Admin',
            'login' => 'sponsor_needle_admin',
            'email' => 'sponsor.needle.admin@safilife.test',
        ]);
        $target = $this->partner(['sponsor_id' => $sponsor->id]);
        Sanctum::actingAs($this->admin());

        $this->assertSame([$target->id], $this->idsFrom('/api/admin/partners?search=Sponsor%20Needle%20Admin&limit=10'));
        $this->assertSame([$target->id], $this->idsFrom('/api/admin/partners?search=sponsor_needle_admin&limit=10'));
        $this->assertSame([$target->id], $this->idsFrom('/api/admin/partners?search=sponsor.needle.admin@safilife.test&limit=10'));
    }

    public function test_search_by_package_code(): void
    {
        $start = $this->package('START');
        $vip = $this->package('VIP');
        $elite = $this->package('ELITE');
        $startPartner = $this->partner(['current_package_id' => $start->id]);
        $vipPartner = $this->partner(['current_package_id' => $vip->id]);
        $elitePartner = $this->partner(['current_package_id' => $elite->id]);
        Sanctum::actingAs($this->admin());

        $this->assertContains($startPartner->id, $this->idsFrom('/api/admin/partners?search=START&limit=10'));
        $this->assertContains($vipPartner->id, $this->idsFrom('/api/admin/partners?search=VIP&limit=10'));
        $this->assertContains($elitePartner->id, $this->idsFrom('/api/admin/partners?search=ELITE&limit=10'));
    }

    public function test_search_by_account_status_active_and_blocked_labels(): void
    {
        $active = $this->partner(['account_status' => 'active']);
        $blocked = $this->partner(['account_status' => 'blocked']);
        Sanctum::actingAs($this->admin());

        $this->assertContains($active->id, $this->idsFrom('/api/admin/partners?search=active&limit=10'));
        $this->assertContains($blocked->id, $this->idsFrom('/api/admin/partners?search=%D0%B7%D0%B0%D0%B1%D0%BB%D0%BE%D0%BA%D0%B8%D1%80%D0%BE%D0%B2%D0%B0%D0%BD&limit=10'));
    }

    public function test_search_excludes_staff_roles(): void
    {
        User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'name' => 'Staff Search Needle',
            'email' => 'staff.search.needle@safilife.test',
        ]);
        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/partners?search=staff.search.needle@safilife.test&limit=10')
            ->assertOk();

        $this->assertSame(0, $response->json('pagination.filtered_total'));
        $this->assertSame([], $response->json('data'));
    }

    public function test_user_and_support_cannot_access_admin_partners_search(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_USER]));
        $this->getJson('/api/admin/partners?search=test')->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPPORT]));
        $this->getJson('/api/admin/partners?search=test')->assertForbidden();
    }

    public function test_search_by_pv_and_balance(): void
    {
        $pvPartner = $this->partner(['left_pv' => 9876, 'right_pv' => 100, 'total_pv' => 9976]);
        $balancePartner = $this->partner();
        Wallet::query()->create([
            'user_id' => $balancePartner->id,
            'type' => 'main',
            'currency' => 'KZT',
            'balance' => 43210,
            'hold_balance' => 0,
            'status' => 'active',
        ]);
        Sanctum::actingAs($this->admin());

        $this->assertContains($pvPartner->id, $this->idsFrom('/api/admin/partners?search=9876&limit=10'));
        $this->assertContains($balancePartner->id, $this->idsFrom('/api/admin/partners?search=43210&limit=10'));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function partner(array $attributes = [], ?string $phone = null): User
    {
        $partner = User::factory()->create(array_merge([
            'role' => User::ROLE_USER,
            'status' => 'user',
            'account_status' => 'active',
        ], $attributes));

        if ($phone !== null) {
            UserProfile::query()->create([
                'user_id' => $partner->id,
                'phone' => $phone,
                'city' => 'Almaty',
            ]);
        }

        return $partner;
    }

    private function package(string $code): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => match ($code) {
                'VIP' => 180000,
                'ELITE' => 300000,
                default => 60000,
            },
            'pv' => match ($code) {
                'VIP' => 300,
                'ELITE' => 500,
                default => 100,
            },
            'activity_pv' => match ($code) {
                'VIP' => 300,
                'ELITE' => 500,
                default => 100,
            },
            'turnover_pv' => $code === 'ELITE' ? 200 : match ($code) {
                'VIP' => 300,
                default => 100,
            },
            'referral_percent' => 10,
            'binary_percent' => match ($code) {
                'VIP' => 8,
                'ELITE' => 10,
                default => 7,
            },
            'sort_order' => match ($code) {
                'VIP' => 2,
                'ELITE' => 3,
                default => 1,
            },
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function idsFrom(string $uri): array
    {
        return collect($this->getJson($uri)->assertOk()->json('data'))
            ->pluck('id')
            ->all();
    }
}
