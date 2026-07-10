<?php

namespace App\Http\Controllers;

use App\Models\IdTemplate;
use App\Services\OcrClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:2048'],
            'side' => ['nullable', 'string', 'in:front,back'],
        ]);
        $side = ($validated['side'] ?? 'front') === 'back' ? 'back' : 'front';

        $template = IdTemplate::scannerTemplate();
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

        return response()->json([
            'student_id' => $result['student_id'] ?? null,
            'fields' => $result['fields'] ?? [],
            'note' => $result['note'] ?? null,
        ]);
    }
}
