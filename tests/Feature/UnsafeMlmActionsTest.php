<?php

namespace Tests\Feature;

use App\Models\BonusTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnsafeMlmActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_trigger_binary_recalculation_api(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USER,
            'remaining_left_pv' => 1000,
            'remaining_right_pv' => 1000,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/bonuses/binary/calculate')
            ->assertForbidden();

        $this->assertSame(0, BonusTransaction::query()->where('bonus_type', 'binary')->count());
    }

    public function test_user_cannot_delete_partner(): void
    {
        $partner = User::factory()->create(['role' => User::ROLE_USER]);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_USER]));

        $response = $this->deleteJson("/api/admin/partners/{$partner->id}");

        $this->assertContains($response->getStatusCode(), [403, 404, 405]);
        $this->assertDatabaseHas('users', ['id' => $partner->id]);
    }

    public function test_super_admin_partner_delete_route_is_not_implemented(): void
    {
        $partner = User::factory()->create(['role' => User::ROLE_USER]);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $response = $this->deleteJson("/api/admin/partners/{$partner->id}");

        $this->assertContains($response->getStatusCode(), [404, 405]);
        $this->assertDatabaseHas('users', ['id' => $partner->id]);
    }

    public function test_income_calculator_api_route_is_not_added(): void
    {
        $calculatorRoutes = collect(Route::getRoutes())
            ->map(fn ($route): string => strtolower($route->uri()))
            ->filter(fn (string $uri): bool => str_contains($uri, 'calculator'));

        $this->assertSame([], $calculatorRoutes->values()->all());
    }
}
