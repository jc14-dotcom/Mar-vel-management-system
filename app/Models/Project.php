<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $fillable = ['name', 'normalized_name', 'merged_into_id'];

    public static function normalizeDisplayName(string $name): string
    {
        return Str::squish($name);
    }

    public static function normalizeName(string $name): string
    {
        return Str::lower(self::normalizeDisplayName($name));
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    public function mergedProjects(): HasMany
    {
        return $this->hasMany(self::class, 'merged_into_id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }
}
