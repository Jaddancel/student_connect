@props(['type'])

@if ($type === \App\Services\BackupService::TYPE_CONFIGURATION)
    <span class="inline-flex whitespace-nowrap rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">Configuration</span>
@else
    <span class="inline-flex whitespace-nowrap rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-white/5 dark:text-gray-300">Database</span>
@endif
