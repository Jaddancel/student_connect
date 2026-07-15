<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Services\OcrClient;
use App\Support\SignatureImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Live "is this signature recognized?" endpoint behind the signature-field
 * badge. The drawn signature is compared against EVERY stored profile
 * signature via the OCR sidecar, and the best match (if it clears the
 * threshold) is reported with the owner's name. Purely advisory — nothing
 * here gates a submission.
 */
class SignatureVerificationController extends Controller
{
    /**
     * Upper bound on how many stored signatures ride along per check; keeps
     * the sidecar request bounded on installations with many profiles.
     */
    private const MAX_CANDIDATES = 300;

    public function verify(Request $request, OcrClient $ocr): JsonResponse
    {
        $validated = $request->validate([
            'signature' => ['required', 'string', 'starts_with:data:image'],
        ]);

        $probe = SignatureImage::decodeDataUrl($validated['signature']);
        if ($probe === null) {
            return response()->json(['status' => 'unavailable']);
        }

        [$candidates, $names] = $this->candidates();
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
     * All stored profile signatures as sidecar candidates, plus an id → owner
     * name map for the response.
     *
     * @return array{0: array<int,array{id:int, image:string}>, 1: array<int,string>}
     */
    private function candidates(): array
    {
        $disk = Storage::disk(SignatureImage::disk());

        $candidates = [];
        $names = [];

        $profiles = Profile::query()
            ->whereNotNull('signature_path')
            ->where('signature_path', '!=', '')
            ->orderByDesc('profile_id')
            ->limit(self::MAX_CANDIDATES)
            ->get(['profile_id', 'first_name', 'last_name', 'signature_path']);

        foreach ($profiles as $profile) {
            $path = (string) $profile->signature_path;
            if (! $disk->exists($path)) {
                continue;
            }

            $candidates[] = [
                'id' => (int) $profile->profile_id,
                'image' => base64_encode((string) $disk->get($path)),
            ];
            $names[(int) $profile->profile_id] = trim(
                $profile->first_name.' '.$profile->last_name,
            );
        }

        return [$candidates, $names];
    }
}
