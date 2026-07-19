<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IdTemplate;
use App\Support\ZonePayloadValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * CRUD for waiver templates — id_templates rows with kind='waiver' whose zones
 * (text / signature / stamp) are authored over a reference waiver image in the
 * Konva editor. Used by the event waiver scanner to crop + validate a scan.
 */
class WaiverTemplateController extends Controller
{
    public const KIND = 'waiver';

    public function index(): View
    {
        return view('pages.admin.waiver-templates.index', [
            'title' => 'Waiver Templates',
            'templates' => IdTemplate::query()->where('kind', self::KIND)->orderByDesc('id_template_id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'image' => ['required', 'image', 'max:5120'],
            'image_width' => ['required', 'integer', 'min:1'],
            'image_height' => ['required', 'integer', 'min:1'],
            'zones' => ['required', 'array', 'min:1'],
        ]);

        // Authoritatively validate/normalize the zones (throws 422 on bad input).
        $zones = ZonePayloadValidator::validate($validated['zones']);

        $path = $request->file('image')->store('waiver-templates', (string) config('documents.disk', 'public'));

        IdTemplate::create([
            'name' => $validated['name'],
            'kind' => self::KIND,
            'image_path' => $path,
            'image_width' => (int) $validated['image_width'],
            'image_height' => (int) $validated['image_height'],
            'zones' => $zones,
            'is_active' => true,
            'created_by' => $request->user()?->getKey(),
        ]);

        \App\Services\ActionLogger::log(
            \App\Services\ActionLogger::CATEGORY_ID_TEMPLATE,
            'waiver_template_created',
            'Created waiver template "'.$validated['name'].'"',
        );

        return back()->with('success', 'Waiver template saved.');
    }

    public function destroy(IdTemplate $waiverTemplate): RedirectResponse
    {
        abort_unless($waiverTemplate->kind === self::KIND, 404);

        if ($waiverTemplate->image_path) {
            Storage::disk((string) config('documents.disk', 'public'))->delete($waiverTemplate->image_path);
        }
        $waiverTemplate->delete();

        return back()->with('success', 'Waiver template deleted.');
    }
}
