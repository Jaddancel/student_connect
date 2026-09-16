<?php

namespace App\Http\Controllers\Admin;

use App\Forms\FieldType;
use App\Http\Controllers\Controller;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use Illuminate\View\View;

/**
 * Type-2 review of scanned-waiver submissions: lists submissions of forms that
 * carry a WAIVER_SCAN field with the authoritative server re-validation verdict
 * (valid / needs review / unvalidated) recorded at submit.
 */
class WaiverReviewController extends Controller
{
    public function index(): View
    {
        $waiverFormIds = FormDescription::query()
            ->where('field_type', FieldType::WAIVER_SCAN)
            ->pluck('form_id')
            ->unique()
            ->values();

        $submissions = FormSubmission::query()
            ->whereIn('form_id', $waiverFormIds)
            ->with('form:id,name')
            ->orderByDesc('form_submission_id')
            ->limit(100)
            ->get(['form_submission_id', 'form_id', 'submitted_by', 'payload', 'submitted_at']);

        $rows = $submissions->map(function (FormSubmission $submission) {
            $validation = (array) (($submission->payload ?? [])['_waiver_validation'] ?? []);
            $verdict = 'unvalidated';
            if ($validation !== []) {
                $first = reset($validation);
                // New submissions store a list of per-item validations per
                // field (one waiver can be many); legacy ones stored a single
                // validation object directly for the field.
                $items = array_is_list($first) ? $first : [$first];
                $verdicts = array_column($items, 'valid');
                $verdict = in_array(false, $verdicts, true)
                    ? 'needs_review'
                    : (in_array(true, $verdicts, true) ? 'valid' : 'unvalidated');
            }

            return [
                'id' => (int) $submission->form_submission_id,
                'form' => $submission->form?->name ?? 'Form',
                'submitted_at' => $submission->submitted_at,
                'verdict' => $verdict,
            ];
        });

        return view('pages.admin.waiver-review.index', [
            'title' => 'Waiver Review',
            'rows' => $rows,
        ]);
    }
}
