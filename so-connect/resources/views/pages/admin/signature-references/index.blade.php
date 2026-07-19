@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Signature References" />

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('success'))
            <div class="rounded-xl border border-success-300 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/40 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Known signatures</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Every signature the verifier compares against — mirrored from user profiles or
                auto-enrolled under an owner's name. Delete any that were enrolled in error.
            </p>

            @if ($references->isEmpty())
                <p class="mt-6 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                    No signature references yet.
                </p>
            @else
                <div class="mt-6 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-gray-400">
                            <tr>
                                <th class="pb-2">Owner</th>
                                <th class="pb-2">Source</th>
                                <th class="pb-2">Added</th>
                                <th class="pb-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($references as $reference)
                                <tr>
                                    <td class="py-3 font-medium text-gray-800 dark:text-white/90">{{ $reference->name }}</td>
                                    <td class="py-3">
                                        <span class="rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase
                                            {{ $reference->source === 'profile' ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/10' : 'bg-gray-100 text-gray-500 dark:bg-gray-800' }}">
                                            {{ $reference->source }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-gray-500 dark:text-gray-400">{{ $reference->created_at?->diffForHumans() }}</td>
                                    <td class="py-3 text-right">
                                        <form method="POST" action="{{ route('superadmin.signature-references.destroy', $reference->reference_id) }}"
                                            onsubmit="return confirm('Delete this signature reference?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="rounded-lg bg-error-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-error-600">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $references->links() }}</div>
            @endif
        </section>
    </div>
@endsection
