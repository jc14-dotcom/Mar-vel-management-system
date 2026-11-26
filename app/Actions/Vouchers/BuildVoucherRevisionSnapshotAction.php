<?php

namespace App\Actions\Vouchers;

use App\Models\Voucher;
use App\Models\VoucherItem;

class BuildVoucherRevisionSnapshotAction
{
    /**
     * @return array{
     *     voucher_number: string,
     *     voucher_date: string,
     *     payee: string,
     *     project_id: int,
     *     project_name_snapshot: string,
     *     scope_of_work: ?string,
     *     expense_category: ?string,
     *     payment_type: string,
     *     created_by: int,
     *     prepared_by: string,
     *     total_amount: string,
     *     items: array<int, array{description: string, amount: string, line_type: string, sort_order: int}>
     * }
     */
    public function handle(Voucher $voucher): array
    {
        $voucher->loadMissing('items');

        return [
            'voucher_number' => $voucher->voucher_number,
            'voucher_date' => $voucher->voucher_date->toDateString(),
            'payee' => $voucher->payee,
            'project_id' => (int) $voucher->project_id,
            'project_name_snapshot' => $voucher->project_name_snapshot,
            'scope_of_work' => $voucher->scope_of_work,
            'expense_category' => $voucher->expense_category,
            'payment_type' => $voucher->payment_type,
            'created_by' => (int) $voucher->created_by,
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
