<?php

namespace App\Http\Requests\Auth;

use App\Models\Package;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $branch = $this->input('branch');

        if (is_string($branch)) {
            $normalizedBranch = match (strtolower($branch)) {
                'left' => 'L',
                'right' => 'R',
                default => strtoupper($branch),
            };

            $this->merge([
                'branch' => $normalizedBranch,
            ]);
        }

        $referralCode = $this->input('referral_code')
            ?? $this->input('ref')
            ?? $this->input('sponsor_code');

        if (is_string($referralCode)) {
            $this->merge([
                'referral_code' => trim($referralCode),
            ]);
        }

        $packageId = $this->input('package_id');

        if (is_string($packageId) && ! ctype_digit($packageId)) {
            $package = Package::query()
                ->where('slug', strtolower($packageId))
                ->orWhere('code', strtoupper($packageId))
                ->first();

            if ($package) {
                $this->merge([
                    'package_id' => $package->id,
                ]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'login' => ['required', 'string', 'alpha_dash', 'max:255', Rule::unique('users', 'login')->whereNull('deleted_at')],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
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
            'referral_code' => ['nullable', 'string', 'max:255'],
            'ref' => ['nullable', 'string', 'max:255'],
            'sponsor_code' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'required_with:sponsor_id,referral_code', 'string', Rule::in(['L', 'R'])],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'branch.required_with' => 'Некорректная реферальная ссылка',
            'branch.in' => 'Некорректная ветка',
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
