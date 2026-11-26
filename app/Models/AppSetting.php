<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    public const PRINT_TEMPLATE_KEY = 'print_template';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * @return array{company_name: string, form_number: string, effective_date: string, template_revision: string, procedural_notices: string}
     */
    public static function printTemplateDefaults(): array
    {
        return [
            'company_name' => '',
            'form_number' => 'MCS-OP-ACC-01F3',
            'effective_date' => '',
            'template_revision' => '1',
            'procedural_notices' => '',
        ];
    }
}
