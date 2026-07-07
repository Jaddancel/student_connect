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
        $request->validate([
            'photo' => ['required', 'image', 'max:2048'],
        ]);

        $template = IdTemplate::scannerTemplate();
        if (! $template) {
            return response()->json([
                'student_id' => null,
                'fields' => [],
                'note' => 'no active template',
            ]);
        }

        $result = $ocr->scan($template, $request->file('photo'));

        return response()->json([
            'student_id' => $result['student_id'] ?? null,
            'fields' => $result['fields'] ?? [],
            'note' => $result['note'] ?? null,
        ]);
    }
}
