<?php

namespace App\Actions\Vouchers;

use App\Events\VoucherChanged;
use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherItem;
use App\Models\VoucherNumberSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateVoucherAction
{
    public function __construct(private CalculateVoucherTotal $calculateVoucherTotal) {}

    /**
     * @param  array{
     *     voucher_date: string,
     *     payee: string,
     *     project_id: int,
     *     scope_of_work?: ?string,
     *     expense_category?: ?string,
     *     payment_type: string,
     *     prepared_by?: string,
     *     items: array<int, array{description: string, amount: string, line_type: string}>
     * }  $data
     */
    public function handle(User $creator, array $data): Voucher
    {
        return DB::transaction(function () use ($creator, $data): Voucher {
            $project = Project::query()
                ->whereKey($data['project_id'])
                ->whereNull('merged_into_id')
                ->lockForUpdate()
                ->first();

            if ($project === null) {
                throw ValidationException::withMessages([
                    'project_id' => 'Select an active project.',
                ]);
            }

            $entryYear = (int) now()->format('Y');
            $timestamp = now();
            VoucherNumberSequence::query()->insertOrIgnore([
                'year' => $entryYear,
                'last_number' => 0,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
            $sequence = VoucherNumberSequence::query()
                ->where('year', $entryYear)
                ->lockForUpdate()
                ->firstOrFail();
            $nextNumber = $sequence->last_number + 1;

            $sequence->update(['last_number' => $nextNumber]);

            $voucher = Voucher::create([
                'voucher_number' => 'DV-'.str_pad((string) $entryYear, 4, '0', STR_PAD_LEFT).'-'.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT),
                'voucher_date' => $data['voucher_date'],
                'payee' => $data['payee'],
                'project_id' => $project->id,
                'project_name_snapshot' => $project->name,
                'scope_of_work' => $data['scope_of_work'] ?? null,
                'expense_category' => $data['expense_category'] ?? null,
                'payment_type' => $data['payment_type'],
                'created_by' => $creator->id,
                'prepared_by' => $data['prepared_by'] ?? $creator->name,
                'total_amount' => $this->calculateVoucherTotal->handle($data['items']),
                'status' => 'draft',
            ]);

            $voucher->refresh();
            $voucher->items()->createMany($this->itemRecords($data['items']));
            $voucher->load('items');

            AuditEvent::create([
                'actor_id' => $creator->id,
                'action' => 'voucher.created',
                'auditable_type' => Voucher::class,
                'auditable_id' => $voucher->id,
                'metadata' => [
                    'after' => $this->auditData($voucher),
                ],
            ]);

            event(new VoucherChanged(
                (int) $voucher->id,
                (int) $voucher->record_version,
                'created',
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
            'prepared_by' => $voucher->prepared_by,
            'total_amount' => $voucher->total_amount,
            'items' => $voucher->items->map(fn (VoucherItem $item): array => [
                'description' => $item->description,
                'amount' => $item->amount,
                'line_type' => $item->line_type,
                'sort_order' => $item->sort_order,
            ])->all(),
        ];
    }
}
