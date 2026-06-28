<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ForgotPasswordRequestResource;
use App\Models\AdminActionLog;
use App\Models\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ForgotPasswordRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', ForgotPasswordRequest::STATUS_PENDING);
        $search = trim((string) $request->query('search', ''));
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 50);

        $requests = ForgotPasswordRequest::query()
            ->with(['user.profile', 'resolvedBy.profile'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search): void {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('login', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhereKey($search);
                        });
                });
            })
            ->latest('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => ForgotPasswordRequestResource::collection($requests)->resolve($request),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
            'links' => [
                'next' => $requests->nextPageUrl(),
                'prev' => $requests->previousPageUrl(),
            ],
        ]);
    }

    public function show(ForgotPasswordRequest $forgotPasswordRequest): JsonResponse
    {
        return response()->json([
            'data' => ForgotPasswordRequestResource::make(
                $forgotPasswordRequest->load(['user.profile', 'resolvedBy.profile'])
            ),
        ]);
    }

    public function resetPassword(Request $request, ForgotPasswordRequest $forgotPasswordRequest): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($forgotPasswordRequest->status !== ForgotPasswordRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'request' => ['Обращение уже обработано.'],
            ]);
        }

        $result = DB::transaction(function () use ($request, $forgotPasswordRequest, $validated): ForgotPasswordRequest {
            $lockedRequest = ForgotPasswordRequest::query()
                ->whereKey($forgotPasswordRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== ForgotPasswordRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'request' => ['Обращение уже обработано.'],
                ]);
            }

            $user = $lockedRequest->user()->lockForUpdate()->firstOrFail();

            $user->forceFill([
                'password' => Hash::make((string) $validated['password']),
            ])->save();

            $user->tokens()->delete();

            $lockedRequest->forceFill([
                'status' => ForgotPasswordRequest::STATUS_RESOLVED,
                'resolved_at' => now(),
                'resolved_by' => $request->user()?->id,
            ])->save();

            AdminActionLog::query()->create([
                'admin_id' => $request->user()?->id,
                'target_user_id' => $user->id,
                'action' => 'forgot_password_reset_completed',
                'reason' => null,
                'metadata' => [
                    'request_id' => $lockedRequest->id,
                ],
            ]);

            return $lockedRequest->refresh();
        });

        return response()->json([
            'message' => 'Новый пароль установлен.',
            'data' => [
                'request_id' => $result->id,
                'user_id' => $result->user_id,
                'status' => $result->status,
            ],
        ]);
    }

    public function cancel(Request $request, ForgotPasswordRequest $forgotPasswordRequest): JsonResponse
    {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', Rule::in([ForgotPasswordRequest::STATUS_CANCELLED])],
        ]);

        if ($forgotPasswordRequest->status !== ForgotPasswordRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'request' => ['Обращение уже обработано.'],
            ]);
        }

        $forgotPasswordRequest->forceFill([
            'status' => ForgotPasswordRequest::STATUS_CANCELLED,
            'resolved_at' => now(),
            'resolved_by' => $request->user()?->id,
            'admin_note' => $validated['admin_note'] ?? null,
        ])->save();

        return response()->json([
            'message' => 'Обращение отменено.',
            'data' => ForgotPasswordRequestResource::make($forgotPasswordRequest->load(['user.profile', 'resolvedBy.profile'])),
        ]);
    }
}
