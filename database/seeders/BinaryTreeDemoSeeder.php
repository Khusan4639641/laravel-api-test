<?php

namespace Database\Seeders;

use App\Models\BinaryNode;
use App\Models\User;
use Illuminate\Database\Seeder;

class BinaryTreeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->whereIn('login', ['aidar', 'erlan', 'alisa', 'gulnara', 'alexey', 'dinara'])
            ->get()
            ->keyBy('login');

        $aidar = $users->get('aidar');

        if (! $aidar) {
            return;
        }

        $root = BinaryNode::query()->updateOrCreate(
            ['user_id' => $aidar->id],
            ['parent_id' => null, 'position' => null, 'depth' => 0, 'path' => (string) $aidar->id]
        );

        $placements = [
            ['login' => 'erlan', 'parent' => $root, 'position' => 'L'],
            ['login' => 'alisa', 'parent' => $root, 'position' => 'R'],
        ];

        foreach ($placements as $placement) {
            $user = $users->get($placement['login']);

            if (! $user) {
                continue;
            }

            BinaryNode::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'parent_id' => $placement['parent']->id,
                    'position' => $placement['position'],
                    'depth' => 1,
                    'path' => $placement['parent']->path.'.'.$user->id,
                ]
            );
        }

        $erlanNode = BinaryNode::query()->where('user_id', $users->get('erlan')?->id)->first();
        $alisaNode = BinaryNode::query()->where('user_id', $users->get('alisa')?->id)->first();

        foreach ([
            ['login' => 'gulnara', 'parent' => $erlanNode, 'position' => 'L'],
            ['login' => 'alexey', 'parent' => $erlanNode, 'position' => 'R'],
            ['login' => 'dinara', 'parent' => $alisaNode, 'position' => 'L'],
        ] as $placement) {
            $user = $users->get($placement['login']);
            $parent = $placement['parent'];

            if (! $user || ! $parent) {
                continue;
            }

            BinaryNode::query()->updateOrCreate(
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
