<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
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
            'login' => ['required', 'string', 'max:255', Rule::unique('users', 'login')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'sponsor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'branch' => ['nullable', 'string', Rule::in(['left', 'right', 'L', 'R'])],
            'role' => ['nullable', 'string', Rule::in($roles ?: [
                User::ROLE_USER,
                User::ROLE_SUPPORT,
                User::ROLE_ADMIN,
                User::ROLE_SUPER_ADMIN,
            ])],
        ];
    }
}
