<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\SignatureReference;
use App\Support\SignatureImage;
use Illuminate\Support\Facades\Storage;

/**
 * The single source of signature candidates for verification. Every reference
 * mirrors a profile signature (via {@see syncFromProfile()} / {@see backfill()});
 * unrecognized signatures become flagged profiles that then sync in the same
 * way. The verifier reads only this registry, so every candidate id is a
 * reference_id.
 */
class SignatureReferenceService
{
    public const MAX_CANDIDATES = 300;

    private function disk()
    {
        return Storage::disk(SignatureImage::disk());
    }

    /**
     * Candidates for the sidecar (reference_id => base64 image) plus a
     * reference_id => owner-name map. Skips references whose image is missing.
     *
     * @return array{0: array<int,array{id:int, image:string}>, 1: array<int,string>}
     */
    public function candidates(int $limit = self::MAX_CANDIDATES): array
    {
        $disk = $this->disk();
        $candidates = [];
        $names = [];

        $references = SignatureReference::query()
            ->orderByDesc('reference_id')
            ->limit($limit)
            ->get(['reference_id', 'name', 'signature_path']);

        foreach ($references as $reference) {
            $path = (string) $reference->signature_path;
            if ($path === '' || ! $disk->exists($path)) {
                continue;
            }

            $candidates[] = [
                'id' => (int) $reference->reference_id,
                'image' => base64_encode((string) $disk->get($path)),
            ];
            $names[(int) $reference->reference_id] = (string) $reference->name;
        }

        return [$candidates, $names];
    }

    /**
     * Candidates built from a supplied set of references (rather than the whole
     * registry), so a Compare-mode field matches a probe only against the
     * expected signer(s). Same shape as {@see candidates()}.
     *
     * @param  iterable<SignatureReference>  $references
     * @return array{0: array<int,array{id:int, image:string}>, 1: array<int,string>}
     */
    public function candidatesFrom(iterable $references): array
    {
        $disk = $this->disk();
        $candidates = [];
        $names = [];

        foreach ($references as $reference) {
            $path = (string) $reference->signature_path;
            if ($path === '' || ! $disk->exists($path)) {
                continue;
            }

            $candidates[] = [
                'id' => (int) $reference->reference_id,
                'image' => base64_encode((string) $disk->get($path)),
            ];
            $names[(int) $reference->reference_id] = (string) $reference->name;
        }

        return [$candidates, $names];
    }

    /**
     * Mirror a profile's current signature into the registry (idempotent by
     * profile_id). Called whenever a profile signature is written.
     */
    public function syncFromProfile(Profile $profile): ?SignatureReference
    {
        $path = (string) ($profile->signature_path ?? '');
        if ($path === '') {
            // No signature — drop any stale reference for this profile.
            SignatureReference::query()->where('profile_id', $profile->getKey())->delete();

            return null;
        }

        $name = trim($profile->first_name.' '.$profile->last_name);

        return SignatureReference::query()->updateOrCreate(
            ['profile_id' => (int) $profile->getKey()],
            [
                'name' => $name !== '' ? $name : 'Unknown',
                'signature_path' => $path,
                'source' => SignatureReference::SOURCE_PROFILE,
            ],
        );
    }

    /**
     * Populate the registry from every profile signature. Returns the number of
     * references synced.
     */
    public function backfill(): int
    {
        $count = 0;
        Profile::query()
            ->whereNotNull('signature_path')
            ->where('signature_path', '!=', '')
            ->chunkById(200, function ($profiles) use (&$count) {
                foreach ($profiles as $profile) {
                    $this->syncFromProfile($profile);
                    $count++;
                }
            }, 'profile_id');

        return $count;
    }
}
