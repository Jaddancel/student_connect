<?php

namespace App\Forms;

use App\Models\Form;
use App\Models\Semester;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The optional per-semester cap on accepted submissions of a form
 * (forms.semester_submission_limit), counted globally across organizations.
 *
 * "Accepted" = the request filed against the form (requests.form_id) carries a
 * final, non-rejected approval (stage null, or the "admin" stage of a two-stage
 * flow). Pending and rejected submissions never consume the quota. The window
 * is the current semester, from its prep (vacation) period through the day
 * before the next semester starts — mirroring {@see Semester::current()}. With
 * no semester configured the limit is not enforced.
 */
final class SemesterSubmissionLimit
{
    /**
     * Null when the form has no limit or no semester is configured.
     *
     * @return array{limit:int, accepted:int, remaining:int, reached:bool, semester:Semester}|null
     */
    public static function status(Form $form): ?array
    {
        $limit = (int) ($form->semester_submission_limit ?? 0);
        if ($limit <= 0 || ! $form->exists) {
            return null;
        }

        $semester = Semester::current();
        if ($semester === null) {
            return null;
        }

        $accepted = self::acceptedCount($form, $semester);

        return [
            'limit' => $limit,
            'accepted' => $accepted,
            'remaining' => max(0, $limit - $accepted),
            'reached' => $accepted >= $limit,
            'semester' => $semester,
        ];
    }

    public static function acceptedCount(Form $form, Semester $semester): int
    {
        $start = $semester->activePeriodStart()->copy()->startOfDay();
        $end = $semester->endsAt()?->copy()->endOfDay();

        return DB::table('requests as r')
            ->where('r.form_id', (int) $form->getKey())
            ->where('r.requested_at', '>=', $start)
            ->when($end !== null, fn ($q) => $q->where('r.requested_at', '<=', $end))
            ->whereExists(function ($q) {
                $q->from('approvals as a')
                    ->whereColumn('a.request', 'r.request_id')
                    ->where(fn ($s) => $s->whereNull('a.stage')->orWhere('a.stage', 'admin'))
                    ->where('a.is_rejected', false);
            })
            ->count();
    }

    public static function blockedMessage(Form $form, array $status): string
    {
        $semester = $status['semester'];

        return '"'.$form->name.'" has reached its limit of '.$status['limit'].' accepted '
            .($status['limit'] === 1 ? 'submission' : 'submissions')
            .' for the '.$semester->semesterLabel().' Semester of '.$semester->schoolYear()
            .'. New submissions are closed until the next semester.';
    }

    /**
     * @throws ValidationException when the form's semester quota is used up
     */
    public static function assertOpen(Form $form): void
    {
        $status = self::status($form);
        if ($status !== null && $status['reached']) {
            throw ValidationException::withMessages(['form' => self::blockedMessage($form, $status)]);
        }
    }
}
