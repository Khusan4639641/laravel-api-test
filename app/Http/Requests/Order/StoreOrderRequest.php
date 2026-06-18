<?php

namespace App\Http\Requests\Order;

use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
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
        if ($this->has('product_id') && ! $this->has('items')) {
            $this->merge([
                'items' => [
                    [
                        'product_id' => $this->input('product_id'),
                        'quantity' => $this->input('quantity', 1),
                    ],
                ],
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'gt:0'],
            'payment_strategy' => ['sometimes', 'nullable', 'string', Rule::in([
                Order::PAYMENT_STRATEGY_CARD_100,
                Order::PAYMENT_STRATEGY_CARD_50_DEPOSIT_50,
                Order::PAYMENT_STRATEGY_DEPOSIT_100,
            ])],
            'shipping_address' => ['nullable', 'array'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:6', 'max:32'],
            'city' => ['required', 'string', 'max:120'],
            'delivery_address' => ['required', 'string', 'min:5', 'max:500'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
