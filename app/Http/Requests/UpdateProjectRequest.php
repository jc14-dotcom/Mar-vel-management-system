<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('projects.manage') ?? false;
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

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('name')) {
                    return;
                }

                $project = $this->route('project');
                $normalizedName = Project::normalizeName($this->string('name')->value());
                $alreadyExists = Project::query()
                    ->where('normalized_name', $normalizedName)
                    ->whereKeyNot($project->id)
                    ->exists();

                if ($alreadyExists) {
                    $validator->errors()->add('name', 'The project name has already been taken.');
                }
            },
        ];
    }
}
