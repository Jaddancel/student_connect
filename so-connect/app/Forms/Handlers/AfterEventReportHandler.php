<?php

namespace App\Forms\Handlers;

use App\Forms\FieldType;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\AfterEventReportService;
use App\Services\DocumentGenerationService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * After Event Report: filed by an organization official for one concluded
 * event (carried as the hidden `after_event_event_id` input the renderer adds
 * for `?event=`). Unlike the other system functions there is no approval step —
 * the printed document is generated right away and the event is marked filed.
 * An event takes exactly one report: a second attempt is sent back to the
 * After Event Form page with an "already filed" dialog.
 */
class AfterEventReportHandler implements SystemFunctionHandler
{
    public const EVENT_INPUT = 'after_event_event_id';

    public function __construct(private readonly AfterEventReportService $afterEvents) {}

    public function validatePayload(Form $form, array $payload, Request $request): void
    {
        try {
            $this->afterEvents->assertFileable($request->user(), $this->eventId($request));
        } catch (ValidationException $exception) {
            $this->redirectIfAlreadyFiled($exception);
            throw $exception;
        }
    }

    public function handle(Form $form, FormSubmission $submission, array $payload, Request $request): RedirectResponse
    {
        $eventId = $this->eventId($request);

        try {
            $event = DB::transaction(function () use ($request, $eventId, $submission) {
                // Serialise concurrent filings for one event so only the first claims it.
                Event::query()->whereKey($eventId)->lockForUpdate()->first();

                $event = $this->afterEvents->assertFileable($request->user(), $eventId, (int) $submission->getKey());

                $submission->forceFill([
                    'event_id' => (int) $event['event_id'],
                    'organization_id' => (int) $event['organization_id'],
                ])->save();

                return $event;
            });
        } catch (ValidationException $exception) {
            $this->discard($form, $submission);
            $this->redirectIfAlreadyFiled($exception);
            throw $exception;
        }

        try {
            app(DocumentGenerationService::class)->generateAllFromSubmission(
                $submission,
                null,
                (int) $request->user()->getKey(),
            );
        } catch (\Throwable $throwable) {
            report($throwable);
            $this->discard($form, $submission);

            return redirect()
                ->route('forms.render', ['routeName' => $form->route_name, 'event' => $event['event_id']])
                ->withErrors(['form' => 'The after-event report could not be generated. Please try again. ('.$throwable->getMessage().')']);
        }

        return redirect()
            ->route('after-event-reports.index')
            ->with('success', 'After-event report for "'.$event['name'].'" filed. Its document is ready.');
    }

    /**
     * A duplicate report goes back to the After Event Form page, which shows
     * the error as a dialog, instead of back to the form.
     */
    private function redirectIfAlreadyFiled(ValidationException $exception): void
    {
        if (array_key_exists(AfterEventReportService::ERROR_ALREADY_FILED, $exception->errors())) {
            throw new HttpResponseException(
                redirect()->route('after-event-reports.index')->withErrors($exception->errors())
            );
        }
    }

    private function eventId(Request $request): int
    {
        $eventId = (int) $request->input(self::EVENT_INPUT, 0);
        if ($eventId <= 0) {
            throw ValidationException::withMessages([
                'form' => 'Open the after-event report from the After Event Form page so it is tied to an event.',
            ]);
        }

        return $eventId;
    }

    /**
     * Drop a submission whose document failed to generate (and its uploads),
     * so the event stays unfiled and the official can retry.
     */
    private function discard(Form $form, FormSubmission $submission): void
    {
        $disk = Storage::disk((string) config('documents.disk', 'public'));
        $payload = (array) $submission->payload;

        foreach ($form->fields as $field) {
            $type = (string) $field->field_type;
            if (! FieldType::isFileLike($type) && $type !== FieldType::MULTI_IMAGE && $type !== FieldType::SIGNATURE) {
                continue;
            }
            foreach ((array) ($payload[$field->field_key] ?? []) as $path) {
                if (is_string($path) && str_starts_with($path, 'form-uploads/')) {
                    $disk->delete($path);
                }
            }
        }

        $submission->generatedDocuments()->delete();
        $submission->delete();
    }
}
