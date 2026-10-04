<?php

namespace App\Services;

use App\Models\Form;
use App\Models\Template as FormTemplate;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Owns the ordered `.docx` template slots for a form. Step 2 edits each slot
 * independently, then folds the draft slots into active Template rows on save.
 *
 * A form that has never been opened in the new editor is migrated lazily: its
 * legacy `pdf_template.html` (rich text with `data-field` token spans) is
 * converted to Word on first open, so existing forms carry their layout across
 * instead of starting blank. Forms with no legacy template get a stub document.
 */
class FormPrintTemplateService
{
    /**
     * How long a Step-2 draft survives without activity. Every read slides the
     * TTL forward, so an actively-edited draft never expires mid-session; an
     * abandoned one is reaped by the cache 12h after the last touch.
     */
    private const DRAFT_TTL = 60 * 60 * 12;

    /** Cache-key namespace for draft metadata and documents. */
    private const DRAFT_PREFIX = 'draft:print:';

    public const MAX_SLOTS = 10;

    public function __construct(private readonly DocxTemplateService $docx) {}

    /**
     * The form's primary printed template, created on first use.
     */
    public function resolve(Form $form, ?int $userId = null): FormTemplate
    {
        return $this->resolveAll($form, $userId)->first();
    }

    /** @return Collection<int, FormTemplate> */
    public function activeTemplates(Form $form): Collection
    {
        return $form->activeTemplates()->get()->filter(fn (FormTemplate $template) => $this->fileExists($template))->values();
    }

    /** @return Collection<int, FormTemplate> */
    public function resolveAll(Form $form, ?int $userId = null): Collection
    {
        $active = $this->activeTemplates($form);
        if ($active->isNotEmpty()) {
            return $active;
        }

        return new Collection([$this->seed($form, $userId)]);
    }

    public function createSlot(Form $form, string $name, string $contents, int $order, ?int $userId): FormTemplate
    {
        if ($contents === '') {
            throw new RuntimeException('Refusing to create an empty printed template.');
        }

        $path = trim((string) config('documents.templates_directory', 'form-templates'), '/')
            .'/'.$form->getKey().'/printed-template-'.Str::uuid().'.docx';
        if (! Storage::disk($this->disk())->put($path, $contents)) {
            throw new RuntimeException('Could not store the printed template.');
        }

        return $form->templates()->create([
            'organization_id' => $form->organization_id,
            'uploaded_by' => $userId,
            'template_name' => $name,
            'docx_path' => $path,
            'version' => 1,
            'is_active' => true,
            'slot_order' => $order,
        ]);
    }

    /**
     * Persist a new revision of the template's document.
     *
     * The file is overwritten in place and `version` bumped: OnlyOffice
     * autosaves, so a row per save would pile up fast. Bumping the version and
     * touching the row is what invalidates the editor's document key.
     */
    public function storeRevision(FormTemplate $template, string $contents): FormTemplate
    {
        if ($contents === '') {
            throw new RuntimeException('Refusing to save an empty printed template.');
        }

        Storage::disk($this->disk())->put((string) $template->docx_path, $contents);

        $template->forceFill([
            'version' => (int) ($template->version ?? 1) + 1,
            'is_active' => true,
        ])->save();

        // `updated_at` feeds the document key; make sure it actually moved even
        // when two saves land inside the same second.
        $template->touch();

        return $template->refresh();
    }

    /**
     * Absolute path of the template's document on disk.
     */
    public function absolutePath(FormTemplate $template): string
    {
        return Storage::disk($this->disk())->path((string) $template->docx_path);
    }

    public function fileExists(FormTemplate $template): bool
    {
        $path = (string) ($template->docx_path ?? '');

        return $path !== '' && Storage::disk($this->disk())->exists($path);
    }

    /**
     * The form's first active template *if its document is actually on disk*,
     * null otherwise. Unlike {@see resolve()} this never seeds — read-only
     * callers (the `/forms` directory, blank-PDF printing) must not mutate
     * storage as a side effect of a lookup.
     */
    public function activeStored(Form $form): ?FormTemplate
    {
        return $this->activeTemplates($form)->first();
    }

