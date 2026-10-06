<?php

namespace App\Services;

use App\Models\Profile;
use App\Support\NameParser;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for turning a captured (name, signature) into a real
 * profile the system can recognize and browse. Both the interactive naming
 * endpoint (a Normal-mode field whose signature wasn't recognized) and the
 * submit-time enroller route through here.
 *
 * The name is split into first/middle/last, deduped against existing profiles
 * (so the same person isn't created twice, and a real registered profile with
 * no signature yet simply adopts this one), and otherwise a new placeholder
 * profile is created flagged {@see Profile::ORIGIN_SIGNATURE_ONLY}. The
 * resulting profile is mirrored into the signature registry, so the verifier
 * recognizes the signature from here on.
 */
class SignatureProfileRegistrar
{
    public function __construct(private readonly SignatureReferenceService $references) {}

    /**
     * Register a captured signature under a person's name, returning the profile
     * it was filed under (existing or newly created). Returns null only when the
     * name is unusable (no first or last name to file under).
     */
    public function register(string $fullName, string $signaturePath): ?Profile
    {
        $parts = NameParser::split($fullName);
        if ($parts['first_name'] === '' && $parts['last_name'] === '') {
            return null;
        }

        // Dedupe by name in a transaction so two concurrent submissions of the
        // same signatory can't both create a placeholder.
        return DB::transaction(function () use ($parts, $signaturePath) {
            $existing = $this->findByName($parts);

            if ($existing) {
                // Adopt the signature only when the profile has none, so a real
                // person's registered signature is never overwritten.
                if (trim((string) $existing->signature_path) === '') {
                    $existing->update(['signature_path' => $signaturePath]);
                }
                $this->references->syncFromProfile($existing->fresh());

                return $existing->fresh();
            }

            $profile = Profile::query()->create([
                'first_name' => $parts['first_name'],
                'middle_name' => $parts['middle_name'],
                'last_name' => $parts['last_name'],
                'occupation' => '',
                'signature_path' => $signaturePath,
                'origin' => Profile::ORIGIN_SIGNATURE_ONLY,
            ]);

            $this->references->syncFromProfile($profile);

            return $profile;
        });
    }

    /**
     * The existing profile matching this name exactly (case-insensitive on
     * first + last), if any — the dedupe key.
     *
     * @param  array{first_name:string, middle_name:string, last_name:string}  $parts
     */
    private function findByName(array $parts): ?Profile
    {
        return Profile::query()
            ->whereRaw('LOWER(first_name) = ?', [mb_strtolower($parts['first_name'])])
            ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($parts['last_name'])])
            ->orderBy('profile_id')
            ->first();
    }
}
