<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminActionLog;
use App\Models\ForgotPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ForgotPasswordController extends Controller
{
    public function checkEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = trim((string) $validated['email']);
        $exists = User::query()
            ->whereNull('deleted_at')
            ->where('email', $email)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'email' => ['Такой email не найден.'],
            ]);
        }

        return response()->json([
            'message' => 'Email найден.',
            'data' => [
                'email' => $email,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'phone' => ['required', 'string', 'max:50'],
        ]);

        $email = trim((string) $validated['email']);
        $phone = trim((string) $validated['phone']);
        $user = User::query()
            ->with('profile')
            ->whereNull('deleted_at')
            ->where('email', $email)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['Такой email не найден.'],
            ]);
        }

        if (! $this->phonesMatch((string) ($user->profile?->phone ?? ''), $phone)) {
            throw ValidationException::withMessages([
                'phone' => ['Номер телефона не совпадает с указанным email.'],
            ]);
        }

        $pendingRequest = ForgotPasswordRequest::query()
            ->where('user_id', $user->id)
            ->where('status', ForgotPasswordRequest::STATUS_PENDING)
            ->first();

        if ($pendingRequest) {
            return response()->json([
                'message' => 'Обращение уже передано в администрацию.',
                'data' => [
                    'request_id' => $pendingRequest->id,
                    'status' => $pendingRequest->status,
                ],
            ]);
        }

        $forgotPasswordRequest = ForgotPasswordRequest::query()->create([
            'user_id' => $user->id,
            'email' => $email,
            'phone' => $phone,
            'status' => ForgotPasswordRequest::STATUS_PENDING,
            'requested_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        AdminActionLog::query()->create([
            'admin_id' => null,
            'target_user_id' => $user->id,
            'action' => 'forgot_password_request_created',
            'reason' => null,
            'metadata' => [
                'request_id' => $forgotPasswordRequest->id,
                'email' => $email,
                'ip_address' => $request->ip(),
            ],
        ]);

        return response()->json([
            'message' => 'Обращение передано в администрацию.',
            'data' => [
                'request_id' => $forgotPasswordRequest->id,
                'status' => $forgotPasswordRequest->status,
            ],
        ], 201);
    }

    private function phonesMatch(string $storedPhone, string $submittedPhone): bool
    {
        $stored = $this->normalizePhone($storedPhone);
        $submitted = $this->normalizePhone($submittedPhone);

        return $stored !== '' && $stored === $submitted;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            return '7'.substr($digits, 1);
        }

        return $digits;
    }
}
