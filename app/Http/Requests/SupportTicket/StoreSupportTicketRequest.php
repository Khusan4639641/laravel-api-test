<?php

namespace App\Http\Requests\SupportTicket;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'required_without:file', 'string'],
            'file' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt'],
            'priority' => ['nullable', 'string', 'max:255'],
        ];
    }
}
