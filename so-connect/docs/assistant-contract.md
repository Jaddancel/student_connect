# Assistant Contract

This freezes the request/response shape between the browser widget
(`resources/js/components/assistant-chat.js`) and `AssistantController`, and
the contract between `AssistantController` and the Ollama sidecar via
`App\Services\LlmClient` — so any side can be reworked independently as long
as it honours this shape. Mirrors how `docs/ocr-template-contract.md`
documents the OCR boundary.

## Request

`POST /assistant/chat` (auth required, `throttle:20,1`) — JSON body:

```json
{
  "messages": [
    { "role": "user", "content": "how do I publish a form?" },
    { "role": "assistant", "content": "..." }
  ],
  "current_path": "/admin/form-builder"
}
```

- `messages` — required array, max 20 entries, oldest first. Each entry's
  `role` is `user` or `assistant`; `content` is a string, max 2000 characters.
- `current_path` — optional string, max 2048 characters. The page the user is
  currently on; folded into the system prompt for context only, never
  validated against the route table.

## Response

Always HTTP 200 — degraded states are a status sentinel, not an HTTP error,
so the widget never has to special-case a failed `fetch()`.

```json
{ "ok": true, "reply": "Go to Form Builder, click New Form, ...", "links": [{ "name": "Form Builder", "path": "/admin/form-builder" }] }
```

```json
{ "ok": false, "reply": "", "links": [], "note": "assistant unavailable" }
```

- `ok` — false for an invalid payload, no reachable pages for this user
  (shouldn't happen for an authenticated request), or the Ollama sidecar
  being unreachable or erroring.
- `reply` — the model's answer with every `[[route:…]]` token stripped out
  (see below). Empty when `ok` is false.
- `links` — deep links extracted from the reply, in the order they appeared.
  Each is `{name, path}` — display name and a same-origin path to render as
  an `<a>` chip. Empty when `ok` is false or the reply cited no page.
- `note` — present only when `ok` is false: a short machine-readable reason
  (`invalid request`, `assistant unavailable`, `assistant error`). Not meant
  for verbatim display — the widget shows its own generic message instead.

## The `[[route:…]]` token contract

The model is never allowed to write a raw URL or a markdown link. Its system
prompt (`AssistantController::buildSystemPrompt()`) lists every page the
current user can reach, each annotated with `[[route:<name>]]` when that
route name uniquely identifies one page for this user, and is instructed to
cite recommendations using exactly that token.

`AssistantController::extractLinks()` then:

1. Strips every `[[route:…]]` token out of the reply text unconditionally.
2. Resolves each token's route name against the **same per-user page index**
   built for the prompt (`AssistantKnowledgeBase::forUser()`) — never against
   the full route table, and never by calling `route()` with guessed
   parameters.
3. A route name that doesn't appear in that index — hallucinated, out of the
   user's role, or ambiguous because several concrete pages share it (e.g.
   every per-form request queue resolves to the same
   `admin.form-requests.index` route name) — contributes nothing to `links`.
   It's dropped silently, never surfaced as a broken or unauthorized link.

Net effect: a hallucinated or out-of-role route name becomes plain text with
no link — never a 404 or a 403.

## Failure behaviour

`LlmClient::chat()` mirrors `OcrClient`: any timeout, non-200, or unreachable
sidecar is caught, logged via `Log::warning`, and turned into
`['ok' => false, 'reply' => '', 'note' => 'assistant unavailable']` — never an
exception into the request. The rest of the dashboard is unaffected by the
sidecar being down.

## Maintenance requirement

The page index is *generated* from `App\Helpers\MenuHelper::getMenuGroups()`,
not hand-maintained. **An admin-facing page added without a sidebar entry in
`MenuHelper` is invisible to the assistant** — it can't explain or link to a
page it was never told about. When adding a page that should be discoverable
but deliberately doesn't belong in the sidebar, add it to
`AssistantKnowledgeBase`'s supplemental route enumeration instead (zero
required route parameters only — see that class's docblock).

## Caching

`AssistantKnowledgeBase::forUser()` caches the page index for one hour, keyed
by `user_type` — and, for user_type 3, further split by officer/president vs.
plain member, since those see very different menus. A newly published form or
sidebar change can take up to an hour to reach the assistant;
`Cache::forget()` the relevant `assistant.kb.*` key if that lag ever matters
operationally.
