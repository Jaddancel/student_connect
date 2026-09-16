@extends('layouts.app')

@php
    use App\Forms\FieldType;
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Review Activity Request" />

    <div class="space-y-6 max-w-4xl">
        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Meta --}}
        <div class="rounded-2xl border border-gray-200 bg-white px-6 py-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Organization</p>
                    <p class="mt-1 text-gray-800 dark:text-white/90">{{ $orgName }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Submitted By</p>
                    <p class="mt-1 text-gray-800 dark:text-white/90">{{ $requesterName }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Submitted At</p>
                    <p class="mt-1 text-gray-800 dark:text-white/90">{{ \Illuminate\Support\Carbon::parse($actionRequest->requested_at)->format('M d, Y h:i A') }}</p>
                </div>
            </div>
        </div>

        {{-- Submitted answers, labelled by the form's own fields --}}
        @if ($submission)
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Activity Details</h3>
                </div>
                <div class="grid grid-cols-1 gap-x-6 gap-y-4 px-6 py-5 text-sm sm:grid-cols-2">
                    @foreach ($fields as $field)
                        @continue(FieldType::isPresentational($field->field_type))
                        {{-- Passwords are stored hashed and never shown. --}}
                        @continue($field->field_type === FieldType::PASSWORD)
                        @php
                            $type = $field->field_type;
                            $value = $submissionPayload[$field->field_key] ?? null;
                            $isImagePath = is_string($value) && $value !== ''
                                && (FieldType::isFileLike($type) || in_array($type, [FieldType::SIGNATURE, FieldType::WAIVER_SCAN], true))
                                && preg_match('/\.(jpe?g|png|gif|webp|heic|heif)$/i', $value);
                            $isFilePath = is_string($value) && $value !== '' && FieldType::isFileLike($type) && ! $isImagePath;
                            $isDataUri = is_string($value) && str_starts_with($value, 'data:image');
                            $tableCols = $type === FieldType::TABLE_INPUT ? FieldType::tableColumns((array) ($field->field_options ?? [])) : [];
                        @endphp
                        <div @class(['sm:col-span-2' => in_array($type, [FieldType::TEXTAREA, FieldType::TABLE_INPUT, FieldType::MULTI_IMAGE, FieldType::WAIVER_SCAN], true)])>
                            <p class="text-xs font-medium text-gray-400">{{ $field->field_label }}</p>
                            @if ($isImagePath || $isDataUri)
                                <x-admin.zoomable-image :src="$isDataUri ? $value : asset('storage/'.$value)" :alt="$field->field_label"
                                     class="mt-2 max-h-40 rounded-lg border border-gray-200 object-contain dark:border-gray-700" />
                            @elseif ($isFilePath)
                                <a href="{{ asset('storage/'.$value) }}" target="_blank" class="mt-1 inline-block text-brand-500 hover:underline">Open file</a>
                            @elseif ($type === FieldType::MULTI_IMAGE && is_array($value))
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @forelse ($value as $photo)
                                        <x-admin.zoomable-image :src="asset('storage/'.$photo)" alt="Photo" class="h-24 w-24 rounded-lg border border-gray-200 object-cover dark:border-gray-700" />
                                    @empty
                                        <span class="text-gray-800 dark:text-white/90">—</span>
                                    @endforelse
                                </div>
                            @elseif ($type === FieldType::WAIVER_SCAN && is_array($value))
                                {{-- One or more scanned waivers, each stored as a path or (unresubmitted) data URI. --}}
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @forelse ($value as $waiver)
                                        @continue(! is_string($waiver) || $waiver === '')
                                        <x-admin.zoomable-image :src="str_starts_with($waiver, 'data:image') ? $waiver : asset('storage/'.$waiver)" alt="Scanned waiver" class="h-24 w-24 rounded-lg border border-gray-200 object-cover dark:border-gray-700" />
                                    @empty
                                        <span class="text-gray-800 dark:text-white/90">—</span>
                                    @endforelse
                                </div>
                            @elseif ($type === FieldType::TABLE_INPUT && count($tableCols))
                                <div class="mt-1 overflow-x-auto">
                                    <table class="w-full border-collapse text-xs">
                                        <thead><tr class="border-b border-gray-200 dark:border-gray-700">
                                            @foreach ($tableCols as $col)<th class="px-2 py-1 text-left text-gray-500">{{ $col['label'] }}</th>@endforeach
                                        </tr></thead>
                                        <tbody>
                                            @forelse ((array) $value as $row)
                                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                                    @foreach ($tableCols as $col)<td class="px-2 py-1 text-gray-800 dark:text-white/90">{{ $row[$col['key']] ?? '' }}</td>@endforeach
                                                </tr>
                                            @empty
                                                <tr><td colspan="{{ count($tableCols) }}" class="px-2 py-1 text-gray-400">—</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            @elseif (in_array($type, [FieldType::ORG_SELECT, FieldType::EVENT_SELECT, FieldType::WORKPLAN_EVENTS], true))
                                <p class="mt-1 text-gray-800 dark:text-white/90">{{ \App\Forms\SpecialFieldLabel::forField($type, $value) ?: '—' }}</p>
                            @elseif (is_array($value))
                                <p class="mt-1 whitespace-pre-wrap text-gray-800 dark:text-white/90">{{ implode(', ', array_map('strval', $value)) ?: '—' }}</p>
                            @else
                                <p class="mt-1 whitespace-pre-wrap text-gray-800 dark:text-white/90">{{ ($value === null || $value === '') ? '—' : $value }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                Submission data not found.
            </div>
        @endif

        {{-- Decision Panel --}}
        @if ($approval)
            <div class="rounded-2xl border border-gray-200 bg-white px-6 py-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm font-semibold text-gray-700 dark:text-white/90">Decision</p>
                <div class="mt-3 flex items-center gap-3">
                    @if ($approval->is_rejected)
                        <span class="inline-flex items-center rounded-full bg-error-50 px-3 py-1 text-sm font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400">Rejected</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-success-50 px-3 py-1 text-sm font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">Approved</span>
                    @endif
                    @if ($approval->rejection_reason)
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $approval->rejection_reason }}</span>
                    @endif
                </div>
            </div>
        @else
            <div x-data="{ rejectOpen: false }" class="rounded-2xl border border-gray-200 bg-white px-6 py-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="mb-4 text-sm font-semibold text-gray-700 dark:text-white/90">Decision</p>
                <div class="flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('admin.activity-requests.decide', $actionRequest->request_id) }}">
                        @csrf
                        <input type="hidden" name="decision" value="approve" />
                        <button type="submit" class="rounded-lg bg-success-500 px-4 py-2 text-sm font-medium text-white hover:bg-success-600">
                            Approve
                        </button>
                    </form>

                    <div>
                        <button type="button" @click="rejectOpen = !rejectOpen"
                            class="rounded-lg border border-error-300 px-4 py-2 text-sm font-medium text-error-600 hover:bg-error-50 dark:border-error-600 dark:text-error-400 dark:hover:bg-error-900/20">
                            Reject
                        </button>
                        <div x-show="rejectOpen" x-cloak class="mt-3 w-full max-w-md">
                            <form method="POST" action="{{ route('admin.activity-requests.decide', $actionRequest->request_id) }}" class="space-y-3">
                                @csrf
                                <input type="hidden" name="decision" value="reject" />
                                <textarea name="rejection_reason" rows="3" placeholder="Reason for rejection (optional)"
                                    class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-2 focus:ring-brand-500/10 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                                <button type="submit" class="rounded-lg bg-error-500 px-4 py-2 text-sm font-medium text-white hover:bg-error-600">
                                    Confirm Reject
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="pb-2">
            <a href="{{ route('admin.activity-requests.index') }}" class="text-sm text-brand-500 hover:underline">
                ← Back to list
            </a>
        </div>
    </div>
@endsection
