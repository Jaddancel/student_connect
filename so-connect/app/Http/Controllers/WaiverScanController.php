<?php

namespace App\Http\Controllers;

use App\Services\OcrClient;
use App\Services\WaiverValidationService;
use App\Support\SignatureImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Waiver scan preflight: sends a scanned waiver photo to the OCR sidecar, then
 * fuzzy-validates the extracted details (name/date/time + stamp/signature
 * presence) against the event's expected details. Advisory — the authoritative
 * re-validation happens again at form submission.
 */
class WaiverScanController extends Controller
{
    public function scan(Request $request, OcrClient $ocr, WaiverValidationService $validator): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'string', 'starts_with:data:image'],
            'template' => ['required', 'array'],
            'template.reference' => ['required', 'array'],
            'template.zones' => ['required', 'array'],
            'expected' => ['nullable', 'array'],
        ]);

        $png = SignatureImage::decodeDataUrl($validated['image']);
        if ($png === null) {
            return response()->json(['ok' => false, 'status' => 'bad_image'], 422);
        }

        $scan = $ocr->scanWaiver($png, $validated['template']);
        if (! ($scan['ok'] ?? false)) {
            return response()->json(['ok' => false, 'status' => 'unavailable']);
        }

        $validation = $validator->validate(
            (array) ($scan['fields'] ?? []),
            (array) ($validated['expected'] ?? []),
            [
                'stamp' => (bool) ($scan['stamp'] ?? false),
                'signature' => (bool) ($scan['signature'] ?? false),
            ],
        );

        return response()->json([
            'ok' => true,
            'valid' => $validation['valid'],
            'validation' => $validation,
            'stamp' => (bool) ($scan['stamp'] ?? false),
            'signature' => (bool) ($scan['signature'] ?? false),
        ]);
    }
}
