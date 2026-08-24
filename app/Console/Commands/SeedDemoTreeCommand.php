<?php

namespace App\Console\Commands;

use App\Models\BinaryNode;
use App\Models\Package;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\BinaryTreeService;
use App\Services\WalletService;
use Database\Seeders\PackageSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

#[Signature('safi:seed-demo-tree {--count=1000 : Total demo users to create} {--roots=10 : Independent root users} {--password=password : Password for demo users and local admin} {--fresh-demo : Delete only demo users before seeding}')]
#[Description('Seed a local/testing demo binary tree for Safi Life. Disabled outside local/testing.')]
class SeedDemoTreeCommand extends Command
{
    public function handle(BinaryTreeService $binaryTreeService, WalletService $walletService): int
    {
        if (app()->environment('production')) {
            $this->error('This command is disabled in production.');

            return self::FAILURE;
        }

        if (! app()->environment(['local', 'testing'])) {
            $this->error('This command is enabled only in local/testing environments.');

            return self::FAILURE;
        }

        $count = max((int) $this->option('count'), 1);
        $rootsCount = max((int) $this->option('roots'), 10);
        $password = (string) $this->option('password');
        $hashedPassword = Hash::make($password);

        if ($count < $rootsCount) {
            $count = $rootsCount;
        }

        if (! $this->option('fresh-demo') && $this->demoUsersQuery()->exists()) {
            $this->error('Demo users already exist. Re-run with --fresh-demo to rebuild the demo tree safely.');

            return self::FAILURE;
        }

        $summary = DB::transaction(function () use ($binaryTreeService, $walletService, $count, $rootsCount, $password, $hashedPassword): array {
            if ($this->option('fresh-demo')) {
                $this->deleteDemoUsers();
            }

            app(PackageSeeder::class)->run();

            $packages = Package::query()
                ->whereIn('code', ['START', 'VIP', 'ELITE'])
                ->get()
                ->keyBy('code');
            $startPackage = $packages->get('START');

            $admin = User::query()->updateOrCreate(
                ['email' => 'admin@safilife.test'],
                [
                    'name' => 'Safi Super Admin',
                    'login' => 'admin',
                    'password' => $hashedPassword,
                    'role' => User::ROLE_SUPER_ADMIN,
                    'account_status' => 'active',
                    'status' => 'user',
                    'sponsor_id' => null,
                    'current_package_id' => null,
                    'left_pv' => 0,
                    'right_pv' => 0,
                    'remaining_left_pv' => 0,
                    'remaining_right_pv' => 0,
                    'total_pv' => 0,
                ],
            );
            $walletService->createUserWallets($admin);

            $roots = [];
            $queues = [];
            $queueIndexes = [];
            $childCounts = [];

            for ($index = 1; $index <= $rootsCount; $index++) {
                $root = $this->demoUser(
                    serial: $index,
                    name: sprintf('Demo Root %03d', $index),
                    login: sprintf('demo_root_%03d', $index),
                    email: sprintf('demo_root_%03d@safilife.test', $index),
                    phoneSerial: $index,
                    sponsor: null,
                    package: $this->packageFor($packages, $index) ?? $startPackage,
                    hashedPassword: $hashedPassword,
                    walletService: $walletService,
                );

                if (! $root->binaryNode()->exists()) {
                    $binaryTreeService->placeUser($root, null);
                }

                $root = $root->fresh(['binaryNode']);
                $roots[] = $root;
                $queues[$root->id] = [$root->binaryNode];
                $queueIndexes[$root->id] = 0;
            }

            for ($serial = $rootsCount + 1; $serial <= $count; $serial++) {
                $root = $roots[($serial - $rootsCount - 1) % $rootsCount];

                $partner = $this->demoUser(
                    serial: $serial,
                    name: sprintf('Demo Partner %04d', $serial),
                    login: sprintf('demo_user_%04d', $serial),
                    email: sprintf('demo_user_%04d@safilife.test', $serial),
                    phoneSerial: $serial,
                    sponsor: $root,
                    package: $this->packageFor($packages, $serial) ?? $startPackage,
                    hashedPassword: $hashedPassword,
                    walletService: $walletService,
                );

                if (! $partner->binaryNode()->exists()) {
                    $parentNode = $this->nextOpenParent($queues[$root->id], $queueIndexes[$root->id], $childCounts);
                    $position = ($childCounts[$parentNode->id] ?? 0) === 0 ? 'L' : 'R';
                    $childCounts[$parentNode->id] = ($childCounts[$parentNode->id] ?? 0) + 1;

                    $node = BinaryNode::query()->create([
                        'user_id' => $partner->id,
                        'parent_id' => $parentNode->id,
                        'position' => $position,
                        'depth' => $parentNode->depth + 1,
                        'path' => trim($parentNode->path.'.'.$partner->id, '.'),
                    ]);

                    $queues[$root->id][] = $node;
                }
            }

            $demoUserIds = $this->demoUsersQuery()->pluck('id');
            $rootUsers = User::query()
                ->whereIn('id', $demoUserIds)
                ->whereNull('sponsor_id')
                ->whereHas('binaryNode', fn ($query) => $query->whereNull('parent_id'))
                ->count();
            $placedCount = BinaryNode::query()
                ->whereIn('user_id', $demoUserIds)
                ->whereNotNull('parent_id')
                ->count();

            return [
                'total_users' => $demoUserIds->count(),
                'roots' => $rootUsers,
                'placed' => $placedCount,
                'max_depth' => (int) BinaryNode::query()->whereIn('user_id', $demoUserIds)->max('depth'),
            ];
        });

        $this->info('Created demo binary tree:');
        $this->line("- total users: {$summary['total_users']}");
        $this->line("- roots: {$summary['roots']}");
        $this->line("- placed in binary tree: {$summary['placed']}");
        $this->line("- max depth: {$summary['max_depth']}");
        $this->line("- password: {$password}");
        $this->newLine();
        $this->line('Admin login:');
        $this->line("admin@safilife.test / {$password}");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, BinaryNode>  $queue
     * @param  array<int, int>  $childCounts
     */
    private function nextOpenParent(array $queue, int &$queueIndex, array $childCounts): BinaryNode
    {
        while (($childCounts[$queue[$queueIndex]->id] ?? 0) >= 2) {
            $queueIndex++;
        }

        return $queue[$queueIndex];
    }

    private function demoUser(
        int $serial,
        string $name,
        string $login,
        string $email,
        int $phoneSerial,
        ?User $sponsor,
        ?Package $package,
        string $hashedPassword,
        WalletService $walletService,
    ): User {
        $user = User::withTrashed()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'login' => $login,
                'password' => $hashedPassword,
                'role' => User::ROLE_USER,
                'account_status' => 'active',
                'status' => 'user',
                'sponsor_id' => $sponsor?->id,
                'current_package_id' => $package?->id,
                'left_pv' => 0,
                'right_pv' => 0,
                'remaining_left_pv' => 0,
                'remaining_right_pv' => 0,
                'total_pv' => 0,
                'deleted_by' => null,
                'deleted_reason' => null,
                'deleted_meta' => null,
            ],
        );

