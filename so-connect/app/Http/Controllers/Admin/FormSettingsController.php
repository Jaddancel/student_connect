<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\Form\FormDescription;
use Illuminate\Http\Request;

class FormSettingsController extends Controller
{
    private const DIRECTORY_KEYS = [
        'student-leader-directory' => 'Directory of Student Leaders sign-up',
    ];

    public function show(Form $form)
    {
        $form->load('fields');

        return view('pages.admin.form-settings.show', [
            'form' => $form,
            'directoryKeys' => self::DIRECTORY_KEYS,
        ]);
    }

    public function update(Request $request, Form $form)
    {
        $validated = $request->validate([
            'allows_guest_scan' => ['nullable', 'boolean'],
            'directory_assignment_key' => [
                'nullable',
                'string',
                'in:' . implode(',', array_keys(self::DIRECTORY_KEYS)),
            ],
            'ocr_region' => ['nullable', 'array'],
            'ocr_region.*' => ['nullable', 'array'],
            'ocr_region.*.x_pct' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'ocr_region.*.y_pct' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'ocr_region.*.width_pct' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'ocr_region.*.height_pct' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'ocr_region.*.page' => ['nullable', 'integer', 'min:1'],
        ]);

        $form->update([
            'allows_guest_scan' => (bool) ($validated['allows_guest_scan'] ?? false),
            'directory_assignment_key' => $validated['directory_assignment_key'] ?? null,
        ]);

        foreach ($validated['ocr_region'] ?? [] as $fieldId => $region) {
            $filled = array_filter($region, fn ($v) => $v !== null && $v !== '');
            if (empty($filled)) {
                FormDescription::where('id', (int) $fieldId)
                    ->where('form_id', $form->id)
                    ->update(['ocr_region' => null]);
                continue;
            }

            FormDescription::where('id', (int) $fieldId)
                ->where('form_id', $form->id)
                ->update(['ocr_region' => $filled]);
        }

        return redirect()->route('admin.forms.settings', $form)
            ->with('success', 'Settings saved.');
    }
}
