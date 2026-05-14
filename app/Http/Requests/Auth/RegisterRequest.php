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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'sponsor_id' => ['nullable', 'integer', 'exists:users,id'],
            'branch' => ['nullable', 'string', Rule::in(['L', 'R'])],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
        ];
    }
}
