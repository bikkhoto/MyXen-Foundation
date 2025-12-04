<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_wallet_id' => ['nullable', 'integer', 'exists:wallets,id'],
            'to_address' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.000000001'],
            'currency' => ['nullable', 'string', 'in:MYXN,SOL'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Transfer amount must be greater than 0.',
            'to_address.required' => 'Recipient address is required.',
        ];
    }
}
