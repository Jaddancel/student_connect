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
            'message' => 'ID template saved.',
            'redirect' => route('superadmin.id-templates.edit', $idTemplate),
        ]);
    }

    public function destroy(IdTemplate $idTemplate): RedirectResponse
    {
        $path = $idTemplate->image_path;
        $idTemplate->delete();

        if ($path) {
            Storage::disk((string) config('documents.disk', 'public'))->delete($path);
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
     * When the given template is the default, clear the flag on every other row.
     */
    private function enforceSingleDefault(IdTemplate $template): void
    {
        if (! $template->is_default) {
            return;
        }

        IdTemplate::query()
            ->where($template->getKeyName(), '!=', $template->getKey())
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    /**
     * @return array<string,mixed>
     */
    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'image_path' => ['required', 'string', 'max:2048'],
            'image_width' => ['required', 'integer', 'min:1'],
            'image_height' => ['required', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'zones' => ['required', 'array', 'min:1'],
            'zones.*.name' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', 'distinct'],
            'zones.*.label' => ['required', 'string', 'max:150'],
            'zones.*.x1' => ['required', 'integer', 'min:0'],
            'zones.*.y1' => ['required', 'integer', 'min:0'],
            'zones.*.x2' => ['required', 'integer', 'gt:zones.*.x1'],
            'zones.*.y2' => ['required', 'integer', 'gt:zones.*.y1'],
            'zones.*.regex' => ['nullable', 'string', 'max:255'],
            'zones.*.field' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
        ], [
            'zones.*.name.regex' => 'Zone keys may only contain lowercase letters, numbers and underscores.',
            'zones.*.field.regex' => 'Zone fields may only contain lowercase letters, numbers and underscores.',
            'zones.*.x2.gt' => 'Each zone must have a positive width.',
            'zones.*.y2.gt' => 'Each zone must have a positive height.',
        ]);

        $width = (int) $validated['image_width'];
        $height = (int) $validated['image_height'];

        // Coordinates must sit inside the reference image, and each regex must
        // be a compilable PCRE pattern (validated here so a bad pattern is a 422,
        // not a 500 later in the OCR path).
        foreach ($validated['zones'] as $i => $zone) {
            if ((int) $zone['x2'] > $width || (int) $zone['y2'] > $height) {
                throw ValidationException::withMessages([
                    "zones.$i" => 'Zone falls outside the reference image bounds.',
                ]);
            }

            $pattern = $zone['regex'] ?? null;
            if ($pattern !== null && $pattern !== '' && @preg_match('/'.$pattern.'/', '') === false) {
                throw ValidationException::withMessages([
                    "zones.$i.regex" => 'This is not a valid regular expression.',
                ]);
            }
        }

        return [
            'name' => $validated['name'],
            'image_path' => $validated['image_path'],
            'image_width' => $width,
            'image_height' => $height,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'zones' => array_map(static fn (array $z) => [
                'name' => $z['name'],
                'label' => $z['label'],
                'x1' => (int) $z['x1'],
                'y1' => (int) $z['y1'],
                'x2' => (int) $z['x2'],
                'y2' => (int) $z['y2'],
                'regex' => ($z['regex'] ?? '') !== '' ? $z['regex'] : null,
                'field' => $z['field'],
            ], $validated['zones']),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function blankData(): array
    {
        return [
            'id' => null,
            'name' => '',
            'image_path' => '',
            'image_width' => 0,
            'image_height' => 0,
            'is_active' => true,
            'is_default' => false,
            'zones' => [],
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
            'image_path' => $template->image_path,
            'image_width' => (int) $template->image_width,
            'image_height' => (int) $template->image_height,
            'is_active' => (bool) $template->is_active,
            'is_default' => (bool) $template->is_default,
            'zones' => (array) $template->zones,
        ];
    }
}
