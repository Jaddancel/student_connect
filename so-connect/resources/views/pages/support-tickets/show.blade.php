@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :pageTitle="$title" />

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $ticket->subject }}</h2><p class="text-xs text-gray-500">Submitted {{ $ticket->created_at->format('M j, Y g:i A') }}</p></div>
                    <span class="rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-400">{{ $statuses[$ticket->status] }}</span>
                </div>
                <p class="mt-4 whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300">{{ $ticket->description }}</p>
                @if ($ticket->page_path)<p class="mt-3 text-xs text-gray-400">Page: {{ $ticket->page_path }}</p>@endif
            </div>

            @foreach ($ticket->messages as $message)
                @php($name = collect([$message->user?->profileRecord?->first_name, $message->user?->profileRecord?->last_name])->filter()->implode(' ') ?: $message->user?->user_email ?: 'Deleted user')
                <div class="rounded-2xl border p-5 {{ $message->from_support ? 'border-brand-200 bg-brand-50/40 dark:border-brand-500/20 dark:bg-brand-500/5' : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]' }}">
                    <div class="mb-2 flex justify-between text-xs"><strong class="text-gray-700 dark:text-gray-300">{{ $name }}{{ $message->from_support ? ' · Support' : '' }}</strong><span class="text-gray-400">{{ $message->created_at->diffForHumans() }}</span></div>
                    <p class="whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300">{{ $message->body }}</p>
                </div>
            @endforeach

            <form method="POST" action="{{ route('support-tickets.reply', $ticket) }}" class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                @csrf
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Reply</label>
                <textarea name="body" rows="4" required maxlength="4000" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">{{ old('body') }}</textarea>
                <button class="mt-3 rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Send reply</button>
            </form>
        </div>

        <aside class="space-y-4">
            @if ($isSupportAgent)
                <form method="POST" action="{{ route('admin.support-tickets.update', $ticket) }}" class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                    @csrf @method('PATCH')
                    <h3 class="mb-4 font-semibold text-gray-800 dark:text-white/90">Manage ticket</h3>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Status</label>
                    <select name="status" class="mb-4 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90">
                        @foreach($statuses as $value => $label)<option value="{{ $value }}" @selected($ticket->status === $value)>{{ $label }}</option>@endforeach
                    </select>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Assigned to</label>
                    <select name="assigned_to" class="mb-4 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90">
                        <option value="">Unassigned</option>
                        @foreach($agents as $agent)
                            @php($agentName = collect([$agent->profileRecord?->first_name, $agent->profileRecord?->last_name])->filter()->implode(' ') ?: $agent->user_email)
                            <option value="{{ $agent->getKey() }}" @selected((int)$ticket->assigned_to === (int)$agent->getKey())>{{ $agentName }}</option>
                        @endforeach
                    </select>
                    <button class="w-full rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white">Update ticket</button>
                </form>
            @endif

            @if (!empty($ticket->assistant_context))
                <details class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                    <summary class="cursor-pointer text-sm font-semibold text-gray-800 dark:text-white/90">Assistant conversation</summary>
                    <div class="mt-3 space-y-3">
                        @foreach($ticket->assistant_context as $turn)
                            <div class="text-xs"><strong class="capitalize text-gray-600 dark:text-gray-400">{{ $turn['role'] }}</strong><p class="mt-1 whitespace-pre-wrap text-gray-700 dark:text-gray-300">{{ $turn['content'] }}</p></div>
                        @endforeach
                    </div>
                </details>
            @endif
        </aside>
    </div>
@endsection