        if ($user->trashed()) {
            $user->restore();
        }

        UserProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_name' => $sponsor ? 'Demo Partner' : 'Demo Root',
                'last_name' => str_pad((string) $serial, 4, '0', STR_PAD_LEFT),
                'phone' => '+7700'.str_pad((string) $phoneSerial, 7, '0', STR_PAD_LEFT),
                'country' => 'Kazakhstan',
                'city' => 'Almaty',
                'metadata' => [
                    'source' => 'demo_tree',
                ],
            ],
        );

        $walletService->createUserWallets($user);

        return $user;
    }

    private function packageFor($packages, int $serial): ?Package
    {
        if ($serial % 20 === 0) {
            return $packages->get('ELITE');
        }

        if ($serial % 7 === 0) {
            return $packages->get('VIP');
        }

        return $packages->get('START');
    }

    private function deleteDemoUsers(): void
    {
        $demoUserIds = $this->allDemoUsersQuery()->pluck('id');

        if ($demoUserIds->isEmpty()) {
            return;
        }

        BinaryNode::withTrashed()
            ->whereIn('user_id', $demoUserIds)
            ->orderByDesc('depth')
            ->get()
            ->each
            ->forceDelete();

        if (Schema::hasTable('support_ticket_messages')) {
            DB::table('support_ticket_messages')
                ->whereIn('user_id', $demoUserIds)
                ->delete();
        }

        if (Schema::hasTable('support_tickets')) {
            $ticketsQuery = DB::table('support_tickets')
                ->whereIn('user_id', $demoUserIds);

            if (Schema::hasColumn('support_tickets', 'assigned_to')) {
                $ticketsQuery->orWhereIn('assigned_to', $demoUserIds);
            }

            $ticketsQuery->delete();
        }

        User::withTrashed()->whereIn('id', $demoUserIds)->get()->each->delete();
    }

    private function demoUsersQuery()
    {
        return User::query()
            ->where('email', 'like', 'demo_%@safilife.test');
    }

    private function allDemoUsersQuery()
    {
        return User::withTrashed()
            ->where('email', 'like', 'demo_%@safilife.test');
    }
}
