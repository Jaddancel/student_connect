<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IdTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * SuperAdmin CRUD for ID-recognition templates. The index/create/edit actions
 * return blades; store/update accept JSON from the Konva editor (like
 * {@see FormBuilderController}) and enforce the single-default rule inside a
 * transaction. Zone coordinates are stored in native image pixels.
 */
class IdTemplateController extends Controller
{
    public function index()
    {
        $templates = IdTemplate::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('pages.superadmin.id-templates.index', [
            'title' => 'ID Templates',
            'templates' => $templates,
        ]);
    }

    public function create()
    {
        return view('pages.superadmin.id-templates.create', [
            'title' => 'New ID Template',
            'template' => null,
            'templateData' => $this->blankData(),
        ]);
    }

    public function edit(IdTemplate $idTemplate)
    {
        return view('pages.superadmin.id-templates.edit', [
            'title' => 'Edit ID Template',
            'template' => $idTemplate,
            'templateData' => $this->dataFromModel($idTemplate),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);

        $template = DB::transaction(function () use ($data, $request) {
            $template = IdTemplate::create(array_merge($data, [
                'created_by' => $request->user()?->getKey(),
            ]));

            $this->enforceSingleDefault($template);

            return $template;
        });

        // The editor navigates to the edit page next; greet it with the toast.
        session()->flash('toast', 'Saved!');

        return response()->json([
            'message' => 'ID template created.',
            'redirect' => route('superadmin.id-templates.edit', $template),
        ]);
    }

    public function update(Request $request, IdTemplate $idTemplate): JsonResponse
    {
        $data = $this->validatePayload($request);

        DB::transaction(function () use ($data, $idTemplate) {
            $idTemplate->update($data);
            $this->enforceSingleDefault($idTemplate);
        });

        return response()->json([
            'message' => 'Saved!',
            'redirect' => route('superadmin.id-templates.edit', $idTemplate),
        ]);
    }

    public function destroy(IdTemplate $idTemplate): RedirectResponse
    {
        $paths = array_filter([$idTemplate->image_path, $idTemplate->back_image_path]);
        $idTemplate->delete();

        if ($paths) {
            Storage::disk((string) config('documents.disk', 'public'))->delete($paths);
        }

        return redirect()->route('superadmin.id-templates.index')
            ->with('success', 'ID template deleted.');
    }

    /**
     * Upload a reference ID image; returns its disk-relative path for the editor.
     * Mirrors {@see FormBuilderController::uploadAsset()}.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'asset' => ['required', 'image', 'max:5120'],
        ]);

        $path = $request->file('asset')->store(
            'id-templates',
            (string) config('documents.disk', 'public'),
        );

        return response()->json(['path' => $path]);
    }

    /**
     * Keep exactly one default template. When the given template is the default,
     * clear the flag on every other row. Otherwise, if it is active and no
     * default currently exists, promote it — so an admin who merely activates a
     * template still ends up with a working, badged scanner default.
     */
    private function enforceSingleDefault(IdTemplate $template): void
    {
        if ($template->is_default) {
            IdTemplate::query()
                ->where($template->getKeyName(), '!=', $template->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);

            return;
        }

        if ($template->is_active && ! IdTemplate::query()->where('is_default', true)->exists()) {
            $template->forceFill(['is_default' => true])->save();
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function validatePayload(Request $request): array
    {
        // Both sides carry the same shape; validate them symmetrically. The `back_`
        // prefix mirrors the model columns so a single ruleset covers each side.
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'orientation' => ['nullable', 'string', 'in:vertical,horizontal'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ];
        $messages = [];

        foreach (['front' => '', 'back' => 'back_'] as $side => $prefix) {
            $img = "{$prefix}image_path";
            $w = "{$prefix}image_width";
            $h = "{$prefix}image_height";
            $z = "{$prefix}zones";

            $rules += [
                $img => ['required', 'string', 'max:2048'],
                $w => ['required', 'integer', 'min:1'],
                $h => ['required', 'integer', 'min:1'],
                $z => ['required', 'array', 'min:1'],
                "$z.*.name" => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', 'distinct'],
                "$z.*.label" => ['required', 'string', 'max:150'],
                "$z.*.x1" => ['required', 'integer', 'min:0'],
                "$z.*.y1" => ['required', 'integer', 'min:0'],
                "$z.*.x2" => ['required', 'integer', "gt:$z.*.x1"],
                "$z.*.y2" => ['required', 'integer', "gt:$z.*.y1"],
                "$z.*.regex" => ['nullable', 'string', 'max:255'],
                "$z.*.field" => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
                "$z.*.color" => ['nullable', 'string', 'regex:/^#[0-9a-f]{6}$/i'],
            ];
            $messages += [
                "$z.*.name.regex" => 'Zone keys may only contain lowercase letters, numbers and underscores.',
                "$z.*.field.regex" => 'Zone fields may only contain lowercase letters, numbers and underscores.',
                "$z.*.x2.gt" => 'Each zone must have a positive width.',
                "$z.*.y2.gt" => 'Each zone must have a positive height.',
                "$z.required" => 'Add at least one zone on both the front and back of the ID.',
            ];
        }

        $validated = $request->validate($rules, $messages);

        $out = [
            'name' => $validated['name'],
            'orientation' => ($validated['orientation'] ?? '') === 'horizontal' ? 'horizontal' : 'vertical',
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'is_default' => (bool) ($validated['is_default'] ?? false),
        ];

        foreach (['front' => '', 'back' => 'back_'] as $side => $prefix) {
            $width = (int) $validated["{$prefix}image_width"];
            $height = (int) $validated["{$prefix}image_height"];
            $zones = $validated["{$prefix}zones"];

            // Coordinates must sit inside this side's reference image, and each regex
            // must be a compilable PCRE pattern (validated here so a bad pattern is a
            // 422, not a 500 later in the OCR path).
            foreach ($zones as $i => $zone) {
                if ((int) $zone['x2'] > $width || (int) $zone['y2'] > $height) {
                    throw ValidationException::withMessages([
                        "{$prefix}zones.$i" => 'Zone falls outside the reference image bounds.',
                    ]);
                }

                $pattern = $zone['regex'] ?? null;
                if ($pattern !== null && $pattern !== '' && @preg_match('/'.$pattern.'/', '') === false) {
                    throw ValidationException::withMessages([
                        "{$prefix}zones.$i.regex" => 'This is not a valid regular expression.',
                    ]);
                }
            }

            $out["{$prefix}image_path"] = $validated["{$prefix}image_path"];
            $out["{$prefix}image_width"] = $width;
            $out["{$prefix}image_height"] = $height;
            $out["{$prefix}zones"] = array_map(static fn (array $z) => [
                'name' => $z['name'],
                'label' => $z['label'],
                'x1' => (int) $z['x1'],
                'y1' => (int) $z['y1'],
                'x2' => (int) $z['x2'],
                'y2' => (int) $z['y2'],
                'regex' => ($z['regex'] ?? '') !== '' ? $z['regex'] : null,
                'field' => $z['field'],
                'color' => ($z['color'] ?? '') !== '' ? strtolower($z['color']) : null,
            ], $zones);
        }

        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    private function blankData(): array
    {
        return [
            'id' => null,
            'name' => '',
            'orientation' => 'vertical',
            'image_path' => '',
            'image_width' => 0,
            'image_height' => 0,
            'zones' => [],
            'back_image_path' => '',
            'back_image_width' => 0,
            'back_image_height' => 0,
            'back_zones' => [],
            'is_active' => true,
            'is_default' => false,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function dataFromModel(IdTemplate $template): array
    {
        return [
            'id' => $template->getKey(),
            'name' => $template->name,
            'orientation' => $template->orientation ?: 'vertical',
            'image_path' => $template->image_path,
            'image_width' => (int) $template->image_width,
            'image_height' => (int) $template->image_height,
            'zones' => (array) $template->zones,
            'back_image_path' => (string) $template->back_image_path,
            'back_image_width' => (int) $template->back_image_width,
            'back_image_height' => (int) $template->back_image_height,
            'back_zones' => (array) $template->back_zones,
            'is_active' => (bool) $template->is_active,
            'is_default' => (bool) $template->is_default,
        ];
    }
}
