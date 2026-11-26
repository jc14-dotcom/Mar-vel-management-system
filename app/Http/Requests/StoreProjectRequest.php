<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Voucher;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($user->hasPermission('projects.manage')) {
            return true;
        }

        if ($this->routeIs('vouchers.projects.store')) {
            return $user->hasPermission('vouchers.create');
        }

        if ($this->route('voucher') instanceof Voucher) {
            return $user->hasPermission('vouchers.edit');
        }

        return $user->hasPermission('vouchers.create') && $user->hasPermission('vouchers.view');
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => Project::normalizeDisplayName($name)]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
