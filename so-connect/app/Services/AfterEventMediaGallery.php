<?php

namespace App\Services;

use App\Forms\FieldType;
use App\Forms\SystemFunction;
use App\Models\Approval;
use App\Models\FormSubmission;
use App\Models\Request as ActionRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Photos uploaded through After Event Reports, offered to the admin post editor.
 *
 * Only the form bound to the After Event Report system function contributes.
 * A submission qualifies when a request filed for it carries a final,
 * non-rejected approval (stage null or "admin"), or when no request was ever
 * filed for it. Pending and rejected submissions are left out.
 */
class AfterEventMediaGallery
{
    public const DEFAULT_LIMIT = 300;

    private const MEDIA_FIELD_TYPES = [FieldType::IMAGE, FieldType::FILE, FieldType::MULTI_IMAGE];

    /** Formats browsers can render and the post image picker accepts. */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private const BATCH_SIZE = 200;

    /**
     * Newest first.
     *
     * @return Collection<int, array{path:string, url:string, event:string, field:string, organization_id:int|null, organization:string, submitted_at:string|null, status:string}>
     */
    public function items(int $limit = self::DEFAULT_LIMIT): Collection
    {
        $items = collect();

        $this->scan(null, function (array $item) use ($items, $limit) {
            $items->push($item);

            return $items->count() < $limit;
        });

        return $items;
    }

    public function allows(string $path): bool
    {
        if (! str_starts_with($path, 'form-uploads/')) {
            return false;
        }

        $found = false;
        $this->scan($path, function (array $item) use ($path, &$found) {
            $found = $item['path'] === $path;

            return ! $found;
        });

        return $found;
    }

    /**
     * Walk eligible media newest first, calling $onItem until it returns false.
     */
    private function scan(?string $onlyPath, callable $onItem): void
    {
        $form = SystemFunction::form(SystemFunction::AFTER_EVENT_REPORT);
        $fields = $form?->fields()->whereIn('field_type', self::MEDIA_FIELD_TYPES)->get() ?? collect();

        if ($fields->isEmpty()) {
            return;
        }

        $query = FormSubmission::query()->where('form_id', (int) $form->getKey());

        if ($onlyPath !== null) {
            // Uploads get random names, so the file name pins the submission.
            $needle = addcslashes(basename($onlyPath), '%_\\');
            $query->where('payload', 'like', '%'.$needle.'%');
        }

        $organizationNames = [];
        $eventNames = [];
        $seen = [];
        $disk = Storage::disk('public');

        $query->lazyByIdDesc(self::BATCH_SIZE, 'form_submission_id')
            ->chunk(self::BATCH_SIZE)
            ->each(function ($batch) use ($fields, $onlyPath, $onItem, $disk, &$organizationNames, &$eventNames, &$seen) {
                $statuses = $this->eligibleStatuses($batch);
                $this->rememberOrganizationNames($batch, $organizationNames);
                $this->rememberEventNames($batch, $eventNames);

                foreach ($batch as $submission) {
                    $status = $statuses[(int) $submission->getKey()] ?? null;
                    if ($status === null) {
                        continue;
                    }

                    $payload = (array) ($submission->payload ?? []);
                    $eventId = (int) ($submission->event_id ?? 0);

                    foreach ($fields as $field) {
                        $value = $payload[$field->field_key] ?? null;
                        $paths = $field->field_type === FieldType::MULTI_IMAGE ? (array) $value : [$value];

                        foreach ($paths as $path) {
                            if (! is_string($path) || $path === '' || isset($seen[$path])) {
                                continue;
                            }
                            if ($onlyPath !== null && $path !== $onlyPath) {
                                continue;
                            }

                            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                            if (! in_array($extension, self::IMAGE_EXTENSIONS, true) || ! $disk->exists($path)) {
                                continue;
                            }

                            $seen[$path] = true;
                            $organizationId = $submission->organization_id ? (int) $submission->organization_id : null;

                            $keepGoing = $onItem([
                                'path' => $path,
                                'url' => '/storage/'.ltrim($path, '/'),
                                'event' => $eventNames[$eventId] ?? 'Untitled event',
                                'field' => (string) $field->field_label,
                                'organization_id' => $organizationId,
                                'organization' => $organizationNames[$organizationId] ?? 'No organization',
                                'submitted_at' => $submission->submitted_at?->toIso8601String(),
                                'status' => $status,
                            ]);

                            if ($keepGoing === false) {
                                return false;
                            }
                        }
                    }
                }

                return true;
            });
    }

