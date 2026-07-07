# Universal Fields + ID-Scan Signup Wizard — execution tracker

Plan file: `~/.claude/plans/re-universal-fields-woolly-wirth.md`

## Phase 0 — Universal Field registry (foundation)
- [x] `app/Support/UniversalField.php` (catalog/keys/has/get/valueFor) mirroring `App\Forms\FieldType`
- [x] `UniversalFieldTest` green

## Phase 1 — Form builder: map field → universal field + profile autofill
- [x] Migration `add_universal_key_to_form_descriptions_table` + `FormDescription::$fillable`
- [x] FormBuilderController: validate + syncFields + buildEditorFields carry `universal_key`
- [x] form-builder.js new-field factory adds `universal_key`
- [x] editor.blade.php "Autofill from profile" `<select>`
- [x] FormRenderController::show builds `$prefill` from profile; field component uses it
- [x] FormBuilderTest extended (persist/reload/invalid) + render prefill test

## Phase 2 — PDF template: universal-field tokens print from profile
- [x] PdfTemplateRenderer resolves `data-universal` via UniversalField::valueFor (optional ?Profile)
- [x] DocumentGenerationService resolves submitter profile + passes it
- [x] pdf-template-editor.js "Universal fields" token group
- [x] TemplateHtmlSanitizer allows `data-universal`
- [x] DocxTemplateService bridges `data-universal` → `{{profile.universal_key}}`
- [x] PdfTemplateRenderer test (with/without profile; data-field unchanged)

## Phase 3 — OCR / ID-template editor references universal fields
- [x] IdTemplateController::validatePayload keeps free-form; universal keys recommended (unchanged — already free-form snake_case)
- [x] id-templates `_form.blade.php` datalist from UniversalField::catalog()
- [x] Confirm OcrClient returns universal-keyed `fields` (no change expected — zone `field` maps directly)

## Phase 4 — Signup 2-step ID-scan wizard
- [x] `id-scan-wizard.js` (getUserMedia video + ID finder overlay + canvas capture → /id-scan)
- [x] Upload fallback preserved; captured frame → id_photo_front via DataTransfer
- [x] student-leader-directory.blade.php restructured into step 1 / step 2 wizard
- [x] Step-2 prefill from OCR fields via universal-key → input-name map + "verify" hints (.id-autofilled)
- [x] Back/Continue nav; @error/old() lands on step 2 after failed submit (init() reads hasErrors)
- [x] app.js registers id-scan-wizard; npm build clean

## Phase 5 — Tests & verification
- [x] IdScanController Http::fake test returns universal-keyed fields
- [x] Feature tests for the whole feature green (DB_HOST=127.0.0.1) + re-seeded dev DB
      - UniversalFieldTest, FormBuilderTest (+prefill), PdfTemplateRendererTest, IdTemplateManagementTest all pass.
      - NOTE: 15 PRE-EXISTING failures on this branch are unrelated (undefined routes `forms.show`,
        `generated-documents.download` in DocumentFormWorkflowTest / NewOfficerCreationFlowTest /
        OrganizationScopedDashboardRequestsTest) — not touched by this feature.
- [ ] Manual click-path — needs a real browser + camera for Step-1 getUserMedia; automated coverage done.
      Do: builder autofill select, PDF universal token print, id-template picker datalist, /signup wizard capture→prefill.
