@props(['submitterName', 'currentSignaturePath'])

@php
    use App\Services\SignatureRecordService;
    $result = app(SignatureRecordService::class)->compare($currentSignaturePath, $submitterName);
    $status = $result['status'];
    $previousCount = $result['previous_count'];
    $previousSignatures = $result['previous_signatures'];
@endphp

<div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Signature Verification</h3>
    </div>

    <div class="px-6 py-5 space-y-4">
        {{-- Current signature --}}
        <div>
            <p class="text-xs font-medium text-gray-400 dark:text-gray-500 mb-1.5">Current Signature</p>
            <img src="{{ asset('storage/'.$currentSignaturePath) }}"
                 alt="Current Signature"
                 class="h-20 object-contain rounded border border-gray-200 dark:border-gray-700 bg-white cursor-pointer"
                 @click="$store.lightbox?.show('{{ asset('storage/'.$currentSignaturePath) }}', 'Current Signature')" />
        </div>

        {{-- Status badge --}}
        <div>
            @if($status === 'match')
                <span class="inline-flex items-center gap-1.5 rounded-full bg-success-100 px-3 py-1 text-xs font-semibold text-success-700 dark:bg-success-500/15 dark:text-success-400">
                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    Matches previous signature
                </span>
            @elseif($status === 'different')
                <span class="inline-flex items-center gap-1.5 rounded-full bg-warning-100 px-3 py-1 text-xs font-semibold text-warning-700 dark:bg-warning-500/15 dark:text-warning-400">
                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    Differs from previous signature
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                    First-time submitter
                </span>
            @endif
        </div>

        {{-- Previous signatures --}}
        @if($previousCount > 0 && !empty($previousSignatures))
            <div>
                <p class="text-xs font-medium text-gray-400 dark:text-gray-500 mb-2">
                    Previous Signatures ({{ $previousCount }})
                </p>
                <div class="flex flex-wrap gap-3">
                    @foreach(array_slice($previousSignatures, 0, 5) as $sigPath)
                        <img src="{{ asset('storage/'.$sigPath) }}"
                             alt="Previous Signature"
                             class="h-14 object-contain rounded border border-gray-200 dark:border-gray-700 bg-white cursor-pointer"
                             @click="$store.lightbox?.show('{{ asset('storage/'.$sigPath) }}', 'Previous Signature')" />
                    @endforeach
                    @if($previousCount > 5)
                        <span class="flex h-14 items-center px-3 text-xs text-gray-400 dark:text-gray-500">+{{ $previousCount - 5 }} more</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
