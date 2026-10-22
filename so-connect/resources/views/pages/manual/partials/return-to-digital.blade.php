{{-- Abandon the paper draft and reopen the digital form with the typed values. --}}
<div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
    <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Need to change something first?</h3>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        Go back to the digital form with everything you typed already filled in. This draft and its printable PDF are discarded; you can choose Fill manually again afterwards.
    </p>
    <form method="post" action="{{ route('manual.return-to-digital', $session) }}" class="mt-3"
        data-confirm="Return to the digital form? This paper draft will be discarded, but everything you typed is kept.">
        @csrf
        @if ($token !== '')
            <input type="hidden" name="manual_token" value="{{ $token }}">
        @endif
        <button type="submit"
            class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]">
            Return to the digital form
        </button>
    </form>
</div>
