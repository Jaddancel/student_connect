@extends('layouts.app')

@php
    use App\Forms\FieldType;
    use App\Forms\SubmissionPresenter;
@endphp

@section('content')
    <x-common.page-breadcrumb :pageTitle="'Review · '.$form->name" />

    <div class="max-w-4xl space-y-6">
        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Meta --}}
        <div class="rounded-2xl border border-gray-200 bg-white px-6 py-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $kind === 'Membership' ? "Requester's Organization" : 'Organization' }}</p>
                    <p class="mt-1 text-gray-800 dark:text-white/90">{{ $orgName }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Submitted By</p>
                    <p class="mt-1 text-gray-800 dark:text-white/90">{{ $requesterName }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Submitted At</p>
                    <p class="mt-1 text-gray-800 dark:text-white/90">{{ $actionRequest->requested_at->timezone(config('app.display_timezone'))->format('M d, Y h:i A') }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Kind</p>
                    <p class="mt-1 text-gray-800 dark:text-white/90">{{ $kind }}</p>
                </div>
            </div>

            @if ($newOrganizationEmailStatuses !== [])
                <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                    @foreach ($newOrganizationEmailStatuses as $role => $emailStatus)
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">New {{ ucfirst($role) }} Email</p>
                        <p class="mt-1 text-sm text-gray-800 dark:text-white/90">{{ $emailStatus['email'] }}</p>
                        @if ($emailStatus['registered'])
                            <p class="mt-1 text-sm">The user is <a href="{{ route('admin.form-requests.index', $form) }}" class="font-medium text-brand-500 underline">registered</a> in the system.</p>
                        @else
                            <p class="mt-1 text-sm text-warning-600 dark:text-warning-400">This email is not yet registered. An invitation will be sent upon approval.</p>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Submitted answers, labelled by the form's own fields --}}
        @if ($submission)
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Submitted Answers</h3>
                </div>
                <div class="grid grid-cols-1 gap-x-6 gap-y-4 px-6 py-5 text-sm sm:grid-cols-2">
                    @foreach ($fields as $field)
                        @continue(FieldType::isPresentational($field->field_type))
                        {{-- Passwords are stored hashed and never shown. --}}
                        @continue($field->field_type === FieldType::PASSWORD)
                        @php
                            $type = $field->field_type;
                            $value = $submissionPayload[$field->field_key] ?? null;
                            // Photos, files, captured signatures and scanned waivers all
                            // resolve to the same shape, and each opens full size.
                            $attachments = SubmissionPresenter::attachments($submissionPayload, $field->field_key, $type);
                            $tableCols = match ($type) {
                                FieldType::TABLE_INPUT => FieldType::tableColumns((array) ($field->field_options ?? [])),
                                FieldType::ACTIVITY_TABLE => FieldType::activityTableColumns((array) ($field->field_options ?? [])),
                                default => [],
                            };
                        @endphp
                        <div @class(['sm:col-span-2' => in_array($type, [FieldType::TEXTAREA, FieldType::TABLE_INPUT, FieldType::ACTIVITY_TABLE, FieldType::MULTI_IMAGE, FieldType::WAIVER_SCAN], true)
                            || count($attachments) > 1])>
                            <p class="text-xs font-medium text-gray-400">{{ $field->field_label }}</p>
                            @if (SubmissionPresenter::holdsAttachments($type))
                                <x-admin.attachments :items="$attachments" :label="$field->field_label" />
                            @elseif (in_array($type, [FieldType::TABLE_INPUT, FieldType::ACTIVITY_TABLE], true) && count($tableCols))
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

            {{-- Files the submission carries that no field owns: the ID-scan
                 wizard's front/back captures ride along beside the fields, and
                 the admin has to be able to see the ID that was photographed. --}}
            @php
                $extraAttachments = SubmissionPresenter::orphanAttachments(
                    $submissionPayload,
                    $fields->pluck('field_key')->map(fn ($key) => (string) $key)->all(),
                );
            @endphp
            @if ($extraAttachments !== [])
                <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Attachments</h3>
                    </div>
                    <div class="grid grid-cols-1 gap-x-6 gap-y-4 px-6 py-5 text-sm sm:grid-cols-2">
                        @foreach ($extraAttachments as $label => $items)
                            <div>
                                <p class="text-xs font-medium text-gray-400">{{ $label }}</p>
                                <x-admin.attachments :items="$items" :label="$label" />
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @else
            <div class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                No form submission is attached to this request.
            </div>
        @endif

        {{-- Completed-by-hand scan provenance, when the request came from a
             manual-filling draft. --}}
        <x-admin.manual-source :payload="$submissionPayload" />

        {{-- President's decision provenance: membership requests are approved by
             the org president before this admin stage, so show when they did. --}}
        @if (!empty($presidentApproval))
            <div class="rounded-2xl border border-gray-200 bg-white px-6 py-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm font-semibold text-gray-700 dark:text-white/90">President Approval</p>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    @if ($presidentApproval->is_rejected)
                        <span class="inline-flex items-center rounded-full bg-error-50 px-3 py-1 text-sm font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400">Rejected by President</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-success-50 px-3 py-1 text-sm font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">Approved by President</span>
                    @endif
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $presidentApproval->approved_at->timezone(config('app.display_timezone'))->format('M d, Y h:i A') }}</span>
                    @if ($presidentApproval->rejection_reason)
                        <span class="text-sm text-gray-500 dark:text-gray-400">· {{ $presidentApproval->rejection_reason }}</span>
                    @endif
                </div>
            </div>
        @endif

        {{-- Decision --}}
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
                <p class="mb-1 text-sm font-semibold text-gray-700 dark:text-white/90">Decision</p>
                <p class="mb-4 text-xs text-gray-400 dark:text-gray-500">
                    {{ $kind === 'Membership'
                        ? 'Approving registers the requester as a member of the organization.'
                        : 'Approving generates the printed document; rejecting sends the requester back to the form.' }}
                </p>
                <div class="flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('admin.form-requests.decide', [$form, $actionRequest->request_id]) }}">
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
                            <form method="POST" action="{{ route('admin.form-requests.decide', [$form, $actionRequest->request_id]) }}" class="space-y-3">
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
            <a href="{{ route('admin.form-requests.index', $form) }}" class="text-sm text-brand-500 hover:underline">
                ← Back to {{ $form->name }} requests
            </a>
        </div>
    </div>
@endsection
