@extends('layouts.app')
@section('content')
<x-common.page-breadcrumb pageTitle="Best Student Organizations" />
<div class="grid gap-5 lg:grid-cols-2">
@forelse($cycles as $cycle)<article class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[.03]"><p class="text-xs font-semibold uppercase tracking-wide text-brand-500">{{ $cycle->semester?->name }} · {{ $cycle->semester?->schoolYear() }}</p><h2 class="mt-2 text-xl font-semibold text-gray-800 dark:text-white">{{ $cycle->winner_name }}</h2><p class="mt-1 text-sm text-gray-500">{{ $cycle->title }} · Verified score {{ number_format((float)$cycle->winner_score, 2) }}</p><p class="mt-4 whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-300">{{ $cycle->selection_notes }}</p><p class="mt-4 text-xs text-gray-400">Award finalized {{ $cycle->finalized_at?->format('M d, Y') }}</p></article>@empty<div class="rounded-2xl border border-dashed border-gray-300 p-16 text-center text-sm text-gray-400 lg:col-span-2">No Best Student Organization award has been published yet.</div>@endforelse
</div>
@endsection
