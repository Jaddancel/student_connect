<?php

namespace App\Services\ManualForm;

use App\Jobs\GenerateManualSessionSchema;
use App\Jobs\PrepareManualFormSession;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\ManualFormSession;
use App\Models\ManualFormSessionDocument;
use App\Services\DocxTemplateService;
use App\Services\FormPrintTemplateService;
use App\Services\PdfRasterizer;
use App\Support\OrganizationField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Owns the manual-filling session lifecycle: authorization for owner and
 * public (token) access, starting a draft (freezing the exact partial PDF and
 * rasterizing it), and the finalization hook that links a session to the
 * submission it produced and records the manual-source provenance.
 */
class ManualFormSessionService
{
    /** Days an abandoned draft (and its files) survives before cleanup. */
    public const RETENTION_DAYS = 30;

    public function __construct(
        private readonly ManualDraftPayloadBuilder $payloadBuilder,
        private readonly ManualSchemaBuilder $schemaBuilder,
        private readonly FormPrintTemplateService $templates,
        private readonly DocxTemplateService $docx,
        private readonly PdfRasterizer $rasterizer,
        private readonly \App\Forms\DocxTemplateData $templateData,
    ) {}

    /**
     * Start a manual-filling draft: validate the supplied values, freeze the
     * exact partial PDF the user will print, rasterize its pages, and dispatch
     * session-schema generation. Returns the session and, for a public form,
     * the one-time raw resume token (never persisted in the clear).
     *
     * @return array{session: ManualFormSession, token: ?string}
     */
    public function start(Form $form, Request $request): array
    {
        $user = $request->user();
        $sessionId = (string) Str::uuid();

        $draft = $this->payloadBuilder->build($form, $request, $sessionId);

        $fields = $this->schemaBuilder->fieldsFor($form);
        $knownKeys = array_keys($draft['known']);
        $extractable = array_values(array_filter(
            $fields,
            fn ($f) => $f['paper_support'] === \App\Forms\FieldType::PAPER_EXTRACT
                && ! in_array($f['key'], $knownKeys, true),
        ));

        $rawToken = null;
        $tokenHash = null;
        if ($user === null) {
            $rawToken = Str::random(48);
            $tokenHash = Hash::make($rawToken);
        }

        $templates = $this->templates->activeTemplates($form);
        if ($templates->isEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'form' => 'This form has no printed template to fill.',
            ]);
        }
        $template = $templates->first();

        $session = ManualFormSession::query()->create([
            'id' => $sessionId,
            'form_id' => (int) $form->getKey(),
            'template_id' => $template?->getKey(),
            'template_version' => $template?->version,
            'user_id' => $user?->getKey(),
            'public_token_hash' => $tokenHash,
            'status' => ManualFormSession::STATUS_PREPARING,
            'draft_payload' => $draft['payload'],
            'known_fields' => $draft['known'],
            'extractable_fields' => $extractable,
            'digital_only_fields' => $this->schemaBuilder->digitalOnlyKeys($fields),
            'baseline_schema' => $template?->manual_schema,
            'expires_at' => now()->addDays(self::RETENTION_DAYS),
        ]);

        foreach ($templates->values() as $position => $printedTemplate) {
            $session->documents()->create([
                'template_id' => $printedTemplate->getKey(),
                'template_version' => $printedTemplate->version,
                'position' => $position,
                'status' => ManualFormSession::STATUS_PREPARING,
                'baseline_schema' => $printedTemplate->manual_schema,
            ]);
        }

        PrepareManualFormSession::dispatch((string) $session->getKey());

        return ['session' => $session, 'token' => $rawToken];
    }

    /**
     * Prepare a queued session outside the web request. OnlyOffice fetches the
     * populated DOCX back through Laravel, so running this in the separate
     * queue worker avoids deadlocking Sail's single web process.
     */
    public function prepare(string $sessionId): void
    {
        $session = ManualFormSession::query()->find($sessionId);
        if ($session === null
            || $session->status !== ManualFormSession::STATUS_PREPARING
            || ($session->documents()->doesntExist() && $session->partial_pdf_path !== null)) {
            return;
        }

        $form = $session->form;
        if ($form === null) {
            $this->failSession($session, 'This form is no longer available.');

            return;
        }

        foreach ($session->documents as $document) {
            if ($document->status !== ManualFormSession::STATUS_PREPARING || $document->partial_pdf_path !== null) {
                continue;
            }
            $this->freezePartialPdf($session, $form, $document->template, (array) $session->known_fields, $session->owner, $document);
        }
    }

    /**
     * Populate the active template with the known values, convert to PDF,
     * store the exact bytes, hash them, and rasterize the pages. On any failure
     * the session is marked failed with a reason (it still shows in Drafts).
     */
    private function freezePartialPdf(
        ManualFormSession $session,
        Form $form,
        ?\App\Models\Template $template,
        array $known,
        ?\App\Models\User $user,
        ?ManualFormSessionDocument $document = null,
    ): void {
        if ($template === null) {
            $this->failPreparation($session, $document, 'This form has no printed template to fill.');

            return;
        }

        $disk = (string) config('documents.disk', 'public');
        $prefix = 'manual-form/'.$session->getKey().($document ? '/documents/'.$document->getKey() : '');
        $pdfRelative = $prefix.'/partial.pdf';
        $pagesDir = Storage::disk($disk)->path($prefix.'/pages');

        try {
            $profile = $user?->profile()->first();
            $organization = OrganizationField::resolveOrganization($user);
            $built = $this->templateData->build(
                $known,
                $form->fields()->get(),
                $profile,
                $organization,
                $disk,
            );

            $docxPath = $this->docx->populate($template, $built['values'], $built['images'], $built['tables']);
            $pdfAbsolute = $this->docx->toPdf($docxPath);

            Storage::disk($disk)->put($pdfRelative, File::get($pdfAbsolute));
            // Keep the populated .docx: session-schema generation re-measures
            // each field's table cell against this exact document so distortions
            // from the real digital-form values are reflected in the writable
            // areas (not just the blank-template baseline).
            Storage::disk($disk)->put(
                $prefix.'/partial.docx',
                File::get($docxPath),
            );
            File::deleteDirectory(dirname($docxPath));

            $raster = $this->rasterizer->rasterize(
                Storage::disk($disk)->path($pdfRelative),
                $pagesDir,
            );
            if (! ($raster['ok'] ?? false)) {
                $this->failPreparation($session, $document, 'Could not prepare the printable pages: '.($raster['error'] ?? 'unknown'));

                return;
            }

            $pageMeta = array_map(fn ($p) => [
                'index' => $p['index'],
                'path' => $prefix.'/pages/'.basename($p['path']),
                'width' => $p['width'],
                'height' => $p['height'],
            ], $raster['pages']);

            ($document ?? $session)->forceFill([
                'partial_pdf_path' => $pdfRelative,
                'partial_pdf_hash' => $raster['hash'] ?? null,
                'page_meta' => $pageMeta,
            ])->save();

            GenerateManualSessionSchema::dispatch((string) $session->getKey(), $document?->getKey());
        } catch (\Throwable $e) {
            report($e);
            $this->failPreparation($session, $document, 'Could not generate the partial form.');
        }
    }

    private function failPreparation(ManualFormSession $session, ?ManualFormSessionDocument $document, string $reason): void
    {
        if ($document === null) {
            $this->failSession($session, $reason);

            return;
        }
        $document->forceFill(['status' => ManualFormSession::STATUS_FAILED, 'parse_error' => $reason])->save();
        $this->syncDocuments($session);
    }

    public function failSession(ManualFormSession $session, string $reason): void
    {
        $session->forceFill([
            'status' => ManualFormSession::STATUS_FAILED,
            'parse_error' => $reason,
        ])->save();
    }

    public function syncDocuments(ManualFormSession $session): void
    {
        $documents = $session->documents()->get();
        if ($documents->isEmpty()) {
            return;
        }

        $statuses = $documents->pluck('status')->all();
        $status = ManualFormSession::STATUS_REVIEW;
        foreach ([ManualFormSession::STATUS_FAILED, ManualFormSession::STATUS_PREPARING,
            ManualFormSession::STATUS_PARSING, ManualFormSession::STATUS_AWAITING_SCAN] as $candidate) {
            if (in_array($candidate, $statuses, true)) {
                $status = $candidate;
                break;
            }
        }
        if (! in_array(ManualFormSession::STATUS_PREPARING, $statuses, true)) {
            $covered = $documents->flatMap(fn ($doc) => collect((array) ($doc->session_schema['extractable_fields'] ?? []))->pluck('key'))->all();
            $missing = collect($this->schemaBuilder->fieldsFor($session->form))
                ->filter(fn ($field) => ($field['required'] ?? false) && ! in_array($field['key'], $covered, true))
                ->filter(fn ($field) => in_array($field['paper_support'] ?? '', [
                    \App\Forms\FieldType::PAPER_EXTRACT, \App\Forms\FieldType::PAPER_SIGNATURE,
                ], true) && ! array_key_exists($field['key'], (array) $session->known_fields))
                ->pluck('label')->all();
            if ($missing !== []) {
                $status = ManualFormSession::STATUS_FAILED;
            }
        }
        $values = $confidence = $signatures = $signatureImages = $unresolved = $warnings = [];
        foreach ($documents as $document) {
            $result = (array) $document->parse_result;
            foreach ((array) ($result['values'] ?? []) as $key => $value) {
                if (array_key_exists($key, $values) && $values[$key] !== $value) {
                    $warnings[] = "Conflicting scan values for {$key}; check this field at review.";
                    $unresolved[] = $key;

                    continue;
                }
                $values[$key] = $value;
            }
            $confidence = array_merge($confidence, (array) $document->parse_confidence);
            $signatures = array_merge($signatures, (array) ($result['signatures'] ?? []));
            $signatureImages = array_merge($signatureImages, (array) ($result['signature_images'] ?? []));
            $unresolved = array_merge($unresolved, (array) ($result['unresolved'] ?? []));
            $warnings = array_merge($warnings, (array) $document->parse_warnings);
        }
        $first = $documents->first();
        $session->forceFill([
            'status' => $status,
            'parse_error' => isset($missing) && $missing !== []
                ? 'These required fields are not covered by any printed template: '.implode(', ', $missing).'.'
                : $documents->first(fn ($doc) => $doc->status === ManualFormSession::STATUS_FAILED)?->parse_error,
            'parse_result' => ['values' => $values, 'signatures' => $signatures,
                'signature_images' => $signatureImages, 'unresolved' => array_values(array_unique($unresolved))],
            'parse_confidence' => $confidence,
            'parse_warnings' => array_values(array_unique($warnings)),
            'parse_model' => $documents->pluck('parse_model')->filter()->unique()->implode(', ') ?: null,
            'partial_pdf_path' => $first->partial_pdf_path,
            'partial_pdf_hash' => $first->partial_pdf_hash,
            'scan_paths' => $documents->flatMap(fn ($doc) => (array) $doc->scan_paths)->all(),
        ])->save();
    }

    /**
     * Resolve the manual session a final submission is completing, if any, and
     * authorize it against the current request. Returns null when the form is
     * not being submitted through a manual draft.
     *
     * Owner sessions require the signed-in user; public sessions require the
     * raw resume token to hash-match. A mismatch is treated as no session
     * (the submission proceeds as an ordinary one) rather than leaking state.
     */
    public function resolveForFinalSubmit(Request $request, Form $form): ?ManualFormSession
    {
        $sessionId = trim((string) $request->input('manual_session_id', ''));
        if ($sessionId === '') {
            return null;
        }

        $session = ManualFormSession::query()
            ->where('id', $sessionId)
            ->where('form_id', (int) $form->getKey())
            ->first();

        if ($session === null) {
            return null;
        }

        return $this->authorize($request, $session) ? $session : null;
    }

    /**
     * Whether the request may act on this session.
     */
    public function authorize(Request $request, ManualFormSession $session): bool
    {
        $token = (string) ($request->input('manual_token', $request->query('t', '')));

        return $this->canAccess($session, $request->user(), $token !== '' ? $token : null);
    }

    /**
     * Owner sessions require the given user; public sessions require the raw
     * resume token to hash-match. The primitive both the web pages and the
     * final-submit hook authorize through.
     */
    public function canAccess(ManualFormSession $session, ?\App\Models\User $user, ?string $token): bool
    {
        if ($session->isPublic()) {
            return $token !== null && $token !== ''
                && Hash::check($token, (string) $session->public_token_hash);
        }

        return $user !== null && (int) $session->user_id === (int) $user->getKey();
    }

    /**
     * Compact provenance recorded on the finalized submission payload under
     * `_manual_source`. Never mixes parser confidence into business values.
     *
     * @return array<string,mixed>
     */
    public function manualSourceMeta(ManualFormSession $session): array
    {
        return array_filter([
            'session_id' => (string) $session->getKey(),
            'documents' => $session->documents->map(fn ($doc) => [
                'template_id' => $doc->template_id,
                'template_version' => $doc->template_version,
                'partial_pdf_path' => $doc->partial_pdf_path,
                'partial_pdf_hash' => $doc->partial_pdf_hash,
                'scan_paths' => $doc->scan_paths,
                'parser_model' => $doc->parse_model,
            ])->all() ?: null,
            'partial_pdf_path' => $session->partial_pdf_path,
            'partial_pdf_hash' => $session->partial_pdf_hash,
            'scan_paths' => $session->scan_paths ?: null,
            'parser_model' => $session->parse_model,
            'parse_warnings' => $session->parse_warnings ?: null,
        ], fn ($v) => $v !== null && $v !== []);
    }

    /**
     * Link a finalized submission back to its session and close the session.
     */
    public function markSubmitted(ManualFormSession $session, FormSubmission $submission): void
    {
        $session->forceFill([
            'status' => ManualFormSession::STATUS_SUBMITTED,
            'form_submission_id' => (int) $submission->getKey(),
            'submitted_at' => now(),
        ])->save();
    }
}
