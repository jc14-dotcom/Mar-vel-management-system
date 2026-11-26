<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrintTemplateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['nullable', 'string', 'max:255'],
            'form_number' => ['required', 'string', 'max:100'],
            'effective_date' => ['nullable', 'date_format:Y-m-d'],
            'template_revision' => ['required', 'string', 'max:40'],
            'procedural_notices' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
