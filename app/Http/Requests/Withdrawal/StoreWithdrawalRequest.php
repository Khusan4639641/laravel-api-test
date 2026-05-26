<?php

namespace App\Http\Requests\Withdrawal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWithdrawalRequest extends FormRequest
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
        if ($this->has('method') && ! $this->has('payment_method')) {
            $this->merge([
                'payment_method' => $this->input('method'),
            ]);
        }

        $paymentMethod = $this->input('payment_method');

        if (is_string($paymentMethod)) {
            $this->merge([
                'payment_method' => match ($paymentMethod) {
                    'card' => 'card_account',
                    'bank_account', 'business_account' => 'ip_account',
                    default => $paymentMethod,
                },
            ]);
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
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'string', Rule::in(['ip_account', 'card_account'])],
            'payment_details' => ['nullable', 'array'],
        ];
    }
}
