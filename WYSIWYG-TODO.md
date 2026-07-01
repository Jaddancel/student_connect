# WYSIWYG Form Authoring — execution tracker

Plan: 3-step creation wizard, separate printed-PDF template editor, admin preview, FAB, name+purpose search, DOCX import/export.

## Part A — Data model
- [x] Migration: add `pdf_template` JSON nullable to `forms`
- [x] `Form::$fillable` + cast `pdf_template` to array

## Part B — Printed-PDF template editor (rich text + tokens)
- [x] `app/Forms/PdfTemplateRenderer.php`
- [x] `app/Forms/TemplateHtmlSanitizer.php` (shared server-side sanitize)
- [x] `resources/views/documents/form-template-pdf.blade.php`
- [x] `resources/js/components/pdf-template-editor.js` (registered in app.js)
- [x] Step 2 blade partial `components/form-builder/pdf-template.blade.php`

## Part C — PDF generation switch
- [x] `DocumentGenerationService::generateFromSubmission()` renders the template

## Part D — 3-step wizard
- [x] Extended `form-builder.js` with `step` state + `pdf_template` + `description_text`
- [x] `editor.blade.php` is now the wizard shell (Step1/Step2/Step3 + footer nav)
- [x] `components/common/wizard-steps.blade.php`
- [x] Controller store/update validation accepts `pdf_template` + `description_text`

## Part E — Require template to publish / submit
- [x] Controller guard: block publish when template empty (422 + field error)
- [x] `FormRenderController::submit` guard (defense in depth)

## Part F — Admin preview
- [x] Route `GET /admin/form-builder/{form}/preview` + `preview()` (by id, ignores gates)
- [x] `render.blade.php` preview mode flag (banner, submit disabled)
- [x] Preview printed document (`?document=1`, sample values, nothing persisted)
- [x] Repointed index Preview button to admin preview route (drafts included)

## Part G — FAB
- [x] `components/common/fab.blade.php`
- [x] index.blade FAB replaces "+ New Form"

## Part H — Search (name + purpose)
- [x] `DashboardSearchHelper` indexes purpose (`description_text`)
- [x] Forms directory: `FormDirectoryController`, `/forms` route, `directory.blade.php`, sidebar entry

## Part I — DOCX import/export
- [x] `app/Services/DocxTemplateService.php` (LibreOffice primary, PhpWord fallback)
- [x] Controller `export-docx` / `import-docx` routes (in-wizard state)
- [x] Step 2 toolbar Import/Export buttons

## Verification
- [x] `php artisan migrate` (pdf_template column added)
- [x] `npm run build` (pdf-template-editor bundled)
- [x] Updated `FormBuilderTest` for `pdf_template` + publish guard (8 pass)
- [x] Added `PdfTemplateRendererTest` (token replace / deleted-field / sanitize — 3 pass)
- [x] Fixed latent undefined-array-key bugs (page size/orientation, header align) exposed under strict test env

## Follow-up changes (post-plan, per user requests)
- [x] Moved the header/letterhead designer out of Step 1 into the Step 2 PDF template
      editor, and added a **footer image**. Both stored under `pdf_template.header` /
      `pdf_template.footer`; rendered as running header/footer on every PDF page
      (`documents/form-template-pdf.blade.php`). Legacy `layout.header` auto-migrates
      into the template header when an old form is re-opened.
- [x] Page editor no longer defaults to Times New Roman — `.pdf-template-surface`
      now uses a DejaVu Sans stack (matches the PDF output).
- [x] Added **font family** and **font size** toolbar controls in the template editor;
      inline `font-size`/`font-family` styles are whitelisted client- and server-side
      (`TemplateHtmlSanitizer`) and render in dompdf.

## Notes
- Pre-existing test-env quirk: `.env` sets `APP_ENV=local`, so phpunit's `APP_ENV=testing`
  does not override the process var → CSRF enforced. Run tests with
  `APP_ENV=testing DB_HOST=127.0.0.1 php artisan test`.
- ~14 failing tests in DocumentFormWorkflow / NewOfficerCreation / OrganizationScoped
  suites are PRE-EXISTING (fail identically on the original commit) and unrelated to
  this work — they depend on seeded bespoke-form data absent in the test DB.
- DOCX round-trip fidelity depends on LibreOffice (`soffice`); PhpWord is the fallback.