    /**
     * Create the form's first .docx, migrating the legacy HTML template if the
     * form has one.
     */
    private function seed(Form $form, ?int $userId): FormTemplate
    {
        $relativePath = $this->pathFor($form);
        // Parenthesised around the ?? deliberately: a cast binds tighter, so
        // `(string) $arr['html'] ?? ''` still warns on a template-less form.
        $legacyHtml = trim((string) (((array) ($form->pdf_template ?? []))['html'] ?? ''));

        $bytes = $legacyHtml !== ''
            ? $this->convertLegacyHtml($legacyHtml)
            : $this->blankDocumentFor((string) ($form->name ?? 'Printed template'));

        Storage::disk($this->disk())->put($relativePath, $bytes);

        // No valid active document remains. Retain old rows for historical links.
        FormTemplate::query()->where('form_id', $form->getKey())->update(['is_active' => false]);

        return FormTemplate::query()->create([
            'form_id' => $form->getKey(),
            'organization_id' => $form->organization_id,
            'uploaded_by' => $userId,
            'template_name' => trim((string) ($form->name ?? 'Printed template')),
            'docx_path' => $relativePath,
            'version' => 1,
            'is_active' => true,
            'slot_order' => 0,
        ]);
    }

    /**
     * Convert the legacy rich-text template to Word. Token spans become
     * `{{field_key}}` text on the way through, which is exactly the syntax
     * {@see DocxTemplateService::populate()} fills in later.
     */
    private function convertLegacyHtml(string $html): string
    {
        $generatedPath = $this->docx->htmlToDocx($html);

        try {
            return File::get($generatedPath);
        } finally {
            File::deleteDirectory(dirname($generatedPath));
        }
    }

