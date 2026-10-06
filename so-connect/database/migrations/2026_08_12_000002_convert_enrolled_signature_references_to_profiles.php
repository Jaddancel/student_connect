<?php

use App\Models\SignatureReference;
use App\Services\SignatureProfileRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * The signature registry no longer has an "enrolled" source: every reference
 * mirrors a profile. Convert each existing auto-enrolled signature into a
 * flagged (signature-only) profile — split its owner name, dedupe, create the
 * profile, and mirror it back in as a profile-sourced reference — so no captured
 * signature is lost when the enrolled source is removed.
 *
 * The registrar reuses each enrolled row's stored image path for the new
 * profile, so the file stays referenced; only the stale enrolled row is removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $registrar = app(SignatureProfileRegistrar::class);

        SignatureReference::query()
            ->where('source', 'enrolled')
            ->orderBy('reference_id')
            ->chunkById(200, function ($references) use ($registrar) {
                foreach ($references as $reference) {
                    $path = (string) $reference->signature_path;
                    if ($path !== '') {
                        $registrar->register((string) $reference->name, $path);
                    }
                    $reference->delete();
                }
            }, 'reference_id');
    }

    public function down(): void
    {
        // Irreversible: the enrolled rows became profiles; we don't reconstruct them.
    }
};
