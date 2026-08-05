<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\SignatureReference;
use App\Support\SignatureImage;
use Illuminate\Support\Facades\Storage;

/**
 * The single source of signature candidates for verification. Profile
 * signatures are mirrored in via {@see syncFromProfile()} / {@see backfill()};
 * unrecognized signatures are auto-enrolled via {@see enroll()}. The verifier
 * reads only this registry, so every candidate id is a reference_id.
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
     * Enroll a new signature under an owner name (owner may be a non-user).
     *
     * @param  array<int,float>|null  $embedding
     */
    public function enroll(string $name, string $signaturePath, ?array $embedding = null): SignatureReference
    {
        return SignatureReference::query()->create([
            'name' => trim($name) !== '' ? trim($name) : 'Unknown',
            'signature_path' => $signaturePath,
            'embedding' => $embedding,
            'source' => SignatureReference::SOURCE_ENROLLED,
        ]);
    }

    /**
     * Enroll a signature, refreshing the existing enrolled reference for that
     * owner name instead of stacking a new row per submission — the registry is
     * capped at {@see MAX_CANDIDATES}, so duplicates would crowd out other
     * signatories. Profile-sourced references are never touched.
     *
     * The superseded image file is left on disk: it is still referenced by the
     * submission that captured it.
     */
    public function enrollOrUpdate(string $name, string $signaturePath): SignatureReference
    {
        $name = trim($name) !== '' ? trim($name) : 'Unknown';

        $existing = SignatureReference::query()
            ->where('source', SignatureReference::SOURCE_ENROLLED)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            $existing->update(['signature_path' => $signaturePath, 'embedding' => null]);

            return $existing;
        }

        return $this->enroll($name, $signaturePath);
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
