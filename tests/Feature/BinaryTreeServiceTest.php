<?php

namespace Tests\Feature;

use App\Models\BinaryNode;
use App\Models\User;
use App\Services\BinaryTreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BinaryTreeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_places_user_in_requested_free_branch(): void
    {
        $service = app(BinaryTreeService::class);
        $sponsor = User::factory()->create();
        $user = User::factory()->create();

        $node = $service->placeUser($user, $sponsor, 'L');

        $sponsorNode = $sponsor->binaryNode()->first();

        $this->assertNotNull($sponsorNode);
        $this->assertSame($sponsorNode->id, $node->parent_id);
        $this->assertSame('L', $node->position);
        $this->assertSame(1, $node->depth);
    }

    public function test_it_spills_over_to_nearest_free_place_down_selected_branch(): void
    {
        $service = app(BinaryTreeService::class);
        $sponsor = User::factory()->create();
        $firstLeft = User::factory()->create();
        $secondLeft = User::factory()->create();

        $firstLeftNode = $service->placeUser($firstLeft, $sponsor, 'L');
        $secondLeftNode = $service->placeUser($secondLeft, $sponsor, 'L');

        $this->assertSame($firstLeftNode->id, $secondLeftNode->parent_id);
        $this->assertSame('L', $secondLeftNode->position);
        $this->assertSame(2, $secondLeftNode->depth);
    }

    public function test_referral_endpoint_returns_link_status_and_spillover_parent(): void
    {
        $service = app(BinaryTreeService::class);
        $sponsor = User::factory()->create();
        $leftUser = User::factory()->create();
        $service->placeUser($leftUser, $sponsor, 'L');

        $response = $this->getJson("/api/ref/{$sponsor->id}/L");

        $response
            ->assertOk()
            ->assertJsonPath('sponsor.id', $sponsor->id)
            ->assertJsonPath('branch', 'L')
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('direct_branch_available', false)
            ->assertJsonPath('spillover_parent_id', $leftUser->id);
    }

    public function test_register_places_user_when_sponsor_and_branch_are_present(): void
    {
        $sponsor = User::factory()->create();

        $response = $this->postJson('/api/register', [
            'name' => 'Referral User',
            'login' => 'referral_user',
            'email' => 'referral@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'sponsor_id' => $sponsor->id,
            'branch' => 'R',
        ]);

        $response->assertCreated();

        $user = User::query()->where('login', 'referral_user')->firstOrFail();
        $sponsorNode = BinaryNode::query()->where('user_id', $sponsor->id)->firstOrFail();
        $userNode = BinaryNode::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame($sponsor->id, $user->sponsor_id);
        $this->assertSame($sponsorNode->id, $userNode->parent_id);
        $this->assertSame('R', $userNode->position);
    }

    public function test_register_places_user_by_referral_code_and_branch(): void
    {
        $sponsor = User::factory()->create([
            'login' => 'SAFI',
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'Referral Code User',
            'login' => 'referral_code_user',
            'email' => 'referral-code@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'referral_code' => 'SAFI',
            'branch' => 'left',
        ]);

        $response->assertCreated();

        $user = User::query()->where('login', 'referral_code_user')->firstOrFail();
        $sponsorNode = BinaryNode::query()->where('user_id', $sponsor->id)->firstOrFail();
        $userNode = BinaryNode::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame($sponsor->id, $user->sponsor_id);
        $this->assertSame($sponsorNode->id, $userNode->parent_id);
        $this->assertSame('L', $userNode->position);
    }

    public function test_register_without_referral_does_not_assign_sponsor_or_branch(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Normal User',
            'login' => 'normal_user',
            'email' => 'normal@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated();

        $user = User::query()->where('login', 'normal_user')->firstOrFail();

        $this->assertNull($user->sponsor_id);
        $this->assertFalse(BinaryNode::query()->where('user_id', $user->id)->exists());
    }

    public function test_register_rejects_missing_referral_branch(): void
    {
        User::factory()->create([
            'login' => 'SAFI',
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'Missing Branch User',
            'login' => 'missing_branch_user',
            'email' => 'missing-branch@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'referral_code' => 'SAFI',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch']);
    }
}
