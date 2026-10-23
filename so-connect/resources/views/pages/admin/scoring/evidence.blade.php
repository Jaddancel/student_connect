@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Scoring Evidence" />

    @php
        $raw = (array) ($score->raw_scores ?? []);
        $baseScore = (float) ($raw['total'] ?? $score->total_weighted_score);
        $adjustment = (float) ($raw['manual_adjustment'] ?? 0);
        $attachmentUrl = function (string $path): string {
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
                return $path;
            }
            return \Illuminate\Support\Facades\Storage::disk((string) config('documents.disk', 'public'))->url($path);
        };
    @endphp

    <div class="space-y-5">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $organizationName }}</h2>
                    <p class="text-sm text-gray-500">{{ $score->semester?->name }} · Rubric v{{ $score->rubricVersion?->version ?? 'legacy' }}</p>
                    <p class="mt-2 max-w-3xl text-xs text-gray-500">
                        Points below show the exact records matched by the scoring rules. Quality checks flag weak or missing files; they do not claim document authenticity.
                    </p>
                </div>
                <a href="{{ route('admin.scoring.edit', $score->organization_score_id) }}"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
                    Back to score
                </a>
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/[0.04]">
                    <p class="text-xs text-gray-500">Base weighted score</p>
                    <p class="mt-1 text-xl font-bold text-gray-800 dark:text-white">{{ number_format($baseScore, 2) }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/[0.04]">
                    <p class="text-xs text-gray-500">Manual adjustment</p>
                    <p class="mt-1 text-xl font-bold {{ $adjustment < 0 ? 'text-error-600' : 'text-success-600' }}">{{ $adjustment >= 0 ? '+' : '' }}{{ number_format($adjustment, 2) }}</p>
                </div>
                <div class="rounded-xl bg-brand-50 p-3 dark:bg-brand-500/10">
                    <p class="text-xs text-brand-600 dark:text-brand-300">Final score</p>
                    <p class="mt-1 text-xl font-bold text-brand-700 dark:text-brand-200">{{ number_format((float) $score->total_weighted_score, 2) }}</p>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:bg-success-500/10 dark:text-success-300">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:bg-error-500/10 dark:text-error-300">{{ $errors->first() }}</div>
        @endif

        @forelse ($evidenceByCriterion as $criterionKey => $items)
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $items->first()->criterion_label }}</h3>
                    <span class="text-xs font-medium text-gray-500">{{ $items->count() }} source record(s) · {{ number_format((float) $items->sum('awarded_points'), 2) }} awarded points</span>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($items as $evidence)
                        @php
                            $qualityClass = match ($evidence->quality_status) {
                                'good' => 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300',
                                'poor' => 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-300',
                                default => 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300',
                            };
                            $reviewClass = match ($evidence->review_status) {
                                'verified' => 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300',
                                'rejected' => 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-300',
                                default => 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300',
                            };
                        @endphp
                        <div class="grid gap-5 p-5 lg:grid-cols-[1fr_360px]">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($evidence->evidence_url)
                                        <a href="{{ $evidence->evidence_url }}" class="font-semibold text-brand-600 hover:underline dark:text-brand-400">{{ $evidence->evidence_label }}</a>
                                    @else
                                        <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $evidence->evidence_label }}</span>
                                    @endif
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $qualityClass }}">
                                        Quality: {{ str_replace('_', ' ', $evidence->quality_status) }}{{ $evidence->quality_score !== null ? ' · '.$evidence->quality_score.'%' : '' }}
                                    </span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $reviewClass }}">{{ ucfirst($evidence->review_status) }}</span>
                                </div>
                                <p class="mt-2 text-xs text-gray-500">
                                    {{ $evidence->instances }} instance(s) × rule weight = {{ number_format((float) $evidence->rule_points, 2) }} rule points
                                    @if ((float) $evidence->rule_points !== (float) $evidence->awarded_points)
                                        · {{ number_format((float) $evidence->awarded_points, 2) }} awarded after category cap
                                    @endif
                                    @if ($evidence->override_points !== null)
                                        · effective {{ number_format($evidence->effectivePoints(), 2) }} points
                                    @endif
                                </p>
                                <ul class="mt-3 space-y-1 text-xs text-gray-500">
                                    @foreach ((array) $evidence->quality_notes as $note)
                                        <li>• {{ $note }}</li>
                                    @endforeach
                                </ul>
                                @if (!empty($evidence->attachment_paths))
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($evidence->attachment_paths as $path)
                                            <a href="{{ $attachmentUrl($path) }}" target="_blank" rel="noopener"
                                                class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-brand-600 hover:bg-brand-50 dark:border-gray-700 dark:text-brand-400">
                                                Attachment: {{ basename(parse_url($path, PHP_URL_PATH) ?: $path) }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                                @if ($evidence->reviewed_at)
                                    @php
                                        $reviewerName = $evidence->reviewer?->profileRecord
                                            ? trim($evidence->reviewer->profileRecord->first_name.' '.$evidence->reviewer->profileRecord->last_name)
                                            : null;
                                    @endphp
                                    <p class="mt-3 text-[11px] text-gray-400">Last reviewed {{ $evidence->reviewed_at->format('M j, Y g:i A') }}{{ $reviewerName ? ' by '.$reviewerName : '' }}</p>
                                @endif
                            </div>

                            <form method="POST" action="{{ route('admin.scoring.evidence.review', [$score, $evidence]) }}" class="space-y-3 rounded-xl bg-gray-50 p-4 dark:bg-white/[0.04]">
                                @csrf @method('PATCH')
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Manual review</label>
                                    <select name="review_status" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                        @foreach (['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected'] as $value => $label)
                                            <option value="{{ $value }}" @selected($evidence->review_status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-[10px] text-gray-400">Rejecting evidence removes its awarded points unless you enter a different override.</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Override points (optional)</label>
                                    <input type="number" name="override_points" min="0" max="999.99" step="0.01" value="{{ $evidence->override_points }}"
                                        placeholder="Keep automatic points" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Reason</label>
                                    <textarea name="override_reason" rows="2" maxlength="2000" placeholder="Required for rejection or override"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">{{ $evidence->override_reason }}</textarea>
                                </div>
                                <button type="submit" class="w-full rounded-lg bg-brand-500 px-4 py-2.5 text-xs font-semibold text-white hover:bg-brand-600">Save review</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-white/[0.03]">
                <p class="font-medium text-gray-700 dark:text-gray-200">No scoring evidence snapshot is attached to this score.</p>
                <p class="mt-1 text-xs text-gray-500">Legacy scores remain visible, but new scores will capture their matched source records automatically.</p>
            </div>
        @endforelse
    </div>
@endsection
