@php
    $authProfile = auth()->user()?->profile()->first();
    $signaturePath = $authProfile?->signature_path;
    $signatureUrl = $signaturePath
        ? \Illuminate\Support\Facades\Storage::disk(\App\Support\SignatureImage::disk())->url($signaturePath)
        : null;
@endphp

<div class="p-5 mb-6 border border-gray-200 rounded-2xl dark:border-gray-800 lg:p-6">
    <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">Signature</h4>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        Your saved signature pre-fills signature fields on forms and is what the
        system compares against when recognizing a signature.
    </p>

    <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div>
            <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">Saved signature</p>
            @if ($signatureUrl)
                <img src="{{ $signatureUrl }}" alt="Saved signature"
                     class="h-28 w-auto max-w-full rounded-lg border border-gray-200 bg-white object-contain p-2 dark:border-gray-700" />
            @else
                <p class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-xs text-gray-400 dark:border-gray-700 dark:text-gray-500">
                    No signature saved yet.
                </p>
            @endif
        </div>

        <form method="POST" action="{{ route('profile.signature') }}" enctype="multipart/form-data"
              x-data="signatureField()" class="space-y-2">
            @csrf
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                Draw a new signature (or upload an image of it)
            </p>
            <canvas x-ref="canvas" width="500" height="160"
                class="w-full rounded-lg border border-gray-300 bg-white touch-none dark:border-gray-700"></canvas>
            <input type="hidden" name="signature" x-ref="input" />
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="clear()"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
                    Clear
                </button>
                <label class="cursor-pointer rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
                    Upload instead
                    <input type="file" name="signature_file" accept="image/jpeg,image/png" class="hidden"
                           @change="$el.form.submit()" />
                </label>
                <button type="submit"
                    class="rounded-lg bg-brand-500 px-4 py-1.5 text-xs font-medium text-white transition hover:bg-brand-600">
                    Save signature
                </button>
            </div>
        </form>
    </div>
</div>
