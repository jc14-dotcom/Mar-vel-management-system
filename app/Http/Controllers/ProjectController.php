<?php

namespace App\Http\Controllers;

use App\Events\ProjectChanged;
use App\Http\Requests\StoreProjectRequest;
use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()->hasPermission('vouchers.view')
            || $request->user()->hasPermission('projects.manage'),
            403,
        );

        $search = trim((string) $request->input('search', ''));

        $projects = Project::query()
            ->with('mergedInto:id,name')
            ->when(
                ! $request->user()->hasPermission('projects.manage'),
                fn ($query) => $query->whereNull('merged_into_id'),
            )
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhereHas('mergedInto', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $projectOptions = Project::query()
            ->whereNull('merged_into_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'projectOptions' => $projectOptions,
            'activeProjectCount' => $projectOptions->count(),
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $this->saveProject($request);

        return redirect()->route('projects.index')
            ->with('success', "{$project->name} is available in the project list.");
    }

    public function storeForVoucher(
        StoreProjectRequest $request,
        ?Voucher $voucher = null,
    ): RedirectResponse {
        $project = $this->saveProject($request);
        $destination = $voucher === null
            ? route('vouchers.create')
            : route('vouchers.edit', $voucher);

        return redirect($destination)->with('createdProject', [
            'id' => $project->id,
            'name' => $project->name,
        ]);
    }

    private function saveProject(StoreProjectRequest $request): Project
    {
        $name = $request->validated('name');
        $normalizedName = Project::normalizeName($name);

        return DB::transaction(function () use ($request, $name, $normalizedName): Project {
            $project = Project::query()->firstOrCreate(
                ['normalized_name' => $normalizedName],
                ['name' => $name],
            );

            if ($project->wasRecentlyCreated) {
                AuditEvent::create([
                    'actor_id' => $request->user()->id,
                    'action' => 'project.created',
                    'auditable_type' => Project::class,
                    'auditable_id' => $project->id,
                    'metadata' => ['name' => $project->name],
                ]);

                event(new ProjectChanged((int) $project->id, 'created'));
            }

            while ($project->merged_into_id !== null) {
                $project = $project->mergedInto()->firstOrFail();
            }

            return $project;
        });
    }
}
