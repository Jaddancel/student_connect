<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\Template as FormTemplate;
use App\Services\DocxTemplateService;
use App\Services\FormPrintTemplateService;
use App\Services\OnlyOfficeService;
use App\Support\UniversalField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
                rtrim((string) config('app.url'), '/').'/onlyoffice/'.$form->getKey().'/plugin.json?token='.$pluginToken,
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
    public function pluginConfig(Request $request, Form $form): JsonResponse
    {
        $template = $this->authorizeToken($request, $form, self::PURPOSE_PLUGIN);

        $pluginUrl = rtrim((string) config('app.url'), '/').'/onlyoffice/'.$form->getKey().'/plugin?token='
            .$this->issue($template, self::PURPOSE_PLUGIN, minutes: 60 * 12);

        return response()->json([
            'name' => 'Field tokens',
            'guid' => self::PLUGIN_GUID,
            'version' => '1.0.0',
            'minVersion' => '7.0.0',
            'variations' => [[
                'description' => 'Insert form field and profile tokens',
                'url' => $pluginUrl,
                'icons' => ['icon.png', 'icon@2x.png'],
                'isViewer' => false,
                'EditorsSupport' => ['word'],
                'isVisual' => true,
                'isModal' => false,
                // Docked to the left panel so it stays open while typing,
                // mirroring the palette the old rich-text editor had.
                'isInsideMode' => false,
                'initDataType' => 'none',
                'initData' => '',
                'buttons' => [],
                'size' => [320, 600],
            ]],
        ]);
    }

    /**
     * The plugin UI itself, with this form's tokens baked in.
     */
    public function plugin(Request $request, Form $form, OnlyOfficeService $onlyOffice)
    {
        $this->authorizeToken($request, $form, self::PURPOSE_PLUGIN);

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
        $response->headers->set(
            'Content-Security-Policy',
            'frame-ancestors '.($onlyOffice->publicUrl() ?: "'self'"),
        );

        return $response;
    }

    /**
     * Field tokens for the palette: the form's own fields, then the universal
     * profile/org tokens the PDF renderer knows how to resolve.
     *
     * @return array<int, array{key: string, label: string, group: string}>
     */
    private function tokensFor(Form $form): array
    {
        $tokens = [];

        foreach ($form->fields()->orderBy('field_order')->orderBy('id')->get() as $field) {
            $key = (string) ($field->field_key ?? '');

            // Headings and static text hold no submitted value, so a token for
            // them would always print blank. Matches the `printableFields`
            // filter the old rich-text palette used.
            if ($key === '' || in_array((string) $field->field_type, self::NON_PRINTABLE_FIELD_TYPES, true)) {
                continue;
            }

            $tokens[] = [
                'key' => $key,
                'label' => (string) ($field->field_label ?: $key),
                'group' => 'Form fields',
            ];
        }

        $universal = UniversalField::groupedBySource('profile') + UniversalField::groupedBySource('org');

        foreach ($universal as $group => $entries) {
            foreach ($entries as $key => $meta) {
                $tokens[] = [
                    // Namespaced so re-import can tell them from ordinary fields.
                    'key' => 'profile.'.$key,
                    'label' => (string) ($meta['label'] ?? $key),
                    'group' => (string) $group,
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
    private function authorizeToken(Request $request, Form $form, string $purpose): FormTemplate
    {
        $claims = app(OnlyOfficeService::class)->verify((string) $request->query('token', ''));

        abort_if($claims === null, 403, 'Invalid or expired editor token.');
        abort_unless(($claims['purpose'] ?? null) === $purpose, 403, 'Token is not valid for this action.');

        $template = FormTemplate::query()->find((int) ($claims['tid'] ?? 0));

        abort_if($template === null, 404);
        // A token minted for one form must not reach another's document.
        abort_unless((int) $template->form_id === (int) $form->getKey(), 403);

        return $template;
    }
}
