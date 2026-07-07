# Records pages + ID-Template editor/OCR — execution tracker

Consolidated plan: `~/.claude/plans/consolidated-records-and-id-templates.md`

## Feature 1 — Admin Records pages  (IMPLEMENTED 2026-07-03, verify + commit)
- [x] OrganizationLogoHelper + refactor LandingPage
- [x] RecordQueryService (getLoginLogs/getRequestRecords); repoint superadmin ExportController
- [x] AuditLogController + view + routes + wired exports
- [x] RequestRecordController + view + routes + wired exports
- [x] DatabaseViewController + view + routes + OrganizationsExport/OrganizationOfficersExport + print views
- [x] Remove admin Export Data (routes, adminExport* methods, admin-export.blade.php, menu item)
- [x] New "Records" sidebar group in MenuHelper
- [x] Feature tests (AuditLogTest/RequestRecordTest/DatabaseViewTest, 12 pass)
- [ ] Re-verify suite green + manual click-path, then commit

## Feature 2 — SuperAdmin ID-Template editor + OCR  (IMPLEMENTED 2026-07-07)
- [x] Migration `2026_07_07_000001_create_id_templates_table.php` + `IdTemplate` model (casts, creator, scannerTemplate, toScannerPayload)
- [x] `Admin/IdTemplateController` CRUD + uploadImage + validation + single-default transaction
- [x] Routes (superadmin.id-templates.*) + sidebar entry (user_type===1 group)
- [x] index/create/edit blades (+ shared `_form.blade.php` partial)
- [x] `id-template-editor.js` (Konva, native<->display coord conversion, round-trip check)
- [x] app.js registration + konva dep (`konva@^9.3.22`) + npm build (bundled, clean)
- [ ] Coordinate round-trip verified (save -> reopen -> overlay exact)  [MANUAL — needs browser]
- [x] `docs/ocr-template-contract.md`
- [x] `docker/ocr` FastAPI sidecar (Dockerfile, requirements.txt, app.py)
- [x] compose.yaml 'ocr' service + config/services.php ocr block
- [x] `OcrClient` + `IdScanController`
- [x] `/id-scan` route (public — directory form is guest-accessible)
- [x] Auto-scan pre-fill on id_photo_front upload (student-leader-directory form)
- [x] Pest `IdTemplateManagementTest` green (11 pass) + migration applied + dev DB re-seeded

### Still to do (out of headless reach)
- [ ] Manual browser click-path: upload ID -> draw/name zones -> save -> reopen (overlay exact) -> resize -> 2nd default flips first off; then student upload id_photo_front auto-fills Student ID.
- [ ] `docker compose build ocr && up -d ocr` + `curl /health` and a real `/scan` (first build downloads PaddleOCR models — slow).
- [ ] Commit (Feature 1 + Feature 2) — awaiting your go-ahead.

### Deviations from plan
- `/id-scan` is registered WITHOUT `auth`: the student-leader-directory form is a
  public signup page (no auth middleware), so the scan endpoint must be reachable
  by guests. Plan assumed `auth`.
