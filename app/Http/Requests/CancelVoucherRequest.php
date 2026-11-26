<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('vouchers.cancel') ?? false;
    }

    public function rules(): array
    {
        return [
            'record_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
