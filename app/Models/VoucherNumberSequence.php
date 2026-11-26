<?php

namespace App\Models;

use Database\Factories\VoucherNumberSequenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoucherNumberSequence extends Model
{
    /** @use HasFactory<VoucherNumberSequenceFactory> */
    use HasFactory;

    protected $fillable = ['year', 'last_number'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