    /**
     * A minimal starting document for a form with no legacy template, titled
     * `$name`. Used both when seeding a real template and when synthesising a
     * draft's starter document (which has no Form row to read a name from).
     */
    public function blankDocumentFor(string $name): string
    {
        $workDir = storage_path('app/tmp/seed-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($workDir);
        $path = $workDir.'/blank.docx';

        try {
            // Pin the house default (Times New Roman 12pt, matching the legacy
            // PDF template) so a stub template doesn't start in PhpWord's own
            // default font and drift from the authored look. Settings are
            // process-wide statics, so set them before the document is built.
            \PhpOffice\PhpWord\Settings::setDefaultFontName('Times New Roman');
            \PhpOffice\PhpWord\Settings::setDefaultFontSize(12);

            $phpWord = new \PhpOffice\PhpWord\PhpWord;
            $section = $phpWord->addSection();
            $section->addText(trim($name) !== '' ? trim($name) : 'Printed template', ['bold' => true, 'size' => 16]);
            $section->addTextBreak();
            $section->addText('Use the Field tokens panel to insert form fields, e.g. {{full_name}}.');

            \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007')->save($path);

            return File::get($path);
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    // ── Non-persistent Step-2 drafts ────────────────────────────────────────
    //
    // A draft lets the printed-template editor open — and its field-token
    // palette reflect just-added fields — *before* the form is saved, with zero
    // writes to forms/templates/form_descriptions. Metadata and the working
    // .docx live only in the `file` cache store, keyed by a server-minted
    // draftId, and are cleared on final save (see adoptDraft()). The `file`
    // store is used explicitly because the default cache/session drivers here
    // are `database`, which the non-persistent requirement rules out.

    /**
     * The file cache store, chosen explicitly so drafts never touch the DB.
     */
    private function draftCache(): CacheRepository
    {
        return Cache::store('file');
    }

    private function draftKey(string $draftId): string
    {
        return self::DRAFT_PREFIX.$draftId;
    }

    private function draftDocxKey(string $draftId): string
    {
        return self::DRAFT_PREFIX.$draftId.':docx';
    }

    private function slotDocxKey(string $draftId, string $slotId): string
    {
        return self::DRAFT_PREFIX.$draftId.':slot:'.$slotId.':docx';
    }

    /**
     * Read a draft's metadata, sliding its TTL forward on the way out so an
     * active session never expires. Returns null for an unknown/expired draft.
     *
     * @return array{form_id: ?int, name: string, fields: array<int, array<string, mixed>>, version: int, created_by: ?int, updated_at: int}|null
     */
    public function readDraft(string $draftId): ?array
    {
        $draft = $this->draftCache()->get($this->draftKey($draftId));

        if (! is_array($draft)) {
            return null;
        }

        // Sliding TTL: touching the draft keeps it (and its document) alive.
        $this->draftCache()->put($this->draftKey($draftId), $draft, self::DRAFT_TTL);

        return $draft;
    }

    /**
     * Write (create or replace) a draft's metadata.
     *
     * @param  array<int, array<string, mixed>>  $fields  descriptors: field_key/field_label/field_type/field_options
     */
    public function writeDraft(string $draftId, ?int $formId, string $name, array $fields, int $version, ?int $userId): void
    {
        $existing = $this->readDraft($draftId);
        $this->draftCache()->put($this->draftKey($draftId), array_merge($existing ?? [], [
            'form_id' => $formId,
            'name' => trim($name) !== '' ? trim($name) : 'Printed template',
            'fields' => array_values($fields),
            'version' => max(1, $version),
            'created_by' => $userId,
            'updated_at' => now()->getTimestamp(),
        ]), self::DRAFT_TTL);
    }

    /** @return array<int, array{id: string, template_id: ?int, name: string, version: int, closed_status?: int}> */
    public function draftSlots(string $draftId): array
    {
        return (array) ($this->readDraft($draftId)['slots'] ?? []);
    }

    public function draftSlot(string $draftId, string $slotId): ?array
    {
        foreach ($this->draftSlots($draftId) as $slot) {
            if ($slot['id'] === $slotId) {
                return $slot;
            }
        }

        return null;
    }

    private function putSlots(string $draftId, array $slots): void
    {
        $draft = $this->readDraft($draftId);
        if ($draft === null) {
            throw new RuntimeException('Draft no longer exists.');
        }
        $draft['slots'] = array_values($slots);
        $this->draftCache()->put($this->draftKey($draftId), $draft, self::DRAFT_TTL);
    }

    public function ensureDraftSlots(string $draftId, string $name, ?Form $form = null, ?int $userId = null): void
    {
        if ($this->draftSlots($draftId) !== []) {
            return;
        }

        $templates = $form ? $this->resolveAll($form, $userId) : new Collection;
        $slots = [];
        foreach ($templates as $template) {
            $id = (string) Str::uuid();
            $slots[] = [
                'id' => $id,
                'template_id' => (int) $template->getKey(),
                'name' => (string) $template->template_name,
                'version' => 1,
            ];
            $this->putSlotDocx($draftId, $id, File::get($this->absolutePath($template)));
        }
        if ($slots === []) {
            $id = (string) Str::uuid();
            $slots[] = ['id' => $id, 'template_id' => null, 'name' => $name, 'version' => 1];
            $this->putSlotDocx($draftId, $id, $this->blankDocumentFor($name));
        }
        $this->putSlots($draftId, $slots);
    }

    public function readSlotDocx(string $draftId, string $slotId): ?string
    {
        if ($this->draftSlot($draftId, $slotId) === null) {
            return null;
        }
        $bytes = $this->draftCache()->get($this->slotDocxKey($draftId, $slotId));
        if (is_string($bytes) && $bytes !== '') {
            $this->putSlotDocx($draftId, $slotId, $bytes);
        }

        return is_string($bytes) && $bytes !== '' ? $bytes : null;
    }

    private function putSlotDocx(string $draftId, string $slotId, string $contents): void
    {
        $this->draftCache()->put($this->slotDocxKey($draftId, $slotId), $contents, self::DRAFT_TTL);
    }

    public function addDraftSlot(string $draftId, string $name, string $contents): array
    {
        $slots = $this->draftSlots($draftId);
        if ($slots === [] || count($slots) >= self::MAX_SLOTS || $contents === '') {
            throw new RuntimeException('Cannot add another printed template to this draft.');
        }
        $id = (string) Str::uuid();
        $slot = ['id' => $id, 'template_id' => null, 'name' => $name, 'version' => 1];
        $this->putSlotDocx($draftId, $id, $contents);
        $slots[] = $slot;
        $this->putSlots($draftId, $slots);

        return $slot;
    }

    public function removeDraftSlot(string $draftId, string $slotId): void
    {
        $slots = $this->draftSlots($draftId);
        if (count($slots) <= 1 || $this->draftSlot($draftId, $slotId) === null) {
            throw new RuntimeException('At least one printed template must remain.');
        }
        $this->putSlots($draftId, array_values(array_filter($slots, fn ($slot) => $slot['id'] !== $slotId)));
        $this->draftCache()->forget($this->slotDocxKey($draftId, $slotId));
    }

    public function replaceDraftSlot(string $draftId, string $slotId, string $contents, ?string $name = null): void
    {
        $this->storeSlotRevision($draftId, $slotId, $contents);
        if ($name !== null) {
            $slots = $this->draftSlots($draftId);
            foreach ($slots as &$slot) {
                if ($slot['id'] === $slotId) {
                    $slot['name'] = $name;
                }
            }
            unset($slot);
            $this->putSlots($draftId, $slots);
        }
    }

    public function storeSlotRevision(string $draftId, string $slotId, string $contents): int
    {
        if ($contents === '') {
            throw new RuntimeException('Refusing to save an empty draft template.');
        }
        $slots = $this->draftSlots($draftId);
        foreach ($slots as &$slot) {
            if ($slot['id'] === $slotId) {
                $this->putSlotDocx($draftId, $slotId, $contents);
                $slot['version']++;
                unset($slot['closed_status']);
                $version = $slot['version'];
                unset($slot);
                $this->putSlots($draftId, $slots);

                return $version;
            }
        }
        throw new RuntimeException('Draft template slot no longer exists.');
    }

    public function markSlotClosed(string $draftId, string $slotId, int $status): void
    {
        $slots = $this->draftSlots($draftId);
        foreach ($slots as &$slot) {
            if ($slot['id'] === $slotId) {
                $slot['closed_status'] = $status;
                break;
            }
        }
        unset($slot);
        $this->putSlots($draftId, $slots);
    }

    public function clearSlotClosed(string $draftId, string $slotId): void
    {
        $slots = $this->draftSlots($draftId);
        foreach ($slots as &$slot) {
            if ($slot['id'] === $slotId) {
                unset($slot['closed_status']);
                break;
            }
        }
        unset($slot);
        $this->putSlots($draftId, $slots);
    }

    /**
     * Raw draft document bytes, or null when none have been stored yet.
     */
    public function readDraftDocx(string $draftId): ?string
    {
        $slots = $this->draftSlots($draftId);
        if ($slots !== []) {
            return $this->readSlotDocx($draftId, $slots[0]['id']);
        }
        $bytes = $this->draftCache()->get($this->draftDocxKey($draftId));

        return is_string($bytes) && $bytes !== '' ? $bytes : null;
    }

    public function putDraftDocx(string $draftId, string $contents): void
    {
        $slots = $this->draftSlots($draftId);
        if ($slots !== []) {
            $this->putSlotDocx($draftId, $slots[0]['id'], $contents);

            return;
        }
        $this->draftCache()->put($this->draftDocxKey($draftId), $contents, self::DRAFT_TTL);
    }

    /**
     * Ensure the draft has a document to open. Idempotent.
     *
     * For an existing form the draft starts from the form's current template —
     * migrating its legacy HTML on first use via {@see resolve()} — NOT from a
     * blank document. Starting blank would mean every save replaces the saved
     * layout with whatever the user rebuilt from scratch, which reads as "the
     * template never keeps my changes". Only genuinely new forms (no $form)
     * get the blank starter.
     */
    public function ensureDraftDocx(string $draftId, string $name, ?Form $form = null, ?int $userId = null): void
    {
        if ($this->readDraftDocx($draftId) !== null) {
            return;
        }

        if ($form !== null) {
            $template = $this->resolve($form, $userId);
            $this->putDraftDocx($draftId, (string) File::get($this->absolutePath($template)));

            return;
        }

        $this->putDraftDocx($draftId, $this->blankDocumentFor($name));
    }

    /**
     * Persist an edited revision of the draft document (from a Document Server
     * save) and bump the draft's version so the editor's document key moves.
     */
    public function storeDraftRevision(string $draftId, string $contents): int
    {
        $slots = $this->draftSlots($draftId);
        if ($slots !== []) {
            $version = $this->storeSlotRevision($draftId, $slots[0]['id'], $contents);
            $draft = $this->readDraft($draftId);
            $draft['version'] = $version;
            $this->draftCache()->put($this->draftKey($draftId), $draft, self::DRAFT_TTL);

            return $version;
        }
        if ($contents === '') {
            throw new RuntimeException('Refusing to save an empty draft template.');
        }

        $draft = $this->readDraft($draftId);
        if ($draft === null) {
            throw new RuntimeException('Draft no longer exists.');
        }

        $this->putDraftDocx($draftId, $contents);

        $version = (int) $draft['version'] + 1;
        $this->writeDraft(
            $draftId,
            $draft['form_id'],
            $draft['name'],
            $draft['fields'],
            $version,
            $draft['created_by'],
        );

        return $version;
    }

    /**
     * Record that the Document Server closed the draft's editing session
     * (status 2 = saved, 4 = closed without changes). The builder's save-flush
     * polls the version endpoint and also resolves on this marker, so a session
     * that ends without a new revision can't stall the form save.
     */
    public function markDraftClosed(string $draftId, int $status): void
    {
        $slots = $this->draftSlots($draftId);
        if ($slots !== []) {
            $this->markSlotClosed($draftId, $slots[0]['id'], $status);
        }
        $draft = $this->readDraft($draftId);

        if ($draft === null) {
            return;
        }

        $draft['closed_status'] = $status;
        $this->draftCache()->put($this->draftKey($draftId), $draft, self::DRAFT_TTL);
    }

    public function forgetDraft(string $draftId): void
    {
        foreach ($this->draftSlots($draftId) as $slot) {
            $this->draftCache()->forget($this->slotDocxKey($draftId, $slot['id']));
        }
        $this->draftCache()->forget($this->draftKey($draftId));
        $this->draftCache()->forget($this->draftDocxKey($draftId));
    }

    /**
     * Fold a finished draft into the form's real printed template on save:
     * copy the draft's edited document into a persisted revision, then clear
     * the draft. A no-op (returns null) when no draft bytes exist — the seeded
     * blank template already covers that case. Never throws for a missing
     * draft; callers wrap this so template adoption can't fail the save.
     */
    public function adoptDraft(Form $form, string $draftId, ?int $userId = null): ?FormTemplate
    {
        $draft = $this->readDraft($draftId);
        if ($draft === null
            || (($draft['form_id'] ?? null) !== null && (int) $draft['form_id'] !== (int) $form->getKey())
            || $draft['created_by'] !== $userId) {
            throw new RuntimeException('Printed template draft does not belong to this form and user.');
        }
        $slots = $this->draftSlots($draftId);
        if ($slots !== []) {
            $documents = [];
            foreach ($slots as $slot) {
                $bytes = $this->readSlotDocx($draftId, $slot['id']);
                if ($bytes === null) {
                    throw new RuntimeException('A draft template is missing its document.');
                }
                if ($slot['template_id'] !== null && $form->templates()->find($slot['template_id']) === null) {
                    throw new RuntimeException('A draft template does not belong to this form.');
                }
                $documents[$slot['id']] = $bytes;
            }
            $ids = [];
            foreach ($slots as $order => $slot) {
                $bytes = $documents[$slot['id']];
                $template = $slot['template_id'] !== null
                    ? $form->templates()->findOrFail($slot['template_id'])
                    : $this->createSlot($form, $slot['name'], $bytes, $order, $userId);
                if ($slot['template_id'] !== null) {
                    if (File::get($this->absolutePath($template)) !== $bytes) {
                        $this->storeRevision($template, $bytes);
                    }
                    $template->forceFill([
                        'template_name' => $slot['name'],
                        'is_active' => true,
                        'slot_order' => $order,
                    ])->save();
                }
                $ids[] = $template->getKey();
            }
            $form->templates()->where('is_active', true)->whereNotIn('id', $ids)->update(['is_active' => false]);
            $first = $form->templates()->findOrFail($ids[0]);
            $this->forgetDraft($draftId);

            return $first;
        }

        $bytes = $this->readDraftDocx($draftId);

        if ($bytes === null) {
            $this->forgetDraft($draftId);

            return null;
        }

        $template = $this->resolve($form, $userId);
        $template = $this->storeRevision($template, $bytes);

        $this->forgetDraft($draftId);

        return $template;
    }

    private function pathFor(Form $form): string
    {
        $directory = trim((string) config('documents.templates_directory', 'form-templates'), '/');

        return $directory.'/'.$form->getKey().'/printed-template.docx';
    }

    private function disk(): string
    {
        return (string) config('documents.disk', 'public');
    }
}
