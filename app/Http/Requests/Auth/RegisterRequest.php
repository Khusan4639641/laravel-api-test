<?php

namespace App\Http\Requests\Auth;

use App\Models\Package;
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
            'login' => ['required', 'string', 'alpha_dash', 'max:255', 'unique:users,login'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'min:6', 'max:32'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'sponsor_id' => ['nullable', 'integer', 'exists:users,id'],
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
}
