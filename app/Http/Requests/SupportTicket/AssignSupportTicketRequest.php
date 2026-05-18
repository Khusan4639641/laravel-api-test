<?php

namespace App\Http\Requests\SupportTicket;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('assignee_id') && ! $this->has('assigned_to')) {
            $this->merge([
                'assigned_to' => $this->input('assignee_id'),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assigned_to' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', [
                    User::ROLE_SUPPORT,
                    User::ROLE_SUPER_ADMIN,
                ])),
            ],
        ];
    }
}