    /**
     * Map each eligible submission id to "approved" or "submitted" (never filed
     * for approval). Pending and rejected submissions are omitted.
     *
     * @param  iterable<FormSubmission>  $submissions
     * @return array<int, string>
     */
    private function eligibleStatuses(iterable $submissions): array
    {
        $formIdBySubmission = [];
        foreach ($submissions as $submission) {
            $formIdBySubmission[(int) $submission->getKey()] = (int) $submission->form_id;
        }

        if ($formIdBySubmission === []) {
            return [];
        }

        $requestIdsBySubmission = [];
        ActionRequest::query()
            ->whereIn('payload->submission_id', array_keys($formIdBySubmission))
            ->get(['request_id', 'form_id', 'payload'])
            ->each(function (ActionRequest $request) use ($formIdBySubmission, &$requestIdsBySubmission) {
                $submissionId = (int) (((array) ($request->payload ?? []))['submission_id'] ?? 0);
                if (! isset($formIdBySubmission[$submissionId])) {
                    return;
                }

                // Ignore requests filed for another form that reuse the same id.
                if ($request->form_id !== null && (int) $request->form_id !== $formIdBySubmission[$submissionId]) {
                    return;
                }

                $requestIdsBySubmission[$submissionId][] = (int) $request->getKey();
            });

        $requestIds = array_merge([], ...array_values($requestIdsBySubmission));
        $approvedRequestIds = $requestIds === []
            ? []
            : Approval::query()
                ->whereIn('request', $requestIds)
                ->where(fn ($q) => $q->whereNull('stage')->orWhere('stage', 'admin'))
                ->where('is_rejected', false)
                ->pluck('request')
                ->mapWithKeys(fn ($id) => [(int) $id => true])
                ->all();

        $statuses = [];
        foreach (array_keys($formIdBySubmission) as $submissionId) {
            $filed = $requestIdsBySubmission[$submissionId] ?? [];

            if ($filed === []) {
                $statuses[$submissionId] = 'submitted';
            } elseif (array_filter($filed, fn (int $id) => isset($approvedRequestIds[$id])) !== []) {
                $statuses[$submissionId] = 'approved';
            }
        }

        return $statuses;
    }

    /**
     * @param  iterable<FormSubmission>  $submissions
     * @param  array<int, string>  $names
     */
    private function rememberEventNames(iterable $submissions, array &$names): void
    {
        $missing = [];
        foreach ($submissions as $submission) {
            $id = (int) ($submission->event_id ?? 0);
            if ($id > 0 && ! isset($names[$id])) {
                $missing[$id] = $id;
            }
        }

        if ($missing === []) {
            return;
        }

        DB::table('events as e')
            ->join('event_details as ed', 'ed.event_detail_id', '=', 'e.event_detail')
            ->whereIn('e.event_id', array_values($missing))
            ->get(['e.event_id', 'ed.name'])
            ->each(function ($row) use (&$names) {
                $names[(int) $row->event_id] = (string) $row->name;
            });
    }

    /**
     * @param  iterable<FormSubmission>  $submissions
     * @param  array<int, string>  $names
     */
    private function rememberOrganizationNames(iterable $submissions, array &$names): void
    {
        $missing = [];
        foreach ($submissions as $submission) {
            $id = (int) ($submission->organization_id ?? 0);
            if ($id > 0 && ! isset($names[$id])) {
                $missing[$id] = $id;
            }
        }

        if ($missing === []) {
            return;
        }

        DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->whereIn('o.organization_id', array_values($missing))
            ->get(['o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as org_name")])
            ->each(function ($row) use (&$names) {
                $names[(int) $row->organization_id] = (string) $row->org_name;
            });
    }
}
