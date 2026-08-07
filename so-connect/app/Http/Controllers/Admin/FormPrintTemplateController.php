<?php

namespace App\Http\Controllers\Admin;

use App\Forms\FieldType;
use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\Template as FormTemplate;
use App\Services\DocxTemplateService;
use App\Services\FormPrintTemplateService;
use App\Services\OnlyOfficeService;
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

        return response()->json([
            'name' => 'Field tokens',
            'guid' => self::PLUGIN_GUID,
            'version' => '1.1.0',
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
                'buttons' => [],
                'size' => [320, 600],
            ]],
        ])->withHeaders([
            // The editor runs on the Document Server's origin but fetches this
            // config.json from the app's origin. Without this the browser blocks
            // the cross-origin read and the plugin silently never registers.
            'Access-Control-Allow-Origin' => $onlyOffice->publicUrl() ?: '*',
        ]);
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
        $this->authorizeToken($request, $form, self::PURPOSE_PLUGIN, $token);

        $response = response()->view('onlyoffice.token-palette', [
            'tokens' => $this->tokensFor($form),
            // The plugin SDK is served by the Document Server, and this page
            // runs in the browser, so it needs the public URL.
            'sdkBase' => $onlyOffice->publicUrl(),
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

    /**
     * Field tokens for the palette: the form's own fields, then the universal
     * profile/org tokens the PDF renderer knows how to resolve. Each token
     * carries a field-type `icon` and a category `group` so the in-editor
     * palette can render icons + grouped sections like the form builder does.
     *
     * @return array<int, array{key: string, label: string, icon: string, group: string}>
     */
    private function tokensFor(Form $form): array
    {
        $catalog = FieldType::catalog();

        // Bucket the form's fields by their display group so the palette can
        // render them Basic → Choice → Media → … in a stable order.
        $fieldsByGroup = [];

        foreach ($form->fields()->orderBy('field_order')->orderBy('id')->get() as $field) {
            $key = (string) ($field->field_key ?? '');

            // Headings and static text hold no submitted value, so a token for
            // them would always print blank. Matches the `printableFields`
            // filter the old rich-text palette used.
            if ($key === '' || in_array((string) $field->field_type, self::NON_PRINTABLE_FIELD_TYPES, true)) {
                continue;
            }

            $meta = $catalog[$field->field_type] ?? null;
            $group = isset($meta['group']) ? (self::FIELD_GROUP_LABELS[$meta['group']] ?? 'Form fields') : 'Form fields';

            $fieldsByGroup[$group][] = [
                'key' => $key,
                'label' => (string) ($field->field_label ?: $key),
                'icon' => (string) ($meta['icon'] ?? 'text'),
                'group' => $group,
            ];
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

    /**
     * Pull the edited document from the Document Server.
     */
    private function fetchEditedDocument(string $url): string
    {
        // The server hands back a URL on its own hostname; inside compose that
        // may be its container name or an internal alias, both routable.
        $response = Http::timeout((int) config('onlyoffice.timeout', 60))->get($url);

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
