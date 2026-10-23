@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Best Organization Selection" />
    <div class="space-y-6">
        @if(session('success'))<div class="rounded-xl bg-success-50 px-4 py-3 text-sm text-success-700">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="rounded-xl bg-error-50 px-4 py-3 text-sm text-error-700">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('admin.best-organizations.store') }}" class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[.03]">
            @csrf
            <h2 class="mb-4 font-semibold text-gray-800 dark:text-white">Create selection cycle</h2>
            <div class="grid gap-3 md:grid-cols-[1fr_1fr_auto]">
                <x-common.predictive-select name="semester_id" :allow-clear="false" :required="true" search-placeholder="Search semester…" :options="$semesters->map(fn($semester) => ['value'=>$semester->semester_id,'label'=>$semester->name.' · '.$semester->schoolYear()])->all()" />
                <input name="title" required maxlength="255" value="{{ old('title', 'Best Student Organization Award') }}" class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700" />
                <button class="rounded-lg bg-brand-500 px-5 text-sm font-medium text-white">Create cycle</button>
            </div>
            @if($semesters->isEmpty())<p class="mt-2 text-xs text-warning-600">Every available semester already has a selection cycle.</p>@endif
        </form>

        <div class="grid gap-4 lg:grid-cols-2">
            @forelse($cycles as $cycle)
                <a href="{{ route('admin.best-organizations.show', $cycle) }}" class="rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-brand-400 dark:border-gray-800 dark:bg-white/[.03]">
                    <div class="flex items-start justify-between gap-3"><div><h3 class="font-semibold text-gray-800 dark:text-white">{{ $cycle->title }}</h3><p class="text-sm text-gray-500">{{ $cycle->semester?->name }} · {{ $cycle->semester?->schoolYear() }}</p></div><span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium uppercase text-brand-600">{{ $cycle->status }}</span></div>
                    <div class="mt-4 flex justify-between text-sm text-gray-500"><span>{{ $cycle->candidates_count }} candidates</span><span>{{ $cycle->winner_name ?? 'No winner yet' }}</span></div>
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 p-12 text-center text-sm text-gray-400 lg:col-span-2">No selection cycles yet.</div>
            @endforelse
        </div>
    </div>
@endsection
