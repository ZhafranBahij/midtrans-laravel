<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransactionSnapRedirectRequest extends FormRequest
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
            'user.name' =>'required|string|max:255',
            'user.mobile_number' =>'required|string|max:20',
            'user.email' =>'required|string|email|max:255',
            'user.address' =>'required|string|max:255',

            'transactions' => 'required|array',
            'transactions.*.name' =>'required|string|max:255',
            'transactions.*.quantity' =>'required|integer',
            'transactions.*.price' =>'required|integer',
            
            'voucher.name' => 'nullable|string|max:255',
            'voucher.discount_percentange' => 'nullable|integer|max:100',
        ];
    }
}
