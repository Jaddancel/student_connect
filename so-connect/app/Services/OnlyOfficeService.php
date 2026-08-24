<?php

namespace App\Services;

use App\Models\Template as FormTemplate;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;

/**
 * Protocol layer for the OnlyOffice Document Server.
 *
 * Three parties talk here and each needs a different URL: the browser loads
 * the editor bundle from the *public* URL, the Document Server fetches and
 * saves the .docx against the *app* URL (its own container hostname, not
 * localhost), and Laravel calls the server's services on the *internal* URL.
 *
 * Every payload in both directions is HS256-signed with a shared secret, so a
 * request that cannot be verified is rejected rather than trusted — the
 * callback endpoint is necessarily unauthenticated in the session sense.
 *
 * Note this targets Community Edition, where the Automation API
 * (`docEditor.createConnector()`) is unavailable: the host page cannot push
 * content into the open document. Token insertion therefore lives in the
 * Document Server plugin under docker/onlyoffice/plugins/token-palette.
 */
class OnlyOfficeService
{
    private const ALGORITHM = 'HS256';

    /**
     * HS256 keys must be at least 256 bits; php-jwt throws rather than signing
     * with anything shorter. Checking here turns a would-be 500 mid-edit into
     * an "editor not configured" notice.
     */
    private const MIN_SECRET_BYTES = 32;

    /**
     * Is the editor usable? A blank public URL or an unusably short secret
     * disables it, so the form builder degrades to a clear message instead of
     * a broken iframe.
     */
    public function enabled(): bool
    {
        return $this->publicUrl() !== '' && strlen($this->secret()) >= self::MIN_SECRET_BYTES;
    }

    public function publicUrl(): string
    {
        return rtrim((string) config('onlyoffice.public_url', ''), '/');
    }

    public function internalUrl(): string
    {
        return rtrim((string) config('onlyoffice.internal_url', ''), '/');
    }

    public function appUrl(): string
    {
        return rtrim((string) config('onlyoffice.app_url', ''), '/');
    }

    /**
     * URL of the editor bundle the blade view loads in a <script> tag.
     */
    public function apiScriptUrl(): string
    {
        return $this->publicUrl().'/web-apps/apps/api/documents/api.js';
    }

    /**
     * Build the editor config object handed to `new DocsAPI.DocEditor(...)`,
     * with its signature attached.
     *
     * @param  string  $documentUrl  where the server downloads the .docx (signed, app-reachable)
     * @param  string  $callbackUrl  where the server posts saves back
     * @return array<string, mixed>
     */
    public function editorConfig(
        FormTemplate $template,
        User $user,
        string $documentUrl,
        string $callbackUrl,
        bool $canEdit = true,
    ): array {
        return $this->assembleEditorConfig(
            $this->documentKey($template),
            ((string) ($template->template_name ?: 'Printed template')).'.docx',
            $user,
            $documentUrl,
            $callbackUrl,
            $canEdit,
        );
    }

    /**
     * Editor config for a non-persistent Step-2 draft, which has no Template
     * row — the document key is derived from the draftId + its cache version
     * instead. {@see FormPrintTemplateService} for the draft lifecycle.
     *
     * @return array<string, mixed>
     */
    public function editorConfigForDraft(
        string $draftId,
        int $version,
        string $name,
        User $user,
        string $documentUrl,
        string $callbackUrl,
        bool $canEdit = true,
    ): array {
        return $this->assembleEditorConfig(
            $this->draftDocumentKey($draftId, $version),
            (trim($name) !== '' ? trim($name) : 'Printed template').'.docx',
            $user,
            $documentUrl,
            $callbackUrl,
            $canEdit,
        );
    }

    /**
     * Build and sign the editor config object handed to `new DocsAPI.DocEditor`.
     * Shared by the persisted-template and draft paths, which differ only in
     * how the document key and title are derived.
     *
     * @return array<string, mixed>
     */
    private function assembleEditorConfig(
        string $documentKey,
        string $title,
        User $user,
        string $documentUrl,
        string $callbackUrl,
        bool $canEdit = true,
    ): array {
        $config = [
            'documentType' => 'word',
            'document' => [
                'fileType' => 'docx',
                'key' => $documentKey,
                'title' => $title,
                'url' => $documentUrl,
                'permissions' => [
                    'edit' => $canEdit,
                    'download' => true,
                    'print' => true,
                    // Nothing here is a shared document, so the collaboration
                    // affordances only add confusion.
                    'comment' => false,
                    'review' => false,
                ],
            ],
            'editorConfig' => [
                'callbackUrl' => $callbackUrl,
                'lang' => 'en',
                'mode' => $canEdit ? 'edit' : 'view',
                'user' => [
                    'id' => (string) $user->getKey(),
                    'name' => $this->displayName($user),
                ],
                'customization' => [
                    'autosave' => true,
                    'forcesave' => true,
                    'compactHeader' => false,
                    'help' => false,
                    'chat' => false,
                    'comments' => false,
                ],
            ],
        ];

        $config['token'] = $this->sign($config);

        return $config;
    }

    /**
     * Sign a payload for the Document Server.
     *
     * @param  array<string, mixed>  $payload
     */
    public function sign(array $payload): string
    {
        return JWT::encode($payload, $this->secret(), self::ALGORITHM);
    }

    /**
     * Verify a token the Document Server sent us.
     *
     * @return array<string, mixed>|null decoded claims, or null if untrustworthy
     */
    public function verify(?string $token): ?array
    {
        if ($token === null || trim($token) === '' || $this->secret() === '') {
            return null;
        }

        // The server sends the header form as "Bearer <token>".
        $token = Str::startsWith($token, 'Bearer ') ? substr($token, 7) : $token;

        try {
            return (array) json_decode(
                json_encode(JWT::decode($token, new Key($this->secret(), self::ALGORITHM))),
                true,
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Cache key for the document. The Document Server treats a key as an
     * immutable document version, so this has to change whenever the stored
     * file does — reuse a stale key and everyone keeps editing the old content.
     */
    public function documentKey(FormTemplate $template): string
    {
        $fingerprint = implode('|', [
            (int) $template->getKey(),
            (int) ($template->version ?? 1),
            (string) ($template->docx_path ?? ''),
            (string) ($template->updated_at?->getTimestamp() ?? 0),
        ]);

        return 'tpl-'.$template->getKey().'-'.substr(sha1($fingerprint), 0, 24);
    }

    /**
     * Document key for a draft. The `draft-` prefix keeps it from ever
     * colliding with a real template's `tpl-` key, and it moves only when a
     * callback save bumps the draft version — so the Document Server reloads
     * the document only on an actual content change, not on every re-sync.
     */
    public function draftDocumentKey(string $draftId, int $version): string
    {
        return 'draft-'.$draftId.'-'.substr(sha1($draftId.'|'.$version), 0, 16);
    }

    private function displayName(User $user): string
    {
        $profile = $user->profile()->first();

        $name = trim(implode(' ', array_filter([
            (string) ($profile->first_name ?? ''),
            (string) ($profile->last_name ?? ''),
        ])));

        return $name !== '' ? $name : (string) ($user->user_email ?? 'Editor');
    }

    private function secret(): string
    {
        return (string) config('onlyoffice.jwt_secret', '');
    }
}
