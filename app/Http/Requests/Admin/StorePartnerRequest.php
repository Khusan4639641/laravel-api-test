<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $roles = array_keys((array) config('role_permissions.roles', []));

        return [
            'name' => ['required', 'string', 'max:255'],
            'login' => ['required', 'string', 'max:255', Rule::unique('users', 'login')->whereNull('deleted_at')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'phone' => ['required', 'string', 'min:6', 'max:32', $this->uniqueActivePhoneRule()],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'sponsor_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->whereNull('deleted_at')
                    ->where('account_status', 'active')
                    ->whereIn('role', [User::ROLE_USER, User::ROLE_SUPER_ADMIN]),
            ],
            'branch' => ['nullable', 'string', Rule::in(['left', 'right', 'L', 'R'])],
            'package_id' => ['nullable', 'integer', Rule::exists('packages', 'id')],
            'pay_referral_bonus' => ['sometimes', 'boolean'],
            'role' => ['nullable', 'string', Rule::in($roles ?: [
                User::ROLE_USER,
                User::ROLE_SUPPORT,
                User::ROLE_ADMIN,
                User::ROLE_ACCOUNTANT,
                User::ROLE_SUPER_ADMIN,
            ])],
        ];
    }

    private function uniqueActivePhoneRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $phone = trim((string) $value);

            if ($phone === '') {
                return;
            }

            if (UserProfile::query()
                ->where('phone', $phone)
                ->whereHas('user', fn ($query) => $query->activeAccount())
                ->exists()
            ) {
                $fail('The phone has already been taken.');
            }
        };
    }
}
