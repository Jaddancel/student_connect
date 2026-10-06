# Local AI Assistant Chatbot for Student Connect

> **Handoff document.** This was planned in a cloud container with no GPU; **nothing has been built yet**. Implementation runs on the target machine (Ryzen 7 + discrete NVIDIA, 8 GB VRAM). All paths are **relative to the repo root** — the Laravel app lives in `so-connect/`, not the repo root.
>
> **First action on approval:** save this file to `so-connect/docs/PLAN_AI_ASSISTANT.md` and commit it. That matches the repo's existing `docs/PLAN_*.md` convention and gives the implementing session a durable reference.
>
> **Branch:** `claude/hello-pv7zep`.

---

## Context

Student Connect has grown to **200+ routes** in a single `so-connect/routes/web.php`, spread over form building, seven bespoke request queues, records/audit, scoring, waivers, ID templates, and a superadmin area. The navigation surface is large and role-dependent — an admin sees four sidebar groups, a superadmin a different eight items, an officer something else again. There is **no user manual in the repo**; the closest thing is a QA script (`so-connect/docs/VERIFICATION-HANDOFF.md`) written for testers, not operators.

New admins and student officers have no way to discover _"where do I approve a workplan?"_ or _"how do I publish a form?"_ short of asking someone.

This adds a **local, self-hosted AI assistant** — a floating chat button in the navbar that answers "how do I…" questions grounded in a generated index of the dashboard and deep-links to the right page. Everything runs on-premises; no data leaves the machine.

**Decisions already made (do not relitigate):**

- Model: **Qwen3.5 9B** at Q4_K_M (~6.6 GB VRAM), served by Ollama on the discrete NVIDIA GPU.
- Scope: **explain + deep-link.** Read-only. No DB queries, no actions on the user's behalf.
- Language: **mirror the user** — English question → English answer; Filipino/Taglish → Filipino.
- **No fine-tuning.** The UI changes every sprint; baking navigation into weights makes stale knowledge uncorrectable. All grounding is via retrieval.

