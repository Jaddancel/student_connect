<?php

namespace App\Http\Controllers;

use App\Services\OcrClient;
use App\Services\SignatureProfileRegistrar;
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
     * Name an unrecognized signature: a Normal-mode field whose drawing the
     * verifier didn't recognize asks the submitter who signed, and posts the
     * name + drawing here. The server stores the extracted signature and files
     * it as a real (flagged) profile via {@see SignatureProfileRegistrar}, so it
     * becomes browsable in the Profile Manager and recognized from here on.
     * Server-authoritative: the client only supplies the drawing + name.
     */
    public function enroll(Request $request, SignatureProfileRegistrar $registrar): JsonResponse
    {
        $validated = $request->validate([
            'signature' => ['required', 'string', 'starts_with:data:image'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $path = SignatureImage::storeDataUrl($validated['signature'], 'signatures/enrolled');
        if ($path === null) {
            return response()->json(['status' => 'error'], 422);
        }

        $profile = $registrar->register($validated['name'], $path);
        if ($profile === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please enter at least a first or last name.',
            ], 422);
        }

        return response()->json([
            'status' => 'saved',
            'name' => trim($profile->first_name.' '.$profile->last_name),
        ]);
    }
}
