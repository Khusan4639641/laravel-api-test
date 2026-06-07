<?php

namespace Tests\Feature\Seeders;

use App\Models\BinaryNode;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoBinaryTreeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_is_disabled_in_production(): void
    {
        $originalEnvironment = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->artisan('safi:seed-demo-tree')
                ->expectsOutput('This command is disabled in production.')
                ->assertExitCode(Command::FAILURE);
        } finally {
            $this->app['env'] = $originalEnvironment;
        }
    }

    public function test_command_creates_requested_count_in_local_testing(): void
    {
        $this->runDemoTreeCommand(count: 50);

        $this->assertSame(50, $this->demoUsersQuery()->count());
    }

    public function test_command_creates_at_least_ten_roots(): void
    {
        $this->runDemoTreeCommand(count: 50, roots: 10);

        $roots = User::query()
            ->where('email', 'like', 'demo_root_%@safilife.test')
            ->whereNull('sponsor_id')
            ->whereHas('binaryNode', fn ($query) => $query->whereNull('parent_id'))
            ->count();

        $this->assertGreaterThanOrEqual(10, $roots);
    }

    public function test_command_creates_at_least_four_levels_depth(): void
    {
        $this->runDemoTreeCommand(count: 200, roots: 10);

        $demoUserIds = $this->demoUsersQuery()->pluck('id');

        $this->assertGreaterThanOrEqual(4, (int) BinaryNode::query()->whereIn('user_id', $demoUserIds)->max('depth'));
    }

    public function test_command_creates_unique_emails_and_logins(): void
    {
        $this->runDemoTreeCommand(count: 80);

        $users = $this->demoUsersQuery()->get(['email', 'login']);

        $this->assertSame(80, $users->pluck('email')->unique()->count());
        $this->assertSame(80, $users->pluck('login')->unique()->count());
    }

    public function test_fresh_demo_does_not_delete_non_demo_users(): void
    {
        $realUser = User::factory()->create([
            'email' => 'real.user@safilife.test',
            'login' => 'real_user',
        ]);

        $this->runDemoTreeCommand(count: 30);
        $this->runDemoTreeCommand(count: 25);

        $this->assertDatabaseHas('users', [
            'id' => $realUser->id,
            'email' => 'real.user@safilife.test',
        ]);
        $this->assertSame(25, $this->demoUsersQuery()->count());
    }

    public function test_all_demo_users_have_user_role_and_active_account_status(): void
    {
        $this->runDemoTreeCommand(count: 50);

        $this->assertSame(50, $this->demoUsersQuery()
            ->where('role', User::ROLE_USER)
            ->where('account_status', 'active')
            ->count());
    }

    public function test_super_admin_exists_after_command(): void
    {
        $this->runDemoTreeCommand(count: 30);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@safilife.test',
            'login' => 'admin',
            'role' => User::ROLE_SUPER_ADMIN,
            'account_status' => 'active',
        ]);
    }

    private function runDemoTreeCommand(int $count = 50, int $roots = 10): void
    {
        $this->artisan('safi:seed-demo-tree', [
            '--count' => $count,
            '--roots' => $roots,
            '--password' => 'password',
            '--fresh-demo' => true,
        ])->assertExitCode(Command::SUCCESS);
    }

    private function demoUsersQuery()
    {
        return User::query()->where('email', 'like', 'demo_%@safilife.test');
    }
}
