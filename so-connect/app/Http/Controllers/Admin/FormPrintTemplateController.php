<?php

namespace App\Http\Controllers\Admin;

use App\Forms\FieldType;
use App\Helpers\FormTemplateHelper;
use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\Template as FormTemplate;
use App\Services\DocxConverter;
use App\Services\DocxTemplateService;
use App\Services\FormPrintTemplateService;
use App\Services\OnlyOfficeService;
use App\Support\FieldTokenSource;
use App\Support\UniversalField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Backs the OnlyOffice editor in the form builder's "Printed template" step.
 *
 * Four of these endpoints are *not* session-authenticated, because the callers
 * are not the admin's browser session: the Document Server fetches and saves
 * the document server-side, and the token-palette plugin runs inside the
 * editor's own iframe. They authenticate with a short-lived HS256 token signed
 * by {@see OnlyOfficeService} instead — deliberately not Laravel signed URLs,
 * whose signature covers the hostname, and three different hostnames are in
 * play here (browser, app container, document server).
 */
class FormPrintTemplateController extends Controller
{
    /** Stable identity for the token-palette plugin. */
    private const PLUGIN_GUID = 'asc.{8B3D2A41-6C7E-4F55-9E2B-1A4C9D0E7F31}';

    private const PURPOSE_DOCUMENT = 'document';

    private const PURPOSE_CALLBACK = 'callback';

    private const PURPOSE_PLUGIN = 'plugin';

    /**
     * Serve a short-lived populated DOCX to the Document Server's conversion
     * API. The random cache key and scoped JWT keep scratch documents private.
     */
    public function conversionSource(
        Request $request,
        string $conversionId,
        OnlyOfficeService $onlyOffice,
    ) {
        $claims = $onlyOffice->verify($request->query('token'));
        abort_unless(
            is_array($claims)
                && ($claims['purpose'] ?? null) === 'conversion-source'
                && hash_equals($conversionId, (string) ($claims['cid'] ?? '')),
            403,
        );

        $contents = \Illuminate\Support\Facades\Cache::store('file')
            ->get(DocxConverter::SOURCE_CACHE_PREFIX.$conversionId);
        abort_unless(is_string($contents) && $contents !== '', 404);

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Field types that carry no submitted value and so make no useful token. */
    private const NON_PRINTABLE_FIELD_TYPES = ['heading', 'static-text'];

    /**
     * Display label for each {@see FieldType} catalog group, so the palette can
     * head its sections the way the form builder does. Anything unmapped falls
     * back to the generic "Form fields" bucket.
     */
    private const FIELD_GROUP_LABELS = [
        'basic' => 'Basic',
        'choice' => 'Choice',
        'media' => 'Media',
        'special' => 'Special',
        'layout' => 'Layout',
    ];

    /**
     * Order the palette renders group headings in. Form-field groups first
     * (mirroring the builder), then the universal token sources.
     */
    private const GROUP_ORDER = ['Basic', 'Choice', 'Media', 'Special', 'Layout', 'Form fields', 'Profile', 'Organization'];

    /**
     * Editor config for the wizard step. Session-authenticated (admin).
     */
    public function config(
        Form $form,
        OnlyOfficeService $onlyOffice,
        FormPrintTemplateService $templates,
        Request $request,
    ): JsonResponse {
        if (! $onlyOffice->enabled()) {
            return response()->json([
                'enabled' => false,
                'message' => 'The document editor is not configured. Set ONLYOFFICE_PUBLIC_URL and '
                    .'an ONLYOFFICE_JWT_SECRET of at least 32 characters (HS256 needs a 256-bit key).',
            ], 503);
        }

        $template = $templates->resolve($form, (int) $request->user()->getKey());

        // The Document Server calls these two itself, so they are built against
        // its view of the app — its own container hostname, not localhost.
        $serverBase = $onlyOffice->appUrl();
        $documentUrl = $serverBase.'/onlyoffice/'.$form->getKey().'/document?token='
            .$this->issue($template, self::PURPOSE_DOCUMENT);
        $callbackUrl = $serverBase.'/onlyoffice/'.$form->getKey().'/callback?token='
            .$this->issue($template, self::PURPOSE_CALLBACK, minutes: 60 * 12);

        $config = $onlyOffice->editorConfig($template, $request->user(), $documentUrl, $callbackUrl);

        // The palette, by contrast, is loaded by the *browser*, so it has to be
        // a browser-reachable URL. Serving it per-form is what lets the plugin
        // know which form's fields to offer — Community Edition has no
        // Automation API for pushing that in from the host page.
        $pluginToken = $this->issue($template, self::PURPOSE_PLUGIN, minutes: 60 * 12);
        $config['editorConfig']['plugins'] = [
            'autostart' => [self::PLUGIN_GUID],
            'pluginsData' => [
                rtrim((string) config('app.url'), '/').'/onlyoffice/'.$form->getKey().'/config.json?token='.$pluginToken,
            ],
        ];

        // Re-sign: the signature has to cover the plugins block too.
        unset($config['token']);
        $config['token'] = $onlyOffice->sign($config);

        return response()->json([
            'enabled' => true,
            'apiScript' => $onlyOffice->apiScriptUrl(),
            'config' => $config,
            'templateId' => (int) $template->getKey(),
            'version' => (int) $template->version,
        ]);
    }

    /**
     * Populate a printed template with posted field data and return the
     * document, optionally converted to PDF.
     *
     * Session-authenticated (admin), unlike the Document Server endpoints. This
     * is the ad-hoc path — proofing a template against sample values — while
     * real submissions print through
     * {@see \App\Services\DocumentGenerationService}.
     */
    public function generate(Request $request, FormTemplate $template, DocxTemplateService $docx)
    {
        $validated = $request->validate([
            'data' => ['required', 'array'],
            'format' => ['nullable', 'string', 'in:docx,pdf'],
        ]);

        $format = $validated['format'] ?? 'docx';

        try {
            $generatedPath = $docx->populate($template, $validated['data']);

            if ($format === 'pdf') {
                $generatedPath = $docx->toPdf($generatedPath);
            }
        } catch (\Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => 'Could not generate the document. '.$throwable->getMessage(),
            ], 422);
        }

