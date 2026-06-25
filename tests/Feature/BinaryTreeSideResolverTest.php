<?php

namespace Tests\Feature;

use App\Models\BinaryNode;
use App\Models\User;
use App\Services\BinaryTreeSideResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BinaryTreeSideResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_descendant_under_root_left_left_is_left(): void
    {
        [$root, , , $leftLeft] = $this->tree();

        $this->assertSame('left', $this->resolver()->getRootSideForDescendant($root->id, $leftLeft->id));
    }

    public function test_descendant_under_root_left_right_is_left(): void
    {
        [$root, , , , $leftRight] = $this->tree();

        $this->assertSame('left', $this->resolver()->getRootSideForDescendant($root->id, $leftRight->id));
    }

    public function test_descendant_under_root_left_right_left_is_left(): void
    {
        [$root, , , , , $leftRightLeft] = $this->tree();

        $this->assertSame('left', $this->resolver()->getRootSideForDescendant($root->id, $leftRightLeft->id));
    }

    public function test_descendant_under_root_right_left_is_right(): void
    {
        [$root, , , , , , $rightLeft] = $this->tree();

        $this->assertSame('right', $this->resolver()->getRootSideForDescendant($root->id, $rightLeft->id));
    }

    public function test_descendant_under_root_right_right_left_is_right(): void
    {
        [$root, , , , , , , , $rightRightLeft] = $this->tree();

        $this->assertSame('right', $this->resolver()->getRootSideForDescendant($root->id, $rightRightLeft->id));
    }

    public function test_root_itself_returns_null(): void
    {
        [$root] = $this->tree();

        $this->assertNull($this->resolver()->getRootSideForDescendant($root->id, $root->id));
    }

    public function test_node_outside_root_subtree_returns_null(): void
    {
        [$root] = $this->tree();
        $outsideRoot = User::factory()->create();
        $outsideChild = User::factory()->create();
        $outsideRootNode = $this->node($outsideRoot);
        $this->node($outsideChild, $outsideRootNode, 'L');

        $this->assertNull($this->resolver()->getRootSideForDescendant($root->id, $outsideChild->id));
    }

    private function resolver(): BinaryTreeSideResolver
    {
        return app(BinaryTreeSideResolver::class);
    }

    /**
     * @return array<int, User>
     */
    private function tree(): array
    {
        $root = User::factory()->create();
        $left = User::factory()->create();
        $right = User::factory()->create();
        $leftLeft = User::factory()->create();
        $leftRight = User::factory()->create();
        $leftRightLeft = User::factory()->create();
        $rightLeft = User::factory()->create();
        $rightRight = User::factory()->create();
        $rightRightLeft = User::factory()->create();

        $rootNode = $this->node($root);
        $leftNode = $this->node($left, $rootNode, 'L');
        $rightNode = $this->node($right, $rootNode, 'R');
        $this->node($leftLeft, $leftNode, 'L');
        $leftRightNode = $this->node($leftRight, $leftNode, 'R');
        $this->node($leftRightLeft, $leftRightNode, 'L');
        $this->node($rightLeft, $rightNode, 'L');
        $rightRightNode = $this->node($rightRight, $rightNode, 'R');
        $this->node($rightRightLeft, $rightRightNode, 'L');

        return [$root, $left, $right, $leftLeft, $leftRight, $leftRightLeft, $rightLeft, $rightRight, $rightRightLeft];
    }

    private function node(User $user, ?BinaryNode $parent = null, ?string $position = null): BinaryNode
    {
        return BinaryNode::query()->create([
            'user_id' => $user->id,
            'parent_id' => $parent?->id,
            'position' => $position,
            'depth' => $parent ? $parent->depth + 1 : 0,
            'path' => trim(($parent?->path ? $parent->path.'.' : '').$user->id, '.'),
            'is_active' => true,
        ]);
    }
}
