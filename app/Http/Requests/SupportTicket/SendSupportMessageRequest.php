<?php

namespace App\Http\Requests\SupportTicket;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendSupportMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reply') && ! $this->has('message')) {
            $this->merge([
                'message' => $this->input('reply'),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['nullable', 'required_without:file', 'string'],
            'file' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt'],
        ];
    }
}
