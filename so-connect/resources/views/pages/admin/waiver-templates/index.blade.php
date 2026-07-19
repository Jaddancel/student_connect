@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Waiver Templates" />

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('success'))
            <div class="rounded-xl border border-success-300 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/40 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-error-300 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/40 dark:bg-error-500/10 dark:text-error-400">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Waiver templates</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Reference waiver forms with text / signature / stamp zones the event scanner reads.
            </p>

            @if ($templates->isEmpty())
                <p class="mt-6 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                    No waiver templates yet.
                </p>
            @else
                <ul class="mt-6 divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($templates as $template)
                        <li class="flex items-center justify-between py-3">
                            <div>
                                <p class="font-medium text-gray-800 dark:text-white/90">{{ $template->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ count((array) $template->zones) }} zone(s)</p>
                            </div>
                            <form method="POST" action="{{ route('admin.waiver-templates.destroy', $template->id_template_id) }}"
                                onsubmit="return confirm('Delete this waiver template?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg bg-error-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-error-600">Delete</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