        // Read then drop the scratch directory so neither the generated file nor
        // the intermediate .docx behind a PDF outlives the response.
        $contents = File::get($generatedPath);
        File::deleteDirectory(dirname($generatedPath));

        $filename = Str::slug((string) $template->template_name) ?: 'document';

        return response($contents, 200, [
            'Content-Type' => $format === 'pdf'
                ? 'application/pdf'
                : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.'.$format.'"',
        ]);
    }

    /**
     * Serve the .docx to the Document Server.
     */
    public function document(Request $request, Form $form, FormPrintTemplateService $templates)
    {
        $template = $this->authorizeToken($request, $form, self::PURPOSE_DOCUMENT);

        abort_unless($templates->fileExists($template), 404);

        return response()->file($templates->absolutePath($template), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    /**
     * Receive save notifications from the Document Server.
     *
     * Status 2 (ready to save) and 6 (force save) carry a URL to fetch the
     * edited document from. Everything else is informational. The reply body
     * must be `{"error":0}` or the server retries and eventually shows the user
     * a save failure.
     */
    public function callback(
        Request $request,
        Form $form,
        FormPrintTemplateService $templates,
        OnlyOfficeService $onlyOffice,
    ): JsonResponse {
        $template = $this->authorizeToken($request, $form, self::PURPOSE_CALLBACK);

        $body = (array) $request->json()->all();

        // The body is signed too; on a JWT-enabled server the claims are
        // authoritative and the unsigned outer body must not be trusted.
        $claims = $onlyOffice->verify($body['token'] ?? $request->header('Authorization'));
        if ($claims !== null) {
            $body = (array) ($claims['payload'] ?? $claims);
        } elseif ((string) config('onlyoffice.jwt_secret', '') !== '') {
            Log::warning('OnlyOffice callback rejected: body signature missing or invalid.', [
                'form_id' => $form->getKey(),
            ]);

            return response()->json(['error' => 1], 403);
        }

        $status = (int) ($body['status'] ?? 0);

        // 2 = ready to save, 6 = force save (autosave tick / explicit save).
        if (in_array($status, [2, 6], true)) {
            $downloadUrl = (string) ($body['url'] ?? '');

            if ($downloadUrl === '') {
                Log::warning('OnlyOffice callback had a save status but no document URL.', [
                    'form_id' => $form->getKey(),
                    'status' => $status,
                ]);

                return response()->json(['error' => 1]);
            }

            try {
                $contents = $this->fetchEditedDocument($downloadUrl);
                $templates->storeRevision($template, $contents);
            } catch (\Throwable $throwable) {
                report($throwable);

                return response()->json(['error' => 1]);
            }
        }

        return response()->json(['error' => 0]);
    }

    /**
     * The plugin's config.json, generated per form.
     */
    public function pluginConfig(Request $request, Form $form, OnlyOfficeService $onlyOffice): JsonResponse
    {
        $template = $this->authorizeToken($request, $form, self::PURPOSE_PLUGIN);

        // Relative to the plugin's baseUrl, which OnlyOffice derives from the
        // config.json URL as ".../onlyoffice/{form}/". The editor concatenates
        // baseUrl + this, giving ".../onlyoffice/{form}/plugin/{token}" on the
        // app's own origin. The token rides in the path so the editor's appended
        // ?theme-type&lang query can't corrupt it.
        $pluginUrl = 'plugin/'.$this->issue($template, self::PURPOSE_PLUGIN, minutes: 60 * 12);

        return response()->json($this->pluginConfigBody($pluginUrl))->withHeaders([
            // The editor runs on the Document Server's origin but fetches this
            // config.json from the app's origin. Without this the browser blocks
            // the cross-origin read and the plugin silently never registers.
            'Access-Control-Allow-Origin' => $onlyOffice->publicUrl() ?: '*',
        ]);
    }

    /**
     * The plugin's config.json body, given the (baseUrl-relative) URL of the
     * plugin page. Shared by the persisted and draft config endpoints.
     *
     * @return array<string, mixed>
     */
    private function pluginConfigBody(string $pluginUrl): array
    {
        return [
            'name' => 'Field tokens',
            'guid' => self::PLUGIN_GUID,
            'version' => '1.2.3',
            'minVersion' => '7.0.0',
            'variations' => [[
                'description' => 'Insert form field and profile tokens',
                'url' => $pluginUrl,
                'icons' => ['icon.png', 'icon@2x.png'],
                'isViewer' => false,
                'EditorsSupport' => ['word'],
                'isVisual' => true,
                'isModal' => false,
                // Docked INSIDE the left panel: this is the mode whose native
                // header carries OnlyOffice's own "Hide panel" collapse button,
                // so the panel collapses from its own title bar.
                'isInsideMode' => true,
                'initDataType' => 'none',
                'initData' => '',
                'events' => ['onContextMenuShow', 'onContextMenuClick'],
                'buttons' => [],
                'size' => [320, 600],
            ]],
        ];
    }

    /**
     * The plugin SDK's own handshake config, fetched from *inside* the plugin
     * iframe.
     *
     * OnlyOffice's bundled plugins.js does `XHR('./config.json')` relative to the
     * plugin page (…/plugin/{token}) on load, reads the `guid`, and only then
     * posts its "initialize" message to the editor — which is what triggers
     * `Asc.plugin.init` and lets the palette render. That relative fetch lands
     * here (…/plugin/config.json), with no token, so it stays deliberately
     * minimal: just the public GUID, nothing form-specific or sensitive. The
     * token-bearing {@see pluginConfig()} is what the *editor* consumes.
     */
    public function pluginHandshake(Request $request, Form $form, OnlyOfficeService $onlyOffice): JsonResponse
    {
        return response()->json([
            'name' => 'Field tokens',
            'guid' => self::PLUGIN_GUID,
        ])->withHeaders([
            'Access-Control-Allow-Origin' => $onlyOffice->publicUrl() ?: '*',
        ]);
    }

    /**
     * The plugin's toolbar/panel icon: a gold coin.
     *
     * The variation config declares icons ['icon.png', 'icon@2x.png'], which the
     * editor resolves against the plugin baseUrl (…/onlyoffice/{form}/) and loads
     * as an <img>. Served as SVG (Content-Type wins over the .png name) so it
     * stays crisp at any devicePixelRatio — the same file answers both the 1x and
     * 2x requests.
     */
    public function pluginIcon(): \Illuminate\Http\Response
    {
        $coin = <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" role="img" aria-label="Coin">
              <circle cx="12" cy="12" r="10" fill="#F6C544" stroke="#D19A1C" stroke-width="1.5"/>
              <circle cx="12" cy="12" r="7.4" fill="none" stroke="#E4AE2C" stroke-width="1"/>
              <path d="M12 7.8 L13.03 10.58 L15.99 10.70 L13.66 12.54 L14.47 15.40 L12 13.75 L9.53 15.40 L10.34 12.54 L8.01 10.70 L10.97 10.58 Z"
                    fill="#FFFBEA" stroke="#C9971A" stroke-width="0.5" stroke-linejoin="round"/>
            </svg>
            SVG;

        return response($coin, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Replace the form's printed template with an uploaded .docx. Session-
     * authenticated (admin) — this is the browser's own request, unlike the
     * Document Server endpoints.
     *
     * Bumping the revision here is what makes the re-opened editor load the
     * uploaded content: {@see FormPrintTemplateService::storeRevision()} moves
     * the version + `updated_at`, which changes {@see OnlyOfficeService::documentKey()}.
     * The client only calls this after the editor reports "all changes saved",
     * so tearing the editor down closes it cleanly (no forcesave that would race
     * this upload).
     */
    public function import(Request $request, Form $form, FormPrintTemplateService $templates): JsonResponse
    {
        $request->validate([
            'docx' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('docx');

        // A real .docx is a zip carrying the main Word part; anything else would
        // just make the Document Server fail to open the document.
        if (! $this->isDocx($file)) {
            throw ValidationException::withMessages([
                'docx' => 'Please upload a valid Word (.docx) file.',
            ]);
        }

        $template = $templates->resolve($form, (int) $request->user()->getKey());
        $templates->storeRevision($template, (string) File::get($file->getRealPath()));

        return response()->json(['ok' => true]);
    }

    /**
     * A file is a usable .docx only if it is a zip that contains the main Word
     * document part.
     */
    private function isDocx(?UploadedFile $file): bool
    {
        if ($file === null || strtolower((string) $file->getClientOriginalExtension()) !== 'docx') {
            return false;
        }

        $zip = new \ZipArchive;
        if ($zip->open((string) $file->getRealPath()) !== true) {
            return false;
        }

        $hasDocumentPart = $zip->locateName('word/document.xml') !== false;
        $zip->close();

        return $hasDocumentPart;
    }

    /**
     * The plugin UI itself, with this form's tokens baked in.
     */
    public function plugin(Request $request, Form $form, string $token, OnlyOfficeService $onlyOffice)
    {
        $template = $this->authorizeToken($request, $form, self::PURPOSE_PLUGIN, $token);

        $response = response()->view('onlyoffice.token-palette', [
            'tokens' => $this->tokensFor($form->fields()->orderBy('field_order')->orderBy('id')->get()),
            // The plugin SDK is served by the Document Server, and this page
            // runs in the browser, so it needs the public URL.
            'sdkBase' => $onlyOffice->publicUrl(),
            // Scopes the palette's localStorage (inserted/expanded state) per form.
            'formId' => (int) $form->getKey(),
            'documentId' => (string) $template->getKey(),
            'usageUrl' => route('onlyoffice.token-usage', $form).'?token='.$token,
        ]);

        // The editor frames this page from the Document Server's origin, so the
        // app-wide SAMEORIGIN policy has to be relaxed for this route alone.
        // (SecurityHeaders is excluded from these routes, so nothing overwrites
        // this afterwards.)
        $response->headers->remove('X-Frame-Options');
        // frame-ancestors checks the WHOLE ancestor chain. The palette's chain is
        // the editor iframe (Document Server origin) nested in our own host page,
        // so both origins must be allowed — 'self' covers the top-level host page
        // (served from this same app origin), publicUrl() the editor around it.
        $response->headers->set(
            'Content-Security-Policy',
            "frame-ancestors 'self' ".($onlyOffice->publicUrl() ?: "'self'"),
        );
        // Same cross-origin story as the config.json above.
        $response->headers->set('Access-Control-Allow-Origin', $onlyOffice->publicUrl() ?: '*');

        return $response;
    }

    public function tokenUsage(Request $request, Form $form, FormPrintTemplateService $templates): JsonResponse
    {
        $this->authorizeToken($request, $form, self::PURPOSE_PLUGIN);

        return response()->json([
            'documents' => $templates->activeTemplates($form)->map(fn (FormTemplate $template) => [
                'id' => (string) $template->getKey(),
                'name' => (string) $template->template_name,
                'keys' => FormTemplateHelper::exactTokenKeysFromDocxContents(File::get($templates->absolutePath($template))),
            ])->all(),
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Field tokens for the palette: the form's own fields, then the universal
     * profile/org tokens the PDF renderer knows how to resolve. Each token
     * carries a field-type `icon` and a category `group` so the in-editor
     * palette can render icons + grouped sections like the form builder does.
     *
     * Takes any iterable of field-shaped items (persisted {@see \App\Models\Field}
     * or draft {@see FieldTokenSource}), reading only field_key/field_label/
     * field_type/field_options off each.
     *
     * @param  iterable<int, object{field_key: string, field_label: string, field_type: string, field_options: array}>  $fields
     * @return array<int, array{key: string, label: string, icon: string, group: string}>
     */
    private function tokensFor(iterable $fields): array
    {
        $catalog = FieldType::catalog();

        // Bucket the form's fields by their display group so the palette can
        // render them Basic → Choice → Media → … in a stable order.
        $fieldsByGroup = [];

        foreach ($fields as $field) {
            $key = (string) ($field->field_key ?? '');

            // Headings and static text hold no submitted value, so a token for
            // them would always print blank. Matches the `printableFields`
            // filter the old rich-text palette used.
            if ($key === '' || in_array((string) $field->field_type, self::NON_PRINTABLE_FIELD_TYPES, true)) {
                continue;
            }

            $meta = $catalog[$field->field_type] ?? null;
            $group = isset($meta['group']) ? (self::FIELD_GROUP_LABELS[$meta['group']] ?? 'Form fields') : 'Form fields';

            $token = [
                'key' => $key,
                'label' => (string) ($field->field_label ?: $key),
                'icon' => (string) ($meta['icon'] ?? 'text'),
                'group' => $group,
            ];

            // The Activity Table inserts a whole table (heading labels + one
            // `{{key.col#}}` token per cell) rather than a single token, so it
            // carries an `insert:'table'` marker and its columns as children.
            if ((string) $field->field_type === FieldType::ACTIVITY_TABLE) {
                $token['insert'] = 'table';
                $token['children'] = [];
                foreach (FieldType::activityTableColumns((array) ($field->field_options ?? [])) as $column) {
                    $token['children'][] = [
                        'key' => $key.'.'.$column['key'],
                        'label' => (string) $column['label'],
                        'icon' => (string) ($catalog[$column['type']]['icon'] ?? 'text'),
                        'type_label' => FieldType::label((string) $column['type']),
                    ];
                }
            }

            // A Table field inserts a table the same way: a heading row of
            // column labels and a data row of `{{key.col#}}` tokens that repeat
            // per submitted row. Its sub-fields are its declared columns plus
            // any per-row computed column (row_total).
            if ((string) $field->field_type === FieldType::TABLE_INPUT) {
                $options = (array) ($field->field_options ?? []);
                $token['insert'] = 'table';
                $token['children'] = [];
                foreach (FieldType::tableColumns($options) as $column) {
                    $token['children'][] = [
                        'key' => $key.'.'.$column['key'],
                        'label' => (string) $column['label'],
                        'icon' => (string) ($catalog[$column['type']]['icon'] ?? 'text'),
                        'type_label' => FieldType::label((string) $column['type']),
                    ];
                }
                $rowTotal = (array) ($options['row_total'] ?? []);
                $rowTotalKey = trim((string) ($rowTotal['key'] ?? ''));
                $rowTotalMultiply = array_filter(array_map('strval', (array) ($rowTotal['multiply'] ?? [])));
                if ($rowTotalKey !== '' && count($rowTotalMultiply) >= 2) {
                    $token['children'][] = [
                        'key' => $key.'.'.$rowTotalKey,
                        'label' => (string) ($rowTotal['label'] ?? $rowTotalKey),
                        'icon' => (string) ($catalog[FieldType::NUMBER]['icon'] ?? 'number'),
                        'type_label' => FieldType::label(FieldType::NUMBER),
                    ];
                }
            }

            $fieldsByGroup[$group][] = $token;
        }

        $tokens = [];

        // Emit field groups in the canonical order, then anything unexpected.
        foreach (self::GROUP_ORDER as $group) {
            foreach ($fieldsByGroup[$group] ?? [] as $token) {
                $tokens[] = $token;
            }
            unset($fieldsByGroup[$group]);
        }
        foreach ($fieldsByGroup as $group) {
            foreach ($group as $token) {
                $tokens[] = $token;
            }
        }

        // Universal tokens, grouped by source: profile fields, then org fields.
        // The icon reuses the FieldType catalog via the universal field's type.
        foreach (['profile' => 'Profile', 'org' => 'Organization'] as $source => $label) {
            foreach (UniversalField::keysBySource($source) as $key) {
                $ukey = (string) $key;
                $meta = UniversalField::get($ukey) ?? [];
                $type = (string) ($meta['type'] ?? FieldType::TEXT);

                $tokens[] = [
                    // Namespaced so re-import can tell them from ordinary fields.
                    'key' => 'profile.'.$ukey,
                    'label' => (string) ($meta['label'] ?? $ukey),
                    'icon' => (string) ($catalog[$type]['icon'] ?? 'text'),
                    'group' => $label,
                ];
            }
        }

        return $tokens;
    }

    // ── Non-persistent Step-2 drafts ────────────────────────────────────────
    //
    // These mirror the persisted endpoints above but key on a server-minted
    // draftId held in the file cache instead of a Template row, so the editor
    // (and its field-token palette) can open before the form is ever saved.
    // {@see FormPrintTemplateService} owns the draft lifecycle; the draft is
    // folded into the real template on save (adoptDraft). The session-auth
    // endpoints (syncDraft/draftConfig/draftImport) are the browser's own
    // requests; the token-auth ones (draftDocument/draftCallback/draftPlugin*)
    // serve the Document Server and the plugin iframe, exactly like the
    // persisted /onlyoffice/{form}/* group.

    /**
     * Snapshot the builder's current fields into a draft on entering Step 2.
     * Session-authenticated (admin). Returns the draft's id and the URLs the
     * editor component boots against. Creates the draft on first call, updates
     * it (preserving the document version) on re-entry.
     */
    public function syncDraft(Request $request, FormPrintTemplateService $templates): JsonResponse
    {
        $validated = $request->validate([
            'draft_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-]+$/'],
            'form_id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:255'],
            'fields' => ['present', 'array'],
            'fields.*.field_key' => ['required', 'string', 'max:255'],
            'fields.*.field_label' => ['nullable', 'string', 'max:255'],
            'fields.*.field_type' => ['required', 'string', 'max:64'],
            'fields.*.field_options' => ['nullable', 'array'],
        ]);

        $draftId = $validated['draft_id'] ?? (string) Str::uuid();

        // Preserve an existing draft's version so its document key stays stable
        // across re-syncs — only a real callback save bumps it. New drafts start
        // at 1.
        $existing = $templates->readDraft($draftId);
        if ($existing !== null) {
            abort_unless(
                (int) $existing['created_by'] === (int) $request->user()->getKey()
                    && ($existing['form_id'] ?? null) === ($validated['form_id'] ?? null),
                403,
            );
        }
        $version = $existing !== null ? (int) $existing['version'] : 1;

        $name = trim((string) ($validated['name'] ?? '')) !== ''
            ? trim((string) $validated['name'])
            : 'Printed template';

        $fields = array_map(static fn (array $f): array => [
            'field_key' => (string) ($f['field_key'] ?? ''),
            'field_label' => (string) ($f['field_label'] ?? ($f['field_key'] ?? '')),
            'field_type' => (string) ($f['field_type'] ?? ''),
            'field_options' => (array) ($f['field_options'] ?? []),
        ], $validated['fields']);

        $templates->writeDraft(
            $draftId,
            isset($validated['form_id']) ? (int) $validated['form_id'] : null,
            $name,
            $fields,
            $version,
            (int) $request->user()->getKey(),
        );

        // An existing form's draft opens on its saved template (legacy HTML is
        // migrated on first use); only brand-new forms start blank.
        $form = isset($validated['form_id'])
            ? Form::query()->find((int) $validated['form_id'])
            : null;
        $templates->ensureDraftSlots($draftId, $name, $form, (int) $request->user()->getKey());

        return response()->json([
            'draftId' => $draftId,
            'configUrl' => route('admin.form-builder.draft.config', $draftId),
            'importUrl' => route('admin.form-builder.draft.import', $draftId),
            'versionUrl' => route('admin.form-builder.draft.version', $draftId),
            'addUrl' => route('admin.form-builder.draft.slots.store', $draftId),
            'slots' => $this->slotResponses($draftId, $templates),
        ]);
    }

    private function slotResponses(string $draftId, FormPrintTemplateService $templates): array
    {
        return array_map(fn ($slot) => [
            'id' => $slot['id'],
            'name' => $slot['name'],
            'configUrl' => route('admin.form-builder.draft.slots.config', [$draftId, $slot['id']]),
            'importUrl' => route('admin.form-builder.draft.slots.import', [$draftId, $slot['id']]),
            'versionUrl' => route('admin.form-builder.draft.slots.version', [$draftId, $slot['id']]),
            'removeUrl' => route('admin.form-builder.draft.slots.destroy', [$draftId, $slot['id']]),
        ], $templates->draftSlots($draftId));
    }

    public function addDraftSlot(Request $request, string $draftId, FormPrintTemplateService $templates): JsonResponse
    {
        $this->browserDraft($request, $draftId, $templates);
        $file = $this->validatedDocx($request);
        if (count($templates->draftSlots($draftId)) >= FormPrintTemplateService::MAX_SLOTS) {
            throw ValidationException::withMessages(['docx' => 'The form can have at most 10 printed templates.']);
        }
        $templates->addDraftSlot($draftId, $file->getClientOriginalName(), File::get($file->getRealPath()));

        return response()->json(['ok' => true, 'slots' => $this->slotResponses($draftId, $templates)]);
    }

    public function removeDraftSlot(Request $request, string $draftId, string $slotId, FormPrintTemplateService $templates): JsonResponse
    {
        $this->browserDraft($request, $draftId, $templates);
        abort_if($templates->draftSlot($draftId, $slotId) === null, 404);
        if (count($templates->draftSlots($draftId)) <= 1) {
            throw ValidationException::withMessages(['slot' => 'At least one printed template must remain.']);
        }
        $templates->removeDraftSlot($draftId, $slotId);

        return response()->json(['ok' => true, 'slots' => $this->slotResponses($draftId, $templates)]);
    }

    public function importDraftSlot(Request $request, string $draftId, string $slotId, FormPrintTemplateService $templates): JsonResponse
    {
        $this->browserDraft($request, $draftId, $templates);
        abort_if($templates->draftSlot($draftId, $slotId) === null, 404);
        $file = $this->validatedDocx($request);
        $templates->replaceDraftSlot($draftId, $slotId, File::get($file->getRealPath()), $file->getClientOriginalName());

        return response()->json(['ok' => true, 'slots' => $this->slotResponses($draftId, $templates)]);
    }

    private function validatedDocx(Request $request): UploadedFile
    {
        $request->validate(['docx' => ['required', 'file', 'max:20480']]);
        $file = $request->file('docx');
        if (! $this->isDocx($file)) {
            throw ValidationException::withMessages(['docx' => 'Please upload a valid Word (.docx) file.']);
        }

        return $file;
    }

    private function browserDraft(Request $request, string $draftId, FormPrintTemplateService $templates): array
    {
        $draft = $templates->readDraft($draftId);
        abort_if($draft === null, 404);
        abort_unless((int) $draft['created_by'] === (int) $request->user()->getKey(), 403);

        return $draft;
    }

    public function slotVersion(Request $request, string $draftId, string $slotId, FormPrintTemplateService $templates): JsonResponse
    {
        $this->browserDraft($request, $draftId, $templates);
        $slot = $templates->draftSlot($draftId, $slotId);
        abort_if($slot === null, 404);

        return response()->json(['version' => $slot['version'], 'closed' => isset($slot['closed_status'])]);
    }

    public function slotConfig(
        Request $request,
        string $draftId,
        string $slotId,
        OnlyOfficeService $onlyOffice,
        FormPrintTemplateService $templates,
    ): JsonResponse {
        $this->browserDraft($request, $draftId, $templates);
        $slot = $templates->draftSlot($draftId, $slotId);
        abort_if($slot === null, 404);
        abort_if($templates->readSlotDocx($draftId, $slotId) === null, 404);
        $templates->clearSlotClosed($draftId, $slotId);

        if (! $onlyOffice->enabled()) {
            return response()->json(['enabled' => false, 'message' => 'The document editor is not configured.'], 503);
        }

        $serverBase = $onlyOffice->appUrl();
        $config = $onlyOffice->editorConfigForDraft(
            $draftId.'-'.$slotId,
            $slot['version'],
            $slot['name'],
            $request->user(),
            $serverBase.'/onlyoffice/draft/'.$draftId.'/document?token='.$this->issueDraft($draftId, self::PURPOSE_DOCUMENT, slotId: $slotId),
            $serverBase.'/onlyoffice/draft/'.$draftId.'/callback?token='.$this->issueDraft($draftId, self::PURPOSE_CALLBACK, minutes: 60 * 12, slotId: $slotId),
        );
        $pluginToken = $this->issueDraft($draftId, self::PURPOSE_PLUGIN, minutes: 60 * 12, slotId: $slotId);
        $config['editorConfig']['plugins'] = [
            'autostart' => [self::PLUGIN_GUID],
            'pluginsData' => [
                rtrim((string) config('app.url'), '/').'/onlyoffice/draft/'.$draftId.'/config.json?token='.$pluginToken,
            ],
        ];
        unset($config['token']);
        $config['token'] = $onlyOffice->sign($config);

        return response()->json([
            'enabled' => true,
            'apiScript' => $onlyOffice->apiScriptUrl(),
            'config' => $config,
            'draftId' => $draftId,
            'version' => $slot['version'],
        ]);
    }

    /**
     * The draft's current version. Session-authenticated (admin). The builder
     * polls this after tearing the editor down on save: the Document Server's
     * final save callback bumps the version via storeDraftRevision(), which is
     * how the page knows the draft holds the user's latest edits before the
     * form save folds (and clears) the draft.
     */
    public function draftVersion(string $draftId, FormPrintTemplateService $templates): JsonResponse
    {
        $draft = $templates->readDraft($draftId);
        abort_if($draft === null, 404);
        $slots = $templates->draftSlots($draftId);
        if ($slots !== []) {
            return response()->json([
                'version' => $slots[0]['version'],
                'closed' => isset($slots[0]['closed_status']),
            ]);
        }

        return response()->json([
            'version' => (int) $draft['version'],
            // Set once the Document Server closes the editing session (status
            // 2/4); the save-flush treats it as "nothing more is coming".
            'closed' => isset($draft['closed_status']),
        ]);
    }

    /**
     * Editor config for a draft. Session-authenticated (admin). Mirrors
     * {@see config()} but issues `did`-scoped tokens against the draft routes.
     */
    public function draftConfig(
        Request $request,
        string $draftId,
        OnlyOfficeService $onlyOffice,
        FormPrintTemplateService $templates,
    ): JsonResponse {
        $slots = $templates->draftSlots($draftId);
        if ($slots !== []) {
            return $this->slotConfig($request, $draftId, $slots[0]['id'], $onlyOffice, $templates);
        }
        if (! $onlyOffice->enabled()) {
            return response()->json([
                'enabled' => false,
                'message' => 'The document editor is not configured. Set ONLYOFFICE_PUBLIC_URL and '
                    .'an ONLYOFFICE_JWT_SECRET of at least 32 characters (HS256 needs a 256-bit key).',
            ], 503);
        }

        $draft = $templates->readDraft($draftId);
        abort_if($draft === null, 404);

        $templates->ensureDraftDocx(
            $draftId,
            (string) $draft['name'],
            isset($draft['form_id']) ? Form::query()->find((int) $draft['form_id']) : null,
            (int) $request->user()->getKey(),
        );
        $version = (int) $draft['version'];

        // Document Server-reachable URLs (its own container hostname).
        $serverBase = $onlyOffice->appUrl();
        $documentUrl = $serverBase.'/onlyoffice/draft/'.$draftId.'/document?token='
            .$this->issueDraft($draftId, self::PURPOSE_DOCUMENT);
        $callbackUrl = $serverBase.'/onlyoffice/draft/'.$draftId.'/callback?token='
            .$this->issueDraft($draftId, self::PURPOSE_CALLBACK, minutes: 60 * 12);

        $config = $onlyOffice->editorConfigForDraft(
            $draftId,
            $version,
            (string) $draft['name'],
            $request->user(),
            $documentUrl,
            $callbackUrl,
        );

        // Browser-reachable plugin config (app origin).
        $pluginToken = $this->issueDraft($draftId, self::PURPOSE_PLUGIN, minutes: 60 * 12);
        $config['editorConfig']['plugins'] = [
            'autostart' => [self::PLUGIN_GUID],
            'pluginsData' => [
                rtrim((string) config('app.url'), '/').'/onlyoffice/draft/'.$draftId.'/config.json?token='.$pluginToken,
            ],
        ];

        unset($config['token']);
        $config['token'] = $onlyOffice->sign($config);

        return response()->json([
            'enabled' => true,
            'apiScript' => $onlyOffice->apiScriptUrl(),
            'config' => $config,
            'draftId' => $draftId,
            'version' => $version,
        ]);
    }

    /**
     * Replace a draft's document with an uploaded .docx. Session-authenticated.
     */
    public function draftImport(Request $request, string $draftId, FormPrintTemplateService $templates): JsonResponse
    {
        $slots = $templates->draftSlots($draftId);
        if ($slots !== []) {
            return $this->importDraftSlot($request, $draftId, $slots[0]['id'], $templates);
        }
        $request->validate([
            'docx' => ['required', 'file', 'max:20480'],
        ]);

        abort_if($templates->readDraft($draftId) === null, 404);

        $file = $request->file('docx');

        if (! $this->isDocx($file)) {
            throw ValidationException::withMessages([
                'docx' => 'Please upload a valid Word (.docx) file.',
            ]);
        }

        $templates->storeDraftRevision($draftId, (string) File::get($file->getRealPath()));

        return response()->json(['ok' => true]);
    }

    /**
     * Serve a draft's .docx bytes to the Document Server. Token-authenticated.
     */
    public function draftDocument(Request $request, string $draftId, FormPrintTemplateService $templates)
    {
        $this->authorizeDraftToken($request, $draftId, self::PURPOSE_DOCUMENT);

        $claims = app(OnlyOfficeService::class)->verify((string) $request->query('token'));
        $slotId = $claims['sid'] ?? null;
        $bytes = $slotId !== null
            ? $templates->readSlotDocx($draftId, (string) $slotId)
            : $templates->readDraftDocx($draftId);
        abort_if($bytes === null, 404);

        // The draft document lives in cache, not on disk, so this returns bytes
        // rather than a file path.
        return response($bytes, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    /**
     * Receive draft save notifications from the Document Server. Mirrors
     * {@see callback()} but persists into the draft cache.
     */
    public function draftCallback(
        Request $request,
        string $draftId,
        FormPrintTemplateService $templates,
        OnlyOfficeService $onlyOffice,
    ): JsonResponse {
        $routeClaims = $onlyOffice->verify((string) $request->query('token'));
        abort_unless(
            is_array($routeClaims) && ($routeClaims['purpose'] ?? null) === self::PURPOSE_CALLBACK
                && ($routeClaims['did'] ?? null) === $draftId,
            403,
        );
        abort_if($templates->readDraft($draftId) === null, 404);
        $slotId = isset($routeClaims['sid']) ? (string) $routeClaims['sid'] : null;
        if ($slotId !== null && $templates->draftSlot($draftId, $slotId) === null) {
            return response()->json(['error' => 0]);
        }

        $body = (array) $request->json()->all();

        $claims = $onlyOffice->verify($body['token'] ?? $request->header('Authorization'));
        if ($claims !== null) {
            $body = (array) ($claims['payload'] ?? $claims);
        } elseif ((string) config('onlyoffice.jwt_secret', '') !== '') {
            Log::warning('OnlyOffice draft callback rejected: body signature missing or invalid.', [
                'draft_id' => $draftId,
            ]);

            return response()->json(['error' => 1], 403);
        }

        $status = (int) ($body['status'] ?? 0);

        if (in_array($status, [2, 6], true)) {
            $downloadUrl = (string) ($body['url'] ?? '');

            if ($downloadUrl === '') {
                Log::warning('OnlyOffice draft callback had a save status but no document URL.', [
                    'draft_id' => $draftId,
                    'status' => $status,
                ]);

                return response()->json(['error' => 1]);
            }

            try {
                $contents = $this->fetchEditedDocument($downloadUrl);
                if ($slotId !== null) {
                    $templates->storeSlotRevision($draftId, $slotId, $contents);
                } else {
                    $templates->storeDraftRevision($draftId, $contents);
                }
            } catch (\Throwable $throwable) {
                report($throwable);

                return response()->json(['error' => 1]);
            }
        }

        // Terminal session states (2 = saved, 4 = closed without changes): mark
        // the draft closed so the builder's save-flush poll stops even when the
        // session produced no new revision. Status 6 (forcesave) leaves the
        // session open, so it is excluded. Runs after storeDraftRevision, which
        // rebuilds the draft metadata and would otherwise drop the marker.
        if (in_array($status, [2, 4], true)) {
            if ($slotId !== null) {
                $templates->markSlotClosed($draftId, $slotId, $status);
            } else {
                $templates->markDraftClosed($draftId, $status);
            }
        }

        return response()->json(['error' => 0]);
    }

    /**
     * A draft's plugin config.json (consumed by the editor). Token-authenticated.
     */
    public function draftPluginConfig(Request $request, string $draftId, OnlyOfficeService $onlyOffice): JsonResponse
    {
        $this->authorizeDraftToken($request, $draftId, self::PURPOSE_PLUGIN);

        $claims = $onlyOffice->verify((string) $request->query('token', ''));
        $pluginUrl = 'plugin/'.$this->issueDraft($draftId, self::PURPOSE_PLUGIN, minutes: 60 * 12, slotId: $claims['sid'] ?? null);

        return response()->json($this->pluginConfigBody($pluginUrl))->withHeaders([
            'Access-Control-Allow-Origin' => $onlyOffice->publicUrl() ?: '*',
        ]);
    }

    /**
     * The plugin SDK's own handshake config for a draft (fetched from inside the
     * plugin iframe, tokenless). Mirrors {@see pluginHandshake()}.
     */
    public function draftPluginHandshake(Request $request, string $draftId, OnlyOfficeService $onlyOffice): JsonResponse
    {
        return response()->json([
            'name' => 'Field tokens',
            'guid' => self::PLUGIN_GUID,
        ])->withHeaders([
            'Access-Control-Allow-Origin' => $onlyOffice->publicUrl() ?: '*',
        ]);
    }

    /**
     * A draft's plugin UI, with the draft's tokens baked in. Token-authenticated.
     * Mirrors {@see plugin()}; replicates the same header mutations because
     * SecurityHeaders is stripped from the draft route group too.
     */
    public function draftPlugin(
        Request $request,
        string $draftId,
        string $token,
        OnlyOfficeService $onlyOffice,
        FormPrintTemplateService $templates,
    ) {
        $this->authorizeDraftToken($request, $draftId, self::PURPOSE_PLUGIN, $token);

        $draft = $templates->readDraft($draftId);
        abort_if($draft === null, 404);

        $response = response()->view('onlyoffice.token-palette', [
            'tokens' => $this->tokensFor(FieldTokenSource::fromDraft((array) $draft['fields'])),
            'sdkBase' => $onlyOffice->publicUrl(),
            // Namespaced so the palette's localStorage never collides with a
            // real form's (int) id.
            'formId' => 'draft-'.$draftId,
            'documentId' => (string) ($onlyOffice->verify($token)['sid'] ?? ($templates->draftSlots($draftId)[0]['id'] ?? 'legacy')),
            'usageUrl' => route('onlyoffice.draft.token-usage', $draftId).'?token='.$token,
        ]);

        $response->headers->remove('X-Frame-Options');
        $response->headers->set(
            'Content-Security-Policy',
            "frame-ancestors 'self' ".($onlyOffice->publicUrl() ?: "'self'"),
        );
        $response->headers->set('Access-Control-Allow-Origin', $onlyOffice->publicUrl() ?: '*');

        return $response;
    }

    public function draftTokenUsage(Request $request, string $draftId, FormPrintTemplateService $templates): JsonResponse
    {
        $this->authorizeDraftToken($request, $draftId, self::PURPOSE_PLUGIN);
        $documents = [];
        foreach ($templates->draftSlots($draftId) as $slot) {
            $contents = $templates->readSlotDocx($draftId, $slot['id']);
            abort_if($contents === null, 404, 'Printed template document is unavailable.');
            $documents[] = [
                'id' => $slot['id'],
                'name' => $slot['name'],
                'keys' => FormTemplateHelper::exactTokenKeysFromDocxContents($contents),
            ];
        }

        return response()->json(['documents' => $documents])->header('Cache-Control', 'no-store');
    }

    /**
     * Mint a short-lived token scoped to one draft and one purpose. Mirrors
     * {@see issue()} but carries the draftId in a `did` claim instead of `tid`.
     */
    private function issueDraft(string $draftId, string $purpose, ?int $minutes = null, ?string $slotId = null): string
    {
        $minutes ??= (int) config('onlyoffice.download_ttl_minutes', 30);

        return app(OnlyOfficeService::class)->sign([
            'did' => $draftId,
            'purpose' => $purpose,
            'sid' => $slotId,
            'iat' => now()->getTimestamp(),
            'exp' => now()->addMinutes(max($minutes, 1))->getTimestamp(),
        ]);
    }

    /**
     * Validate a draft token, returning the draft it names. Mirrors
     * {@see authorizeToken()}: asserts purpose and that the token's `did`
     * matches this route's draftId (a token for draft A can't reach draft B).
     *
     * @return array{form_id: ?int, name: string, fields: array<int, array<string, mixed>>, version: int, created_by: ?int, updated_at: int}
     */
    private function authorizeDraftToken(Request $request, string $draftId, string $purpose, ?string $token = null): array
    {
        $raw = $token ?? (string) $request->query('token', '');
        $claims = app(OnlyOfficeService::class)->verify($raw);

        abort_if($claims === null, 403, 'Invalid or expired editor token.');
        abort_unless(($claims['purpose'] ?? null) === $purpose, 403, 'Token is not valid for this action.');
        abort_unless((string) ($claims['did'] ?? '') === $draftId, 403);
        if (isset($claims['sid']) && $claims['sid'] !== null) {
            abort_if(app(FormPrintTemplateService::class)->draftSlot($draftId, (string) $claims['sid']) === null, 404);
        }

        $draft = app(FormPrintTemplateService::class)->readDraft($draftId);
        abort_if($draft === null, 404);

        return $draft;
    }

    /**
     * Pull the edited document from the Document Server.
     */
    private function fetchEditedDocument(string $url): string
    {
        $response = Http::timeout((int) config('onlyoffice.timeout', 60))
            ->get($this->internalDownloadUrl($url));

        if (! $response->successful()) {
            throw new \RuntimeException('Could not download the edited document (HTTP '.$response->status().').');
        }

        $body = $response->body();

        if ($body === '') {
            throw new \RuntimeException('The edited document came back empty.');
        }

        return $body;
    }

    /**
     * The Document Server builds its cache download URLs on the address the
     * *browser* used to reach it (ONLYOFFICE_PUBLIC_URL). Inside the compose
     * network that host is unreachable from this app — `localhost` there is
     * the app container itself — so fetch through the internal URL instead.
     * The same nginx serves /cache/files on both addresses, and the signed
     * query (md5/expires/shardkey) authorises the download on either.
     */
    private function internalDownloadUrl(string $url): string
    {
        $public = rtrim((string) config('onlyoffice.public_url', ''), '/');
        $internal = rtrim((string) config('onlyoffice.internal_url', ''), '/');

        if ($public === '' || $internal === '' || ! str_starts_with($url, $public.'/')) {
            return $url;
        }

        return $internal.substr($url, strlen($public));
    }

    /**
     * Mint a short-lived token scoped to one template and one purpose.
     */
    private function issue(FormTemplate $template, string $purpose, ?int $minutes = null): string
    {
        $minutes ??= (int) config('onlyoffice.download_ttl_minutes', 30);

        return app(OnlyOfficeService::class)->sign([
            'tid' => (int) $template->getKey(),
            'purpose' => $purpose,
            'iat' => now()->getTimestamp(),
            'exp' => now()->addMinutes(max($minutes, 1))->getTimestamp(),
        ]);
    }

    /**
     * Validate the query token and return the template it names.
     */
    private function authorizeToken(Request $request, Form $form, string $purpose, ?string $token = null): FormTemplate
    {
        // Plugin HTML carries its token in the path (see plugin()); the other
        // endpoints carry it in the query string.
        $raw = $token ?? (string) $request->query('token', '');
        $claims = app(OnlyOfficeService::class)->verify($raw);

        abort_if($claims === null, 403, 'Invalid or expired editor token.');
        abort_unless(($claims['purpose'] ?? null) === $purpose, 403, 'Token is not valid for this action.');

        $template = FormTemplate::query()->find((int) ($claims['tid'] ?? 0));

        abort_if($template === null, 404);
        // A token minted for one form must not reach another's document.
        abort_unless((int) $template->form_id === (int) $form->getKey(), 403);

        return $template;
    }
}
