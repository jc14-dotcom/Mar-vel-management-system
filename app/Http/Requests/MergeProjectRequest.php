<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MergeProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('projects.manage') ?? false;
    }

    public function rules(): array
    {
        $source = $this->route('project');

        return [
            'target_project_id' => [
                'required',
                'integer',
                Rule::exists('projects', 'id')->where(
                    fn (Builder $query) => $query
                        ->whereNull('merged_into_id')
                        ->where('id', '!=', $source instanceof Project ? $source->id : 0),
                ),
            ],
        ];
    }
}
