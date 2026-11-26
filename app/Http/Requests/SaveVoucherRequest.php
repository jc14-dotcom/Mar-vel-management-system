<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Voucher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $voucher = $this->route('voucher');
        $permission = $voucher instanceof Voucher ? 'vouchers.edit' : 'vouchers.create';

        return $user?->hasPermission($permission) ?? false;
    }

    public function rules(): array
    {
        return [
            'record_version' => [
                $this->route('voucher') instanceof Voucher ? 'required' : 'prohibited',
                'integer',
                'min:1',
            ],
            'reason' => [
                $this->route('voucher') instanceof Voucher
                    && in_array($this->route('voucher')->status, ['printed', 'reprint_required'], true)
                    ? 'required'
                    : 'nullable',
                'string',
                'max:2000',
            ],
            'voucher_date' => ['required', 'date_format:Y-m-d'],
            'payee' => ['required', 'string', 'max:255'],
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'scope_of_work' => ['nullable', 'string', 'max:2000'],
            'expense_category' => ['nullable', 'string', 'max:2000'],
            'payment_type' => ['required', Rule::in(['full', 'partial'])],
            'prepared_by' => ['sometimes', 'required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'array:description,amount,line_type'],
            'items.*.description' => ['required', 'string', 'max:2000'],
            'items.*.amount' => [
                'required',
                'string',
                'regex:/\\A\\d{1,10}(?:\\.\\d{1,2})?\\z/',
                'gt:0',
            ],
            'items.*.line_type' => ['required', Rule::in(['expense', 'deduction'])],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('project_id')) {
                    return;
                }

                $project = Project::query()->find($this->integer('project_id'));

                if ($project === null) {
                    return;
                }

                $voucher = $this->route('voucher');
                $isExistingVoucherProject = $voucher instanceof Voucher
                    && (int) $voucher->project_id === (int) $project->id;

                if ($project->merged_into_id !== null && ! $isExistingVoucherProject) {
                    $validator->errors()->add('project_id', 'Select an active project.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.*.amount.regex' => 'Each amount must be positive with no more than two decimal places.',
            'items.*.amount.gt' => 'Each amount must be positive with no more than two decimal places.',
            'items.*.line_type.in' => 'Each line must be an expense or deduction.',
        ];
    }
}
