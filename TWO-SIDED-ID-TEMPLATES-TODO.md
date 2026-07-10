# Two-Sided ID Templates + Vertical Default — execution tracker

Plan file: `~/.claude/plans/re-id-templates-curried-castle.md`
Branch: `wysiwyg-template-editor`.

Goal: ID templates gain a **back** side (front + back zones, both required); the signup
wizard scans **both** sides sequentially and merges OCR; **vertical** is the default
orientation in the editor and the camera overlay. OCR sidecar contract stays frozen.

## Phase 1 — Data model (additive `back_*` columns)
- [ ] Extend migration `2026_07_07_000001_create_id_templates_table.php`:
      `back_image_path`, `back_image_width/height`, `back_zones` (json), `orientation`
- [ ] `IdTemplate` model: fillable + casts; `toScannerPayload(string $side = 'front')`

## Phase 2 — Controller / validation
- [ ] `Admin/IdTemplateController::validatePayload()` — parallel back rules
      (`back_zones` required min:1, per-zone rules, bounds, PCRE) + `orientation`
- [ ] `blankData()` / `dataFromModel()` carry back_* + orientation
- [ ] `destroy()` also deletes `back_image_path`

## Phase 3 — Editor JS + blade (side switcher + orientation)
- [ ] `id-template-editor.js` — `currentSide`, per-side `sides` slice, `switchSide()`,
      orientation-aware `targetOutputSize()`, two-sided `serialize()`, both-sides save guard
- [ ] `_form.blade.php` — Front/Back tab bar + done badges, Orientation select

## Phase 4 — Signup wizard (sequential front→back)
- [ ] `id-scan-wizard.js` — `scanSide`, back capture/preview, `scan(file, side)`, merge,
      write back file to `#id_photo_back`, orientation-driven overlay
- [ ] `student-leader-directory.blade.php` — two-phase capture UI, vertical overlay,
      pass active template `orientation` into config

## Phase 5 — OCR path (one call per side)
- [ ] `IdScanController::scan()` — validate optional `side`, short-circuit back if no zones
- [ ] `OcrClient::scan(..., string $side = 'front')` — per-side payload + mapFields

## Phase 6 — Tests & verification
- [ ] `IdTemplateManagementTest` — back_* persist; missing `back_zones` 422s
- [ ] IdScan Http::fake test — side routing + merge
- [x] Seeder/factory: default template gets a back side
- [ ] `migrate:fresh --seed` (DB_HOST=127.0.0.1) + re-seed real dev DB
- [x] `php artisan test --filter='IdTemplate|IdScan'` green; `npm run build` clean
- [ ] Manual click-path (browser + WebGL + camera) — deferred, see plan

### Deviations from plan
- (none yet)
