<?php

namespace App\Actions\Vouchers;

use App\Events\VoucherChanged;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

class CancelVoucherAction
{
    public function handle(User $actor, Voucher $voucher, int $recordVersion, string $reason): Voucher
    {
        return DB::transaction(function () use ($actor, $voucher, $recordVersion, $reason): Voucher {
            $voucher = Voucher::query()
                ->whereKey($voucher->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                $voucher->record_version !== $recordVersion,
                409,
                'This voucher has changed. Reload it before cancelling.',
            );
            abort_if($voucher->status === 'cancelled', 409, 'This voucher is already cancelled.');
            abort_unless(
                in_array($voucher->status, ['draft', 'printed', 'reprint_required'], true),
                409,
                'This voucher cannot be cancelled.',
            );

            $before = [
                'voucher_number' => $voucher->voucher_number,
                'record_version' => $voucher->record_version,
                'status' => $voucher->status,
            ];

            $voucher->update(['status' => 'cancelled']);
            $voucher->increment('record_version');

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'voucher.cancelled',
                'auditable_type' => Voucher::class,
                'auditable_id' => $voucher->id,
                'metadata' => [
                    'before' => $before,
                    'after' => [
                        'voucher_number' => $voucher->voucher_number,
                        'record_version' => $voucher->record_version,
                        'status' => $voucher->status,
                    ],
                    'reason' => $reason,
                ],
            ]);

            event(new VoucherChanged(
                (int) $voucher->id,
                (int) $voucher->record_version,
                'cancelled',
            ));

            return $voucher;
        }, attempts: 5);
    }
}
