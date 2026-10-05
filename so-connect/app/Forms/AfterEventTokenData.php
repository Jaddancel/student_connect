<?php

namespace App\Forms;

use App\Models\Event;
use App\Models\FormSubmission;
use App\Models\User;
use App\Services\AfterEventReportService;
use App\Support\OrganizationField;
use Illuminate\Support\Carbon;

/**
 * The extra printed-template tokens an After Event Report carries:
 *
 *  - `{{eventinfo.*}}` — the concluded event's own record (name, date, time,
 *    venue, organization), available for every event;
 *  - `{{event.<field_key>}}` — the answers of the New Event form submission the
 *    event was created from, so whatever officers filed when requesting the
 *    event prints on its after-event report. Blank for events that did not
 *    come through the New Event form.
 *
 * Values are built with the same {@see DocxTemplateData} the New Event form
 * itself prints with, then namespaced, so a field prints identically on both.
 */
final class AfterEventTokenData
{
    public const FIELD_PREFIX = 'event.';

    public const INFO_PREFIX = 'eventinfo.';

    /**
     * @return array<string, string>
     */
    public static function infoTokens(): array
    {
        return [
            'name' => 'Event Name',
            'date' => 'Event Date',
            'start_time' => 'Start Time',
            'end_time' => 'End Time',
            'location' => 'Venue / Location',
            'organization' => 'Organization',
            'description' => 'Description',
        ];
    }

    /**
     * Palette tokens: the event record group, then the New Event form's own
     * field tokens (as built by $fieldTokens for that form) re-keyed under
     * `event.`. Universal tokens are excluded — the report's own palette
     * already lists them.
     *
     * @param  callable(iterable): array<int, array<string,mixed>>  $fieldTokens
     * @return array<int, array<string, mixed>>
     */
    public static function paletteTokens(callable $fieldTokens): array
    {
        $tokens = [];
        foreach (self::infoTokens() as $key => $label) {
            $tokens[] = [
                'key' => self::INFO_PREFIX.$key,
                'label' => $label,
                'icon' => in_array($key, ['date', 'start_time', 'end_time'], true) ? 'date' : 'text',
                'group' => 'Event',
            ];
        }

        $newEventForm = SystemFunction::form(SystemFunction::NEW_EVENT);
        if ($newEventForm === null) {
            return $tokens;
        }

        $fields = $newEventForm->fields()->orderBy('field_order')->orderBy('id')->get();
        foreach ($fieldTokens($fields) as $token) {
            $key = (string) ($token['key'] ?? '');
            if ($key === '' || str_starts_with($key, 'profile.')) {
                continue;
            }

            $token['key'] = self::FIELD_PREFIX.$key;
            $token['group'] = 'New Event form';
            if (isset($token['children']) && is_array($token['children'])) {
                $token['children'] = array_map(function (array $child) {
                    $child['key'] = self::FIELD_PREFIX.$child['key'];

                    return $child;
                }, $token['children']);
            }
            $tokens[] = $token;
        }

        return $tokens;
    }

    /**
     * Token values/images for an After Event Report submission.
     *
     * @return array{values: array<string, mixed>, images: array<string, mixed>}
     */
    public function build(FormSubmission $submission, ?string $disk = null): array
    {
        $values = [];
        $images = [];

        $eventId = (int) ($submission->event_id ?? 0);
        $event = $eventId > 0 ? Event::query()->find($eventId) : null;

        foreach (array_keys(self::infoTokens()) as $key) {
            $values[self::INFO_PREFIX.$key] = '';
        }

        if ($event !== null) {
            $detail = $event->detailOfEvent()->first();
            $start = $detail?->start_time ? Carbon::parse($detail->start_time) : null;
            $end = $detail?->end_time ? Carbon::parse($detail->end_time) : null;

            $values[self::INFO_PREFIX.'name'] = (string) ($detail?->name ?? '');
            $values[self::INFO_PREFIX.'date'] = $start?->format('F j, Y') ?? '';
            $values[self::INFO_PREFIX.'start_time'] = $start?->format('g:i A') ?? '';
            $values[self::INFO_PREFIX.'end_time'] = $end?->format('g:i A') ?? '';
            $values[self::INFO_PREFIX.'location'] = (string) ($detail?->location ?? '');
            $values[self::INFO_PREFIX.'description'] = (string) ($detail?->desc_text ?? '');
            $values[self::INFO_PREFIX.'organization'] = (string) (OrganizationField::value(
                $event->organizationOfEvent()->first(),
                'org_name',
            ) ?? '');
        }

        $newEventForm = SystemFunction::form(SystemFunction::NEW_EVENT);
        $fields = $newEventForm?->fields()->orderBy('field_order')->get() ?? collect();

        $source = $event !== null
            ? app(AfterEventReportService::class)->sourceSubmission((int) $event->getKey())
            : null;

        // Every New Event token is emitted (blank when there is no source) so
        // the template never prints raw placeholders.
        $submitter = $source?->submitted_by ? User::query()->find((int) $source->submitted_by) : null;
        $data = app(DocxTemplateData::class)->build(
            (array) ($source?->payload ?? []),
            $fields,
            $submitter?->profile()->first(),
            OrganizationField::resolveOrganization($submitter),
            $disk,
        );

        foreach ($data['values'] as $key => $value) {
            if (! str_starts_with((string) $key, 'profile.')) {
                $values[self::FIELD_PREFIX.$key] = $value;
            }
        }
        foreach ($data['images'] as $key => $value) {
            if (! str_starts_with((string) $key, 'profile.')) {
                $images[self::FIELD_PREFIX.$key] = $value;
            }
        }

        return ['values' => $values, 'images' => $images];
    }
}
