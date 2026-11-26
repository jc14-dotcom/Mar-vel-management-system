<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePrintTemplateSettingsRequest;
use App\Models\AppSetting;
use App\Models\AuditEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(): Response
    {
        $setting = AppSetting::query()
            ->where('key', AppSetting::PRINT_TEMPLATE_KEY)
            ->first();

        return Inertia::render('Admin/Settings', [
            'settings' => [
                ...AppSetting::printTemplateDefaults(),
                ...($setting?->value ?? []),
            ],
        ]);
    }

    public function update(UpdatePrintTemplateSettingsRequest $request): RedirectResponse
    {
        $settings = [
            'company_name' => $request->validated('company_name') ?? '',
            'form_number' => $request->validated('form_number'),
            'effective_date' => $request->validated('effective_date') ?? '',
            'template_revision' => $request->validated('template_revision'),
            'procedural_notices' => $request->validated('procedural_notices') ?? '',
        ];

        DB::transaction(function () use ($request, $settings): void {
            $setting = AppSetting::query()
                ->where('key', AppSetting::PRINT_TEMPLATE_KEY)
                ->lockForUpdate()
                ->first();
            $before = $setting?->value ?? AppSetting::printTemplateDefaults();

            $setting ??= new AppSetting(['key' => AppSetting::PRINT_TEMPLATE_KEY]);
            $setting->value = $settings;
            $setting->save();

            AuditEvent::create([
                'actor_id' => $request->user()->id,
                'action' => 'settings.print_template_updated',
                'auditable_type' => AppSetting::class,
                'auditable_id' => $setting->id,
                'metadata' => [
                    'before' => $before,
                    'after' => $settings,
                ],
            ]);
        });

        return redirect()->route('admin.settings.edit')->with('success', 'Print settings saved.');
    }
}
