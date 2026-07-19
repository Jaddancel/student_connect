<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SignatureReference;
use App\Support\SignatureImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Super-admin maintenance of the signature reference registry: review the known
 * signatures (backfilled profiles + auto-enrolled owners) and delete bad ones.
 */
class SignatureReferenceController extends Controller
{
    public function index(): View
    {
        $references = SignatureReference::query()
            ->orderByDesc('reference_id')
            ->paginate(40);

        return view('pages.admin.signature-references.index', [
            'title' => 'Signature References',
            'references' => $references,
        ]);
    }

    public function destroy(SignatureReference $reference): RedirectResponse
    {
        // Only enrolled images are owned by the registry; profile-sourced rows
        // point at the profile's file, which we must not delete here.
        if ($reference->source === SignatureReference::SOURCE_ENROLLED && $reference->signature_path) {
            Storage::disk(SignatureImage::disk())->delete($reference->signature_path);
        }

        $reference->delete();

        \App\Services\ActionLogger::log(
            \App\Services\ActionLogger::CATEGORY_SETTINGS,
            'signature_reference_deleted',
            'Deleted signature reference #'.$reference->reference_id,
            ['reference_id' => (int) $reference->reference_id],
        );

        return back()->with('success', 'Signature reference deleted.');
    }
}
