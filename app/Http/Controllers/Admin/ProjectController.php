<?php

namespace App\Http\Controllers\Admin;

use App\Events\ProjectChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\MergeProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\AuditEvent;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        abort_if($project->merged_into_id !== null, 404);

        $validated = $request->validated();
        $before = $project->name;

        DB::transaction(function () use ($request, $project, $validated, $before): void {
            $project = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            abort_if($project->merged_into_id !== null, 404);
            $project->update([
                'name' => $validated['name'],
                'normalized_name' => Project::normalizeName($validated['name']),
            ]);

            AuditEvent::create([
                'actor_id' => $request->user()->id,
                'action' => 'project.renamed',
                'auditable_type' => Project::class,
                'auditable_id' => $project->id,
                'metadata' => [
                    'before' => ['name' => $before],
                    'after' => ['name' => $project->name],
                ],
            ]);

            event(new ProjectChanged((int) $project->id, 'renamed'));
        });

        return redirect()->route('projects.index')->with('success', 'Project name updated.');
    }

    public function merge(MergeProjectRequest $request, Project $project): RedirectResponse
    {
        $targetId = $request->validated('target_project_id');

        DB::transaction(function () use ($request, $project, $targetId): void {
            $source = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $target = Project::query()->whereKey($targetId)->lockForUpdate()->firstOrFail();

            if ($source->merged_into_id !== null || $target->merged_into_id !== null || $source->is($target)) {
                throw ValidationException::withMessages([
                    'target_project_id' => 'Choose a different active project.',
                ]);
            }

            $source->update(['merged_into_id' => $target->id]);

            AuditEvent::create([
                'actor_id' => $request->user()->id,
                'action' => 'project.merged',
                'auditable_type' => Project::class,
                'auditable_id' => $source->id,
                'metadata' => [
                    'before' => ['merged_into_id' => null],
                    'after' => [
                        'merged_into_id' => $target->id,
                        'target_name' => $target->name,
                    ],
                ],
            ]);

            event(new ProjectChanged((int) $source->id, 'merged'));
        });

        return redirect()->route('projects.index')->with('success', 'Projects merged.');
    }
}
