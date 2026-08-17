<?php

namespace App\Http\Controllers;

use App\Models\IdTemplate;
use App\Services\OcrClient;
use App\Support\IdScanRetryCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Client-side auto-scan endpoint. The student-leader-directory signup form POSTs
 * the uploaded front-ID photo here on `id_photo_front` change; we run it through
 * the active {@see IdTemplate} and return extracted values (notably student_id)
 * to pre-fill the form. This is a convenience pre-fill only — the student can
 * always override, and the form's own validation is unchanged.
 *
 * The route is intentionally NOT behind `auth`: the directory form is a public
 * signup page filled by users who do not yet have an account.
 */
class IdScanController extends Controller
{
    public function scan(Request $request, OcrClient $ocr): JsonResponse
    {
        // This endpoint is consumed exclusively by the wizard's fetch(), which
        // cannot follow the redirect Laravel issues for a failed validation on
        // a non-JSON request — that used to surface as an instant, unexplained
        // scan failure. Fail soft with the same JSON shape as every other
        // "can't scan" condition instead.
        try {
            $validated = $request->validate([
                'photo' => ['required', 'image', 'max:2048'],
                'side' => ['nullable', 'string', 'in:front,back'],
                'template_id' => ['nullable', 'integer'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'student_id' => null,
                'fields' => [],
                'note' => 'invalid photo',
                'errors' => $e->errors(),
            ]);
        }
        $side = ($validated['side'] ?? 'front') === 'back' ? 'back' : 'front';

        // The wizard's template chooser (2+ active templates) sends the pick;
        // anything missing, inactive, or unknown falls back to the default
        // scanner template so single-template setups behave exactly as before.
        $templateId = (int) ($validated['template_id'] ?? 0);
        $template = $templateId > 0
            ? IdTemplate::query()->where('is_active', true)->find($templateId)
            : null;
        $template = $template ?: IdTemplate::scannerTemplate();
        if (! $template) {
            return response()->json([
                'student_id' => null,
                'fields' => [],
                'note' => 'no active template',
            ]);
        }

        // The back side is optional at scan time: if this template has no back
        // zones there is nothing to extract, so return empty rather than error.
        if ($side === 'back' && empty($template->zonesForSide('back'))) {
            return response()->json([
                'student_id' => null,
                'fields' => [],
                'note' => 'no back zones',
            ]);
        }

        $result = $ocr->scan($template, $request->file('photo'), $side);

        // Cache this side's photo + detected fields against the session, so a
        // validation failure elsewhere on the form doesn't force a re-scan —
        // see IdScanRetryCache for why this is needed only for the photo.
        IdScanRetryCache::remember($request->session()->getId(), $side, $request->file('photo'), $result['fields'] ?? []);

        return response()->json([
            'student_id' => $result['student_id'] ?? null,
            'fields' => $result['fields'] ?? [],
            // field => data-URL crops from signature-type zones (e.g. the
            // signer's signature image, which the wizard feeds into the form).
            'images' => $result['images'] ?? [],
            'note' => $result['note'] ?? null,
        ]);
    }

    /**
     * Serve back a side's cached photo for this session — lets the wizard
     * show "already scanned" previews after a validation-failure reload
     * without re-uploading. Not behind `auth` for the same reason `/id-scan`
     * isn't: the directory form is filled by users without an account yet.
     */
    public function retryPhoto(Request $request, string $side): Response
    {
        abort_unless(in_array($side, ['front', 'back'], true), 404);

        $path = IdScanRetryCache::photoPath($request->session()->getId(), $side);
        abort_if($path === null, 404);

        return response(
            \Illuminate\Support\Facades\Storage::disk('local')->get($path),
            200,
            ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store'],
        );
    }
}
