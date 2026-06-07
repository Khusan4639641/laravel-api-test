<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'user' => UserResource::make(
                $request->user()->load(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode'])
            ),
        ]);
    }

    public function avatar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $oldAvatarPath = $user->avatar_path ?: $user->profile?->avatar_path;
        $path = $validated['avatar']->store('avatars', 'public');

        if (is_string($oldAvatarPath) && str_starts_with($oldAvatarPath, 'avatars/')) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        $user->forceFill([
            'avatar_path' => $path,
        ])->save();

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            ['avatar_path' => $path],
        );

        return response()->json([
            'user' => UserResource::make(
                $user->refresh()->load(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode'])->loadCount('referrals')
            ),
            'avatar_path' => $path,
            'avatar_url' => asset(Storage::url($path)),
        ]);
    }
}
