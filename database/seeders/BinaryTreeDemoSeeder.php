<?php

namespace Database\Seeders;

use App\Models\BinaryNode;
use App\Models\User;
use Illuminate\Database\Seeder;

class BinaryTreeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()
            ->whereIn('login', ['aidar', 'erlan', 'alisa', 'gulnara', 'alexey', 'dinara', 'timur', 'madina', 'nurlan'])
            ->get()
            ->keyBy('login');

        if (! $users->has('aidar')) {
            return;
        }

        $root = BinaryNode::query()->updateOrCreate(
            ['user_id' => $users->get('aidar')->id],
            [
                'parent_id' => null,
                'position' => null,
                'depth' => 0,
                'path' => (string) $users->get('aidar')->id,
            ]
        );

        $nodes = ['aidar' => $root];

        $placements = [
            ['login' => 'erlan', 'parent' => 'aidar', 'position' => 'L'],
            ['login' => 'alisa', 'parent' => 'aidar', 'position' => 'R'],
            ['login' => 'gulnara', 'parent' => 'erlan', 'position' => 'L'],
            ['login' => 'alexey', 'parent' => 'erlan', 'position' => 'R'],
            ['login' => 'dinara', 'parent' => 'alisa', 'position' => 'L'],
            ['login' => 'timur', 'parent' => 'alisa', 'position' => 'R'],
            ['login' => 'madina', 'parent' => 'gulnara', 'position' => 'L'],
            ['login' => 'nurlan', 'parent' => 'gulnara', 'position' => 'R'],
        ];

        foreach ($placements as $placement) {
            $user = $users->get($placement['login']);
            $parent = $nodes[$placement['parent']] ?? null;

            if (! $user || ! $parent) {
                continue;
            }

            $nodes[$placement['login']] = BinaryNode::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'parent_id' => $parent->id,
                    'position' => $placement['position'],
                    'depth' => $parent->depth + 1,
                    'path' => $parent->path.'.'.$user->id,
                ]
            );
        }
    }
}
