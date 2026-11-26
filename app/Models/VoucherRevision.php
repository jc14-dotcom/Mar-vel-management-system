<?php

namespace App\Models;

use Database\Factories\VoucherRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherRevision extends Model
{
    /** @use HasFactory<VoucherRevisionFactory> */
    use HasFactory;

    protected $fillable = [
        'voucher_id',
        'revision_number',
        'snapshot',
        'reason',
        'actor_id',
        'printed_at',
        'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'snapshot' => 'array',
            'printed_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
