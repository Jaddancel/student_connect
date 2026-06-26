# AI Field Detection Improvement — Progress Tracker

Branch: `ocr-and-template`
Plan: `~/.claude/plans/the-ai-field-detection-enumerated-puzzle.md`

## Goal
Field detection on the form-creation wizard was unreliable: missed fields, wrong
types, often empty/invalid output. Root cause: a 3.8B local model (phi4-mini,
~4 GB VRAM, must stay local) was asked to produce a whole-document JSON array in
one shot. Fix: **hybrid pipeline** — deterministic PHP extraction is the floor
(complete, never empty, reliable types), and the LLM only optionally prunes/retypes.

## Status: IMPLEMENTATION COMPLETE — pending live/browser verification

### Done
- [x] `app/Services/FieldCandidateExtractor.php` (new) — deterministic extractor:
      colon labels, underscore blanks, " | " table cells, short noun-phrases;
      exclusion guards (title/long-sentence/signature); keyword type map; dedup
      keys via `FormTemplateHelper::normalizeFieldKey()`; optional detection.
- [x] `config/services.php` — added `num_ctx` (8192), `keep_alive` (10m),
      `refine` (true) under `ollama`; default model corrected to `phi4-mini`.
- [x] `app/Providers/AppServiceProvider.php` — pass `num_ctx`/`keep_alive` into
      `LlmService` constructor; default model `phi4-mini`.
- [x] `app/Services/LlmService.php` — constructor now takes `$numCtx`,`$keepAlive`;
      `send()` injects `num_ctx` + `keep_alive`; new `refineFields()` (subtractive,
      chunked ≤25, one retry, validates subset, falls back to candidates);
      new `normalizeFields()` choke-point (enum clamp, key dedup, order, etc.).
- [x] `app/Jobs/ProcessFormWizardAiReview.php` — wired: extractor floor →
      optional `refineFields()` (gated by `services.ollama.refine`) → fallback.
- [x] Tests: `tests/Unit/FieldCandidateExtractorTest.php` (9),
      `tests/Feature/LlmServiceRefineTest.php` (6) — **all 15 pass**.

### Remaining / To Verify
- [ ] Live GPU check: run the form wizard on a real DOCX, confirm step-3 shows a
      complete, correctly-typed field list. Test both `OLLAMA_REFINE_FIELDS=false`
      (pure deterministic) and `true` (LLM refine).
- [ ] Optional: pull `phi4-mini` in Ollama if not present; confirm `keep_alive`
      keeps it warm (no cold-start timeout).
- [ ] Decide default for `OLLAMA_REFINE_FIELDS` in `.env` / `.env.example`
      (ships `true`; flip to `false` for deterministic-only if refine underperforms).

## Notes
- Full `php artisan test` shows ~31 unrelated failures — these are because the
  **test DB has not been seeded** (system-setup/first-run redirects to /setup),
  NOT caused by this work. My 15 tests pass.
- Cache shape (`wizard_ai_result_{formId}` = `['status'=>'done','fields'=>[...]]`)
  is unchanged, so `FormCreationWizardController::upsertFormDescriptions()` and the
  revise step needed no changes.
