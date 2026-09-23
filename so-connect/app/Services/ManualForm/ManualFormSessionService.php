<?php

namespace App\Services\ManualForm;

use App\Jobs\GenerateManualSessionSchema;
use App\Jobs\PrepareManualFormSession;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\ManualFormSession;
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

        $template = $this->templates->activeStored($form);

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
            || $session->partial_pdf_path !== null) {
            return;
        }

        $form = $session->form;
        if ($form === null) {
            $this->failSession($session, 'This form is no longer available.');

            return;
        }

        $this->freezePartialPdf(
            $session,
            $form,
            $session->template,
            (array) $session->known_fields,
            $session->owner,
        );
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
    ): void {
        if ($template === null) {
            $this->failSession($session, 'This form has no printed template to fill.');

            return;
        }

        $disk = (string) config('documents.disk', 'public');
        $pdfRelative = 'manual-form/'.$session->getKey().'/partial.pdf';
        $pagesDir = Storage::disk($disk)->path('manual-form/'.$session->getKey().'/pages');

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

            $docxPath = $this->docx->populate($template, $built['values'], $built['images']);
            $pdfAbsolute = $this->docx->toPdf($docxPath);

            Storage::disk($disk)->put($pdfRelative, File::get($pdfAbsolute));
            // Keep the populated .docx: session-schema generation re-measures
            // each field's table cell against this exact document so distortions
            // from the real digital-form values are reflected in the writable
            // areas (not just the blank-template baseline).
            Storage::disk($disk)->put(
                'manual-form/'.$session->getKey().'/partial.docx',
                File::get($docxPath),
            );
            File::deleteDirectory(dirname($docxPath));

            $raster = $this->rasterizer->rasterize(
                Storage::disk($disk)->path($pdfRelative),
                $pagesDir,
            );
            if (! ($raster['ok'] ?? false)) {
                $this->failSession($session, 'Could not prepare the printable pages: '.($raster['error'] ?? 'unknown'));

                return;
            }

            $pageMeta = array_map(fn ($p) => [
                'index' => $p['index'],
                'path' => 'manual-form/'.$session->getKey().'/pages/'.basename($p['path']),
                'width' => $p['width'],
                'height' => $p['height'],
            ], $raster['pages']);

            $session->forceFill([
                'partial_pdf_path' => $pdfRelative,
                'partial_pdf_hash' => $raster['hash'] ?? null,
                'page_meta' => $pageMeta,
            ])->save();

            GenerateManualSessionSchema::dispatch((string) $session->getKey());
        } catch (\Throwable $e) {
            report($e);
            $this->failSession($session, 'Could not generate the partial form.');
        }
    }

    public function failSession(ManualFormSession $session, string $reason): void
    {
        $session->forceFill([
            'status' => ManualFormSession::STATUS_FAILED,
            'parse_error' => $reason,
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
