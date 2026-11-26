<?php

namespace App\Http\Controllers;

use App\Actions\Vouchers\CancelVoucherAction;
use App\Actions\Vouchers\CreateVoucherAction;
use App\Actions\Vouchers\UpdateVoucherAction;
use App\Http\Requests\CancelVoucherRequest;
use App\Http\Requests\SaveVoucherRequest;
use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VoucherController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['draft', 'printed', 'reprint_required', 'cancelled'])],
        ]);
        $search = trim($filters['search'] ?? '');
        $status = $filters['status'] ?? null;

        $vouchers = Voucher::query()
            ->select([
                'id',
                'voucher_number',
                'voucher_date',
                'payee',
                'project_name_snapshot',
                'expense_category',
                'prepared_by',
                'total_amount',
                'status',
                'record_version',
                'created_at',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('voucher_number', 'like', "%{$search}%")
                        ->orWhere('payee', 'like', "%{$search}%")
                        ->orWhere('project_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('expense_category', 'like', "%{$search}%");
                });
            })
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Vouchers/Index', [
            'vouchers' => $vouchers,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Vouchers/Form', [
            'voucher' => null,
            'projects' => $this->projectOptions(),
            'preparedBy' => $request->user()->name,
            'today' => now()->toDateString(),
        ]);
    }

    public function edit(Voucher $voucher): Response
    {
        $voucher->load(['items', 'creator']);

        return Inertia::render('Vouchers/Form', [
            'voucher' => [
                'id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'voucher_date' => $voucher->voucher_date->toDateString(),
                'payee' => $voucher->payee,
                'project_id' => $voucher->project_id,
                'project_name_snapshot' => $voucher->project_name_snapshot,
                'scope_of_work' => $voucher->scope_of_work,
                'expense_category' => $voucher->expense_category,
                'payment_type' => $voucher->payment_type,
                'created_by' => $voucher->creator->name,
                'prepared_by' => $voucher->prepared_by,
                'total_amount' => $voucher->total_amount,
                'status' => $voucher->status,
                'record_version' => $voucher->record_version,
                'items' => $voucher->items->map(fn ($item): array => [
                    'description' => $item->description,
                    'amount' => $item->amount,
                    'line_type' => $item->line_type,
                ])->all(),
            ],
            'projects' => $this->projectOptions($voucher),
            'preparedBy' => $voucher->prepared_by,
            'today' => now()->toDateString(),
        ]);
    }

    public function history(Voucher $voucher): Response
    {
        $revisions = $voucher->revisions()
            ->with('actor:id,name')
            ->orderByDesc('revision_number')
            ->get()
            ->map(fn ($revision): array => [
                'id' => $revision->id,
                'revision_number' => $revision->revision_number,
                'snapshot' => $revision->snapshot,
                'reason' => $revision->reason,
                'printed_at' => $revision->printed_at?->toIso8601String(),
                'superseded_at' => $revision->superseded_at?->toIso8601String(),
                'actor' => ['name' => $revision->actor->name],
            ]);

        $events = $voucher->auditEvents()
            ->with('actor:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AuditEvent $event): array => [
                'id' => $event->id,
                'action' => $event->action,
                'metadata' => $event->metadata ?? [],
                'created_at' => $event->created_at->toIso8601String(),
                'actor' => ['name' => $event->actor?->name ?? 'Unavailable account'],
            ]);

        return Inertia::render('Vouchers/History', [
            'voucher' => [
                'id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'status' => $voucher->status,
            ],
            'revisions' => $revisions,
            'events' => $events,
        ]);
    }

    public function store(SaveVoucherRequest $request, CreateVoucherAction $create): RedirectResponse
    {
        $create->handle($request->user(), $request->validated());

        return redirect()->route('vouchers.index')->with('success', 'Voucher saved.');
    }

    public function update(
        SaveVoucherRequest $request,
        Voucher $voucher,
        UpdateVoucherAction $update,
    ): RedirectResponse {
        $update->handle($request->user(), $voucher, $request->validated());

        return redirect()->route('vouchers.index')->with('success', 'Voucher updated.');
    }

    public function cancel(
        CancelVoucherRequest $request,
        Voucher $voucher,
        CancelVoucherAction $cancel,
    ): RedirectResponse {
        $data = $request->validated();
        $cancel->handle($request->user(), $voucher, (int) $data['record_version'], $data['reason']);

        return redirect()->route('vouchers.index')->with('success', 'Voucher cancelled.');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function projectOptions(?Voucher $voucher = null): array
    {
        $projects = Project::query()
            ->whereNull('merged_into_id')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
            ]);

        if ($voucher !== null && ! $projects->contains('id', $voucher->project_id)) {
            $projects->prepend([
                'id' => $voucher->project_id,
                'name' => $voucher->project_name_snapshot,
            ]);
        }

        return $projects->values()->all();
    }
}
