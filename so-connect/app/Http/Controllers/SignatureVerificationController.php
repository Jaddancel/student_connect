<?php

namespace App\Http\Controllers;

use App\Services\OcrClient;
use App\Services\SignatureReferenceService;
use App\Support\SignatureImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Live "is this signature recognized?" endpoint behind the signature-field
 * badge. The drawn signature is compared against EVERY stored profile
 * signature via the OCR sidecar, and the best match (if it clears the
 * threshold) is reported with the owner's name. Purely advisory — nothing
 * here gates a submission.
 */
class SignatureVerificationController extends Controller
{
    public function verify(Request $request, OcrClient $ocr, SignatureReferenceService $references): JsonResponse
    {
        $validated = $request->validate([
            'signature' => ['required', 'string', 'starts_with:data:image'],
        ]);

        $probe = SignatureImage::decodeDataUrl($validated['signature']);
        if ($probe === null) {
            return response()->json(['status' => 'unavailable']);
        }

        // Candidates come from the reference registry (backfilled profiles +
        // auto-enrolled owners), so non-user signatures are recognized too.
        [$candidates, $names] = $references->candidates();
        if ($candidates === []) {
            return response()->json(['status' => 'no_signatures']);
        }

        $result = $ocr->identifySignature($probe, $candidates);
        if (! $result['ok']) {
            return response()->json(['status' => 'unavailable']);
        }

        $best = $result['best'];

        return response()->json([
            'status' => $result['match'] ? 'recognized' : 'not_recognized',
            'matched_user' => $result['match'] && $best ? ($names[(int) $best['id']] ?? null) : null,
            'score' => $best['score'] ?? null,
        ]);
    }

    /**
     * Auto-enroll an unrecognized signature under a typed owner name (owners may
     * be non-users). Server-authoritative: the server stores the image and
     * creates the reference — the client only supplies the drawing + name.
     */
    public function enroll(Request $request, SignatureReferenceService $references): JsonResponse
    {
        $validated = $request->validate([
            'signature' => ['required', 'string', 'starts_with:data:image'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $path = SignatureImage::storeDataUrl($validated['signature'], 'signatures/enrolled');
        if ($path === null) {
            return response()->json(['status' => 'error'], 422);
        }

        $reference = $references->enroll($validated['name'], $path);

        return response()->json([
            'status' => 'enrolled',
            'reference_id' => (int) $reference->reference_id,
        ]);
    }
}
