<?php

namespace App\Services;

use App\Models\BinaryNode;
use App\Models\User;

class BinaryTreeService
{
    public function placeUser(User $user, ?User $sponsor = null, ?string $preferredPosition = null): BinaryNode
    {
        // TODO: Validate user eligibility and requested placement position.
        // TODO: Find direct or spillover parent node for the new user.
        // TODO: Create and return the user's binary node.
    }

    public function findSpilloverPosition(User $sponsor, ?string $preferredPosition = null): ?BinaryNode
    {
        // TODO: Traverse sponsor binary subtree and find the nearest free left/right position.
    }

    public function hasFreePosition(BinaryNode $node, ?string $position = null): bool
    {
        // TODO: Check whether the node has an available left or right child slot.
    }
}
