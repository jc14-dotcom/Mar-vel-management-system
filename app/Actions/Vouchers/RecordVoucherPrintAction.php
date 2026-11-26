<?php

namespace App\Actions\Vouchers;

use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRevision;
use Illuminate\Support\Facades\DB;

class RecordVoucherPrintAction
{
    public function __construct(private BuildVoucherRevisionSnapshotAction $buildSnapshot) {}

    public function handle(User $actor, Voucher $voucher): VoucherRevision
    {
        return DB::transaction(function () use ($actor, $voucher): VoucherRevision {
            $voucher = Voucher::query()
                ->whereKey($voucher->id)
                ->lockForUpdate()
                ->with('items')
                ->firstOrFail();

            if ($voucher->status === 'draft') {
                $revision = $voucher->revisions()->create([
                    'revision_number' => (int) $voucher->revisions()->max('revision_number') + 1,
                    'snapshot' => $this->buildSnapshot->handle($voucher),
                    'actor_id' => $actor->id,
                    'printed_at' => now(),
                ]);
                $previousStatus = 'draft';
            } elseif ($voucher->status === 'reprint_required') {
                $revision = $voucher->revisions()
                    ->whereNull('printed_at')
                    ->whereNull('superseded_at')
                    ->orderByDesc('revision_number')
                    ->lockForUpdate()
                    ->first();

                abort_if($revision === null, 409, 'A pending revision is required before reprinting.');

                $previousRevision = $voucher->revisions()
                    ->whereNotNull('printed_at')
                    ->whereNull('superseded_at')
                    ->orderByDesc('revision_number')
                    ->lockForUpdate()
                    ->first();

                abort_if($previousRevision === null, 409, 'A previously printed revision is required.');

                $printedAt = now();
                $revision->update(['printed_at' => $printedAt]);
                $previousRevision->update(['superseded_at' => $printedAt]);
                $previousStatus = 'reprint_required';
            } else {
                abort(409, 'Only draft or reprint-required vouchers can be printed.');
            }

            $voucher->update(['status' => 'printed']);
            $voucher->increment('record_version');

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'voucher.printed',
                'auditable_type' => Voucher::class,
                'auditable_id' => $voucher->id,
                'metadata' => [
                    'before' => ['status' => $previousStatus],
                    'after' => [
                        'status' => 'printed',
                        'revision_number' => $revision->revision_number,
                    ],
                ],
            ]);

            return $revision;
        }, attempts: 5);
    }
}
