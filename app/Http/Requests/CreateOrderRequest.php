<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enum\OrderSide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class CreateOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'symbol' => ['required', 'string', 'max:10'],
            'side' => ['required', new Enum(OrderSide::class)],
            'price' => ['required', 'numeric', 'min:0.00000001'],
            'amount' => ['required', 'numeric', 'min:0.00000001'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'symbol.required' => 'Trading symbol is required',
            'side.required' => 'Order side (buy/sell) is required',
            'price.required' => 'Price is required',
            'price.min' => 'Price must be greater than 0',
            'amount.required' => 'Amount is required',
            'amount.min' => 'Amount must be greater than 0',
        ];
    }
}