_Considered and rejected: Gemma 4 12B-it (weaker tool calling, restrictive license) and Qwen-SEA-LION-v4-8B (best Filipino, but Ollama GGUF availability unconfirmed — revisit if Qwen3.5's Filipino output disappoints in step 3 of the manual pass)._

---

## Codebase facts — established, don't re-derive

The project's `CLAUDE.md` is **stale on several points**. Verified state:

| Claim in CLAUDE.md                   | Reality                                                                                                                                                                    |
| ------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `routes/admin.php`, `routes/api.php` | **Neither exists.** All routes (public, admin, superadmin, JSON API) are in one 1083-line `so-connect/routes/web.php`. `bootstrap/app.php` registers no `api:` route file. |
| `tailwind.config.js`                 | Tailwind **v4** — config is the `@theme` block in `so-connect/resources/css/app.css`.                                                                                      |
| `GOOGLE-AUTH-SETUP.md`               | Not in the repo.                                                                                                                                                           |
| `app/Policies/`, Gates               | **No policies, no gates, no enums.** Authorization is entirely route middleware keyed on `user_type`.                                                                      |
| Repo root = Laravel root             | App is in `so-connect/`. Root has only a stub `composer.json`.                                                                                                             |

**Roles:** `user_type` 1 = SuperAdmin, 2 = Admin, 3 = officer/president. Middleware aliases registered in `so-connect/bootstrap/app.php:22-32` (`admin`, `superadmin`, `officer.or.admin`, …). Middleware idiom is `(int) $user->user_type !== 2` → `abort(403, '<message>')`.

**Route declaration style:** no `Route::prefix()` or `Route::name()` groups anywhere. URI and route name are spelled out in full on every line. AJAX endpoints are standalone with inline `->middleware('auth')`, e.g. `routes/web.php:1080`:

```php
Route::post('/waiver-scan', [\App\Http\Controllers\WaiverScanController::class, 'scan'])->middleware('auth')->name('waiver.scan');
```

Only one throttled route exists in the whole codebase (`throttle:20,1` on form submit). No `RateLimiter::for()` anywhere.

**Service convention** — `so-connect/app/Services/OcrClient.php` is the template: **no constructor**, no service-provider binding, config read inline per method, `Http::timeout()` facade, injected as a **controller method parameter**. Failure is always graceful — `Log::warning` + return a same-shaped array with `ok => false` and a `note`; never rethrows.

**Controller convention** — degraded states return **HTTP 200 with a status sentinel**, not an error code. `IdScanController` wraps `$request->validate()` in `try/catch (ValidationException)` and returns JSON, because `fetch()` can't follow Laravel's validation redirect. Copy that.

**Frontend** — Alpine bundled via Vite (not CDN). Components are `export function name(config)` returning an object literal with a JSDoc header, registered in `so-connect/resources/js/app.js` as `Alpine.data('name', name)` before `Alpine.start()`. Native `fetch` with `'X-CSRF-TOKEN'` is the prevailing HTTP pattern. **No SSE/streaming anywhere.** Dark mode is class-based on `<html>` via `@custom-variant dark (&:is(.dark *))`.

**Navbar** — `so-connect/resources/views/layouts/app-header.blade.php:226-247`. The right-side group holds the theme toggle then `<x-header.notification-dropdown />`. The canonical circular icon-button class string (identical on both) is at line 229. z-index landscape: header `z-99999`, lightbox `z-[99999]`, file-alert toast `z-[100000]`.

**Free assets:** `App\Helpers\MenuHelper::getIconSvg('chat')` (`MenuHelper.php:277`) is an unused speech-bubble glyph. No new icon needed.

---

## Architecture

```
Browser (Alpine)  ──POST /assistant/chat──▶  AssistantController
                                                  │
                                   AssistantKnowledgeBase (role-filtered page index, cached)
                                                  │
                                             LlmClient ──HTTP──▶  ollama sidecar (Qwen3.5 9B, GPU)
                                                  │
                                   route-token post-processing → safe deep links
```

### Two design choices worth stating up front

**1. The page index is generated, not hand-written.** `App\Helpers\MenuHelper::getMenuGroups()` already builds the full role-aware navigation tree — including the _dynamic_ per-form request queues driven by the `forms` table (`MenuHelper.php:105-124`). Combined with Laravel's route table and the `pageTitle` strings passed to `<x-common.page-breadcrumb>`, that's ~90% of the index for free, and it stays correct as the app changes. Only prose is hand-authored.

**2. The index is filtered by `user_type` before it reaches the model.** An officer's prompt never contains superadmin pages. Correctness win (no advice that 403s) _and_ an authorization boundary — the model cannot leak pages the user can't reach.

---

## Implementation

### 1. Ollama sidecar

**`so-connect/compose.yaml`** — add alongside the existing `ocr` service, following its minimal style (image/volumes/ports/networks only). Deliberately **not** in `laravel.test`'s `depends_on`, matching how `ocr` is wired — the app must boot with the assistant down.

```yaml
# Local LLM sidecar for the in-app assistant. Service name MUST be `ollama`
# to match LLM_SERVICE_URL=http://ollama:11434. First run pulls the model
# (~6.6 GB) — expect a slow initial start.
ollama:
  image: ollama/ollama:latest
  volumes:
    - sail-ollama:/root/.ollama
    - "./docker/ollama/entrypoint.sh:/entrypoint.sh"
  entrypoint: ["/bin/bash", "/entrypoint.sh"]
  environment:
    LLM_MODEL: "${LLM_MODEL:-qwen3.5:9b-q4_K_M}"
  ports:
    - "${LLM_PORT:-11434}:11434"
  networks:
    - sail
```

Add `sail-ollama: { driver: local }` to the `volumes:` block beside `sail-mysql`.

**`so-connect/compose.gpu.yaml`** (new) — GPU access as an opt-in overlay, so teammates without an NVIDIA card aren't broken:

```yaml
services:
  ollama:
    deploy:
      resources:
        reservations:
          devices:
            - driver: nvidia
              count: 1
              capabilities: [gpu]
```

Activate by adding `COMPOSE_FILE=compose.yaml:compose.gpu.yaml` to `.env`.

> **WSL2 note (this project develops on Windows + WSL).** GPU passthrough needs the NVIDIA driver installed **on Windows**, not inside WSL. With Docker Desktop + WSL2 backend, that plus the overlay above is sufficient — do **not** install `nvidia-container-toolkit` inside the distro. If you run Docker natively inside WSL instead of Docker Desktop, you _do_ need the toolkit plus `nvidia-ctk runtime configure --runtime=docker`. Verify with `docker run --rm --gpus all ubuntu nvidia-smi` before touching this repo. Without the overlay Ollama silently falls back to CPU — functional but slow enough to feel broken.

**`so-connect/docker/ollama/entrypoint.sh`** (new) — start the server, pull the model if absent, wait. Mark it executable (`git update-index --chmod=+x` if committing from Windows).

### 2. Config & env

**`so-connect/config/services.php`** — new block directly after `'ocr'`, matching its shape:

```php
'llm' => [
    'url' => env('LLM_SERVICE_URL', 'http://ollama:11434'),
    'timeout' => (int) env('LLM_TIMEOUT', 120),
    'model' => env('LLM_MODEL', 'qwen3.5:9b-q4_K_M'),
],
```

**`so-connect/.env.example` AND `so-connect/.env.check`** — both files carry the OCR block verbatim today; add to both to keep them in sync:

```
# Local LLM sidecar (Ollama) powering the in-app assistant. Default host works
# inside docker-compose; use http://127.0.0.1:11434 when Ollama runs on the host.
# The assistant degrades gracefully if unreachable.
LLM_SERVICE_URL=http://ollama:11434
LLM_TIMEOUT=120
LLM_MODEL=qwen3.5:9b-q4_K_M
```

### 3. Knowledge base — `so-connect/app/Services/AssistantKnowledgeBase.php` (new)

One public method: `forUser(?User $user): array` returning `['pages' => [...], 'workflows' => string]`.

- **Pages** built by walking `MenuHelper::getMenuGroups()` (already role-filters, already resolves the dynamic per-form queues), then enriching each entry with its route name via a `Route::getRoutes()` lookup on the path. Shape mirrors the existing `App\Helpers\DashboardSearchHelper`: `name`, `path`, `route`, `group`, `keywords`.
- **Supplement** with reachable detail pages absent from the sidebar (`admin.form-requests.show`, `admin.scoring.rules.edit`, …) by enumerating the route table and filtering to routes whose middleware the user satisfies.
- **Descriptions** from `App\Forms\SystemFunction::catalog()` (one-sentence purpose blurbs per system function — the best prose in the codebase) and `Form::description_text` for form pages.
- **Exclude** the unauthenticated TailAdmin demo pages (`/blank`, `/buttons`, `/avatars`, … `routes/web.php:730-799`) — template scaffolding, not product surface.
- **Cache** via `Cache::remember("assistant.kb.{$userType}", 3600, ...)`. Per-form queues change when admins publish forms; keying on user_type and accepting an hour of staleness is the right trade.

⚠️ `DashboardSearchHelper` is a useful _shape_ reference but is stale — it points at `/superadmin/request-types`, which has no route. Don't copy its data.

**`so-connect/resources/assistant/workflows.md`** (new) — hand-authored prose for multi-step flows the generated index can't express: creating and publishing a form, the request approve/decline lifecycle, scoring rule triggers, waiver review, ID template zones. Source material: `docs/VERIFICATION-HANDOFF.md` §B1–B3 and §6, plus the unusually explanatory route comments in `web.php` (lines 255-261, 274-276, 294-297). Keep under ~2000 tokens — it ships in every prompt.

### 4. `so-connect/app/Services/LlmClient.php` (new)

Mirror `OcrClient` exactly — no constructor, config inline per method, `Http::timeout()`, sentinel-shaped graceful failure documented in the class docblock.

```php
/**
 * @return array{ok: bool, reply: string, note?: string}
 */
public function chat(array $messages, string $systemPrompt): array
```

- POST to `rtrim(config('services.llm.url'), '/').'/api/chat'`
- Body: `['model' => ..., 'messages' => [...], 'stream' => false, 'think' => false, 'options' => ['temperature' => 0.3, 'num_ctx' => 8192]]`
- **`think => false` is load-bearing** — Qwen3.5's hybrid reasoning mode burns 2–5× the tokens and would make the bot feel sluggish on an 8 GB card.
- Non-200 → `Log::warning` with `['status' => $response->status()]`, return `['ok' => false, 'note' => 'assistant error']`
- `catch (\Throwable $e)` → `Log::warning('LLM unreachable: '.$e->getMessage())`, return `['ok' => false, 'note' => 'assistant unavailable']`
- Never rethrows.

### 5. `so-connect/app/Http/Controllers/AssistantController.php` (new)

Top-level, **not** `Admin/` — matches where the other fetch-driven endpoints live (`WaiverScanController`, `IdScanController`).

```php
public function chat(Request $request, LlmClient $llm, AssistantKnowledgeBase $kb): JsonResponse
```

- Wrap `$request->validate()` in `try/catch (ValidationException)` returning JSON (the `IdScanController` pattern).
- Validate: `messages` array max 20; each `role` in `user,assistant`; `content` string max 2000. `current_path` optional string.
- System prompt = role-filtered page index + `workflows.md` + the user's current page + language-mirroring instruction + **"only reference pages from the list above"**.
- Degraded states → **HTTP 200 with a status sentinel**.

**Deep-link safety.** Instruct the model to emit `[[route:admin.form-builder.index]]` tokens rather than raw URLs, then post-process in PHP: resolve each via `route()`, **drop any token not in the user's permitted index**, return a `links` array alongside the reply text. A hallucinated route name becomes nothing rather than a 404 or a 403.

### 6. Route — `so-connect/routes/web.php`

Standalone line with inline middleware, matching `waiver.scan` / `signature.verify`:

```php
// In-app AI assistant. Auth-only: the knowledge base is filtered by user_type,
// so an unauthenticated caller has no index to ground against. Throttled
// because each call occupies the single local GPU for several seconds.
Route::post('/assistant/chat', [\App\Http\Controllers\AssistantController::class, 'chat'])
    ->middleware(['auth', 'throttle:20,1'])->name('assistant.chat');
```

### 7. Frontend

**`so-connect/resources/views/components/assistant/chat-widget.blade.php`** (new) — trigger button + floating panel in one component.

- Reuse the canonical circular icon-button classes from the theme toggle (`app-header.blade.php:229`) **verbatim**, so it's visually identical to its neighbours.
- Icon: `MenuHelper::getIconSvg('chat')`.
- Panel: `fixed bottom-6 right-6`, `z-[99990]` (below the file-alert toast at `z-[100000]` and the lightbox at `z-[99999]`). Style from `components/header/notification-dropdown.blade.php` — same `rounded-2xl border border-gray-200 bg-white shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark` and the same `x-transition` set.
- `x-cloak` on the panel (CSS already defined at `layouts/app.blade.php:153`).

**`so-connect/resources/views/layouts/app-header.blade.php`** — one line after the notification dropdown (line 246):

```blade
        <!-- AI Assistant -->
        <x-assistant.chat-widget />
```

**`so-connect/resources/js/components/assistant-chat.js`** (new) — `export function assistantChat(config)` returning the Alpine object literal, JSDoc header explaining the message model, server config (`endpoint`, `csrf`) passed from Blade.

- Native `fetch` with `'X-CSRF-TOKEN': this.csrf`.
- **Non-streaming for v1** with a typing indicator. No SSE helper exists in the codebase; introducing one is real work and can follow once the bot proves useful.
- Persist history to `sessionStorage`; clear button in the panel header.
- Render `links` as `<a>` chips below each reply.

**`so-connect/resources/js/app.js`** — add the import beside the other 11 and `Alpine.data('assistantChat', assistantChat);` before `Alpine.start()`.

### 8. Docs & tests

- **`so-connect/docs/assistant-contract.md`** (new) — request/response wire shape and the `[[route:…]]` token contract, mirroring how `docs/ocr-template-contract.md` documents the OCR boundary. Cross-reference from the `LlmClient` docblock.
- **`so-connect/tests/Feature/AssistantTest.php`** (new) — `Http::fake()` the Ollama endpoint. Cover: unauthenticated → redirect; officer's index excludes superadmin routes; `[[route:…]]` tokens outside the user's index are stripped; sidecar down → 200 with `ok:false`; oversized payload rejected cleanly as JSON.

---

## Verification (on the target machine)

```bash
# Prerequisite — confirm Docker can see the GPU at all:
docker run --rm --gpus all ubuntu nvidia-smi

cd so-connect

# 1. Bring up the sidecar (ensure COMPOSE_FILE is set in .env first)
./vendor/bin/sail up -d ollama
./vendor/bin/sail logs -f ollama          # watch the ~6.6 GB first pull

# 2. Confirm the model is resident ON THE GPU, not spilled to CPU
docker compose exec ollama ollama ps      # PROCESSOR column must read 100% GPU
docker compose exec ollama nvidia-smi     # expect ~6.5-7 GB used

# 3. Sidecar reachable from the app container
./vendor/bin/sail exec laravel.test curl -s http://ollama:11434/api/tags

# 4. Tests, then reseed (artisan test wipes the dev DB)
./vendor/bin/sail artisan test --filter=Assistant
./vendor/bin/sail artisan migrate:fresh --seed

# 5. Rebuild frontend — stale Vite bundles are a known failure mode here
nvm use 24 && npm run build
```

**Manual pass** (hard-refresh with Ctrl+Shift+R first):

1. Log in as admin → chat icon sits flush with the theme toggle and bell, same size and hover treatment. Toggle dark mode; panel follows.
2. Ask _"how do I publish a form?"_ → steps from `workflows.md` plus a working deep link to `/admin/form-builder`.
3. Ask _"paano ko ma-approve ang workplan?"_ → **reply in Filipino** with a link to `/admin/workplan-requests`. _(If Filipino output is poor, this is the point to revisit Qwen-SEA-LION-v4-8B.)_
4. Log in as an **officer** → ask _"where are the database backups?"_ → must say it has no such page, **not** link to `/superadmin/backups`.
5. `./vendor/bin/sail stop ollama` → panel shows "assistant unavailable"; **the rest of the dashboard is unaffected**.
6. Latency tolerable — expect ~2–6 s to full reply at 9B/Q4 on 8 GB.

---

## Risks

- **VRAM headroom is thin.** 6.6 GB of weights on an 8 GB card leaves ~1.4 GB for KV cache. `num_ctx` is pinned to 8192 for this reason. If `ollama ps` shows any CPU spill, drop to `num_ctx: 4096` before considering a smaller model.
- **First run downloads ~6.6 GB** — same "be patient" caveat as the OCR sidecar's first build.
- **Single GPU, serialised requests.** Concurrent users queue. `throttle:20,1` limits the damage; if several admins use it simultaneously, revisit with a smaller model or a request queue.
- **The generated index depends on `MenuHelper`.** An admin page added without a sidebar entry is invisible to the assistant. Note this in `docs/assistant-contract.md` as a maintenance requirement.
- **Model tag drift.** `qwen3.5:9b-q4_K_M` should be confirmed against `ollama.com/library/qwen3.5` at implementation time; pin whatever tag actually resolves.

## Explicitly out of scope

Streaming responses (SSE), live DB queries ("how many pending requests?"), the assistant acting on the user's behalf, and vector-embedding retrieval — the role-filtered index is small enough (~3–5 K tokens for an admin) to send whole.

## Unrelated finding — flagged, not addressed

Four routes in `so-connect/routes/web.php` have **no auth middleware**: `GET /api/users` (line 1034, returns all users), `GET /api/superadmin/data/export` (1046), and `POST /api/superadmin/data/import` (1048, which also calls `withoutMiddleware(VerifyCsrfToken)`). Out of scope for this work — worth a separate fix.
