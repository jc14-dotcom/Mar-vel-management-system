<?php

namespace App\Actions\Vouchers;

use App\Events\VoucherChanged;
use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateVoucherAction
{
    public function __construct(
        private CalculateVoucherTotal $calculateVoucherTotal,
        private BuildVoucherRevisionSnapshotAction $buildSnapshot,
    ) {}

    /**
     * @param  array{
     *     voucher_date: string,
     *     payee: string,
     *     project_id: int,
     *     scope_of_work?: ?string,
     *     expense_category?: ?string,
     *     payment_type: string,
     *     prepared_by?: string,
     *     items: array<int, array{description: string, amount: string, line_type: string}>,
     *     record_version: int|string,
     *     reason?: string
     * }  $data
     */
    public function handle(User $actor, Voucher $voucher, array $data): Voucher
    {
        return DB::transaction(function () use ($actor, $voucher, $data): Voucher {
            $voucher = Voucher::query()
                ->whereKey($voucher->id)
                ->lockForUpdate()
                ->with('items')
                ->firstOrFail();

            abort_if(
                $voucher->record_version !== (int) $data['record_version'],
                409,
                'This voucher has changed. Reload it before editing.',
            );
            $previousStatus = $voucher->status;
            $isPostPrintEdit = in_array($previousStatus, ['printed', 'reprint_required'], true);

            abort_if($previousStatus === 'cancelled', 409, 'Cancelled vouchers cannot be edited.');
            abort_unless($previousStatus === 'draft' || $isPostPrintEdit, 409, 'This voucher cannot be edited.');
            abort_if(
                $isPostPrintEdit && trim($data['reason'] ?? '') === '',
                409,
                'A reason is required for post-print edits.',
            );

            $before = $this->auditData($voucher);
            $project = Project::query()
                ->whereKey($data['project_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $isExistingProject = (int) $project->id === (int) $voucher->project_id;

            if ($project->merged_into_id !== null && ! $isExistingProject) {
                throw ValidationException::withMessages([
                    'project_id' => 'Select an active project.',
                ]);
            }

            $projectNameSnapshot = $isExistingProject
                ? $voucher->project_name_snapshot
                : $project->name;

            $voucher->update([
                'voucher_date' => $data['voucher_date'],
                'payee' => $data['payee'],
                'project_id' => $project->id,
                'project_name_snapshot' => $projectNameSnapshot,
                'scope_of_work' => $data['scope_of_work'] ?? null,
                'expense_category' => $data['expense_category'] ?? null,
                'payment_type' => $data['payment_type'],
                'prepared_by' => $data['prepared_by'] ?? $voucher->prepared_by,
                'total_amount' => $this->calculateVoucherTotal->handle($data['items']),
                'status' => $isPostPrintEdit ? 'reprint_required' : $previousStatus,
            ]);

            VoucherItem::query()->whereBelongsTo($voucher)->delete();
            $voucher->items()->createMany($this->itemRecords($data['items']));
            $voucher->load('items');

            if ($isPostPrintEdit) {
                $snapshot = $this->buildSnapshot->handle($voucher);
                $reason = $data['reason'];

                if ($previousStatus === 'printed') {
                    $voucher->revisions()->create([
                        'revision_number' => (int) $voucher->revisions()->max('revision_number') + 1,
                        'snapshot' => $snapshot,
                        'reason' => $reason,
                        'actor_id' => $actor->id,
                    ]);
                } else {
                    $pendingRevision = $voucher->revisions()
                        ->whereNull('printed_at')
                        ->whereNull('superseded_at')
                        ->orderByDesc('revision_number')
                        ->lockForUpdate()
                        ->first();

                    abort_if($pendingRevision === null, 409, 'A pending revision is required before editing.');
                    $pendingRevision->update([
                        'snapshot' => $snapshot,
                        'reason' => $reason,
                        'actor_id' => $actor->id,
                    ]);
                }
            }

            $voucher->increment('record_version');

            $metadata = [
                'before' => $before,
                'after' => $this->auditData($voucher),
            ];

            if ($isPostPrintEdit) {
                $metadata['reason'] = $data['reason'];
            }

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'voucher.updated',
                'auditable_type' => Voucher::class,
                'auditable_id' => $voucher->id,
                'metadata' => $metadata,
            ]);

            event(new VoucherChanged(
                (int) $voucher->id,
                (int) $voucher->record_version,
                'updated',
            ));

            return $voucher;
        }, attempts: 5);
    }

    /**
     * @param  array<int, array{description: string, amount: string, line_type: string}>  $items
     * @return array<int, array{description: string, amount: string, line_type: string, sort_order: int}>
     */
    private function itemRecords(array $items): array
    {
        $records = [];

        foreach (array_values($items) as $sortOrder => $item) {
            $records[] = [
                'description' => $item['description'],
                'amount' => $item['amount'],
                'line_type' => $item['line_type'],
                'sort_order' => $sortOrder,
            ];
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditData(Voucher $voucher): array
    {
        return [
            'voucher_number' => $voucher->voucher_number,
            'voucher_date' => $voucher->voucher_date->toDateString(),
            'payee' => $voucher->payee,
            'project_id' => $voucher->project_id,
            'project_name_snapshot' => $voucher->project_name_snapshot,
            'scope_of_work' => $voucher->scope_of_work,
            'expense_category' => $voucher->expense_category,
            'payment_type' => $voucher->payment_type,
            'created_by' => $voucher->created_by,
            'prepared_by' => $voucher->prepared_by,
            'total_amount' => $voucher->total_amount,
            'record_version' => $voucher->record_version,
            'status' => $voucher->status,
            'items' => $voucher->items->map(fn (VoucherItem $item): array => [
                'description' => $item->description,
                'amount' => $item->amount,
                'line_type' => $item->line_type,
                'sort_order' => $item->sort_order,
            ])->all(),
        ];
    }
}
