<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BestOrganizationCandidate;
use App\Models\BestOrganizationCycle;
use App\Models\OrganizationScore;
use App\Models\Semester;
use App\Services\ActionLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BestOrganizationController extends Controller
{
    public function index()
    {
        return view('pages.admin.best-organizations.index', [
            'title' => 'Best Organization Selection',
            'cycles' => BestOrganizationCycle::query()->with(['semester', 'winner.detailRecord'])->withCount('candidates')->latest()->get(),
            'semesters' => Semester::query()->whereDoesntHave('bestOrganizationCycle')->orderByDesc('starts_at')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => ['required', 'integer', Rule::exists('semesters', 'semester_id'), Rule::unique('best_organization_cycles', 'semester_id')],
            'title' => ['required', 'string', 'max:255'],
        ]);
        $cycle = BestOrganizationCycle::create($validated + ['status' => BestOrganizationCycle::DRAFT, 'created_by' => $request->user()->getKey()]);
        $this->audit('cycle_created', 'Created Best Organization selection cycle', $cycle);

        return redirect()->route('admin.best-organizations.show', $cycle)->with('success', 'Selection cycle created. Sync the verified scores to add candidates.');
    }

    public function show(BestOrganizationCycle $cycle)
    {
        $cycle->load(['semester', 'winner.detailRecord', 'candidates.organization.detailRecord', 'candidates.score', 'candidates.reviewer.profileRecord']);

        return view('pages.admin.best-organizations.show', ['title' => $cycle->title, 'cycle' => $cycle]);
    }

    public function sync(Request $request, BestOrganizationCycle $cycle)
    {
        $this->requireStatus($cycle, BestOrganizationCycle::DRAFT, 'Candidates can only be synchronized while the cycle is in draft.');
        $scores = OrganizationScore::query()->with('organization.detailRecord')->where('semester_id', $cycle->semester_id)->orderByDesc('total_weighted_score')->orderBy('organization_id')->get();

        DB::transaction(function () use ($cycle, $scores) {
            $organizationIds = [];
            foreach ($scores as $index => $score) {
                $organizationIds[] = (int) $score->organization_id;
                BestOrganizationCandidate::query()->updateOrCreate(
                    ['cycle_id' => $cycle->id, 'organization_id' => $score->organization_id],
                    ['organization_name_snapshot' => $score->organization?->detailRecord?->name ?? 'Unknown Organization', 'organization_score_id' => $score->getKey(), 'score_snapshot' => $score->total_weighted_score, 'rank_snapshot' => $index + 1],
                );
            }
            $cycle->candidates()->when($organizationIds !== [], fn ($q) => $q->whereNotIn('organization_id', $organizationIds), fn ($q) => $q)->delete();
        });
        $this->audit('candidates_synced', 'Synchronized verified organization scores as award candidates', $cycle, ['candidate_count' => $scores->count()]);

        return back()->with('success', $scores->count().' verified organization score(s) synchronized.');
    }

    public function start(Request $request, BestOrganizationCycle $cycle)
    {
        $this->requireStatus($cycle, BestOrganizationCycle::DRAFT, 'This cycle is no longer in draft.');
        if (! $cycle->candidates()->exists()) {
            throw ValidationException::withMessages(['cycle' => 'Synchronize at least one verified score before starting evaluation.']);
        }
        $cycle->update(['status' => BestOrganizationCycle::EVALUATION, 'evaluation_started_at' => now()]);
        $this->audit('evaluation_started', 'Started Best Organization candidate review', $cycle);

        return back()->with('success', 'Evaluation started. Review candidates and mark the finalists.');
    }

    public function review(Request $request, BestOrganizationCycle $cycle, BestOrganizationCandidate $candidate)
    {
        abort_unless((int) $candidate->cycle_id === (int) $cycle->id, 404);
        $this->requireStatus($cycle, BestOrganizationCycle::EVALUATION, 'Candidate decisions are locked outside evaluation.');
        $validated = $request->validate([
            'status' => ['required', Rule::in([BestOrganizationCandidate::ELIGIBLE, BestOrganizationCandidate::FINALIST, BestOrganizationCandidate::DISQUALIFIED])],
            'review_notes' => ['nullable', 'string', 'max:3000', Rule::requiredIf($request->input('status') === BestOrganizationCandidate::DISQUALIFIED)],
        ]);
        $candidate->update($validated + ['reviewed_by' => $request->user()->getKey(), 'reviewed_at' => now()]);
        $this->audit('candidate_reviewed', 'Reviewed Best Organization candidate', $cycle, ['candidate_id' => $candidate->id, 'organization_id' => $candidate->organization_id, 'decision' => $validated['status']]);

        return back()->with('success', 'Candidate review saved.');
    }

    public function finalize(Request $request, BestOrganizationCycle $cycle)
    {
        $this->requireStatus($cycle, BestOrganizationCycle::EVALUATION, 'Only a cycle under evaluation can be finalized.');
        $validated = $request->validate([
            'candidate_id' => ['required', 'integer', Rule::exists('best_organization_candidates', 'id')->where(fn ($q) => $q->where('cycle_id', $cycle->id)->where('status', BestOrganizationCandidate::FINALIST))],
            'selection_notes' => ['required', 'string', 'min:20', 'max:5000'],
        ]);
        $winner = $cycle->candidates()->findOrFail($validated['candidate_id']);

        DB::transaction(function () use ($cycle, $winner, $validated, $request) {
            $cycle->candidates()->where('status', BestOrganizationCandidate::WINNER)->update(['status' => BestOrganizationCandidate::FINALIST]);
            $winner->update(['status' => BestOrganizationCandidate::WINNER, 'reviewed_by' => $request->user()->getKey(), 'reviewed_at' => now()]);
            $cycle->update([
                'status' => BestOrganizationCycle::AWARDED,
                'winner_organization_id' => $winner->organization_id,
                'winner_name' => $winner->organization_name_snapshot,
                'winner_score' => $winner->score_snapshot,
                'selection_notes' => $validated['selection_notes'],
                'finalized_by' => $request->user()->getKey(),
                'finalized_at' => now(),
            ]);
        });
        $this->audit('winner_awarded', 'Finalized and awarded Best Student Organization', $cycle, ['organization_id' => $winner->organization_id, 'score' => $winner->score_snapshot]);

        return back()->with('success', 'Best Student Organization winner finalized and published.');
    }

    public function results()
    {
        return view('pages.best-organizations.index', [
            'title' => 'Best Student Organizations',
            'cycles' => BestOrganizationCycle::query()->where('status', BestOrganizationCycle::AWARDED)->with(['semester', 'winner.detailRecord'])->orderByDesc('finalized_at')->get(),
        ]);
    }

    private function requireStatus(BestOrganizationCycle $cycle, string $status, string $message): void
    {
        if ($cycle->status !== $status) throw ValidationException::withMessages(['cycle' => $message]);
    }

    private function audit(string $action, string $description, BestOrganizationCycle $cycle, array $meta = []): void
    {
        ActionLogger::log(ActionLogger::CATEGORY_BEST_ORGANIZATION, $action, $description, ['cycle_id' => $cycle->id, 'semester_id' => $cycle->semester_id] + $meta, $cycle);
    }
}
