<?php

namespace App\Actions\Vouchers;

use Illuminate\Validation\ValidationException;

class CalculateVoucherTotal
{
    /**
     * @param  array<int, array{amount: string, line_type: string}>  $items
     */
    public function handle(array $items): string
    {
        $totalCents = 0;

        foreach ($items as $item) {
            [$whole, $fraction] = array_pad(explode('.', $item['amount'], 2), 2, '');
            $amountCents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

            $totalCents += $item['line_type'] === 'deduction'
                ? -$amountCents
                : $amountCents;
        }

        if (abs($totalCents) > 999_999_999_999) {
            throw ValidationException::withMessages([
                'items' => 'The calculated voucher total exceeds the supported amount.',
            ]);
        }

        $absoluteCents = abs($totalCents);
        $sign = $totalCents < 0 ? '-' : '';

        return $sign.intdiv($absoluteCents, 100).'.'.str_pad((string) ($absoluteCents % 100), 2, '0', STR_PAD_LEFT);
    }
}
