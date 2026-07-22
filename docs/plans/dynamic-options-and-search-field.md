# Plan — Dynamic Options + Search-Bar Field (Form Builder)

**Status:** Design / handoff. No implementation started.
**Author:** Claude (research pass on `origin/backend_api`).
**Date:** 2026-07-22

---

## 1. Request being addressed

> RE: Pre-defined or Dynamic Options (Form Builder)
>
> 1. A new field type is now available for the form builder: a **search-bar type**
>    where the user could choose a result to submit.
> 2. Allow the user to choose a type of **registered entries in the db** (approved
>    events from the user's org, organizations, officers, etc.) to populate an
>    input and the search-bar input.
> 3. If a field has options defined (or pre-defined) in the form builder, all of
>    those options should be **listed and selectable** in the **tally condition
>    editor**, instead of being typed manually.

---

## 2. Critical context: where the code lives

The form builder, tally condition editor, and field-type system do **not** exist on
`main` or on the assigned working branch
(`claude/dynamic-options-registered-entries-a44iuq`). On those branches the
`forms` / `form_descriptions` / `templates` migrations are empty stubs
(`id` + `timestamps` only) and the models/controllers are `//` placeholders.

**All of the relevant code lives on `origin/backend_api`** (the most recently active
branch). Any implementation must be based on `backend_api`, not `main`.

> **OPEN DECISION (blocker):** which base does implementation build on?
> - (Recommended) Recreate the working branch from `origin/backend_api`.
> - Wait for `backend_api` to merge into `main`, then build on `main`.
> - Commit directly onto `backend_api`.
>
> Nothing can be built until this is resolved — there is nothing to build against
> on the current base.

---

## 3. Architecture on `backend_api` (as-found)

| Concern | File |
|---|---|
| Field-type catalog (single source of truth) | `so-connect/app/Forms/FieldType.php` |
| Which special types a form may use (per-form "kit") | `so-connect/app/Forms/FieldKit.php` |
| Server-resolved DB lists for special selects | `so-connect/app/Forms/SpecialFieldData.php` |
| Per-field visibility conditions | `so-connect/app/Forms/ConditionEvaluator.php` |
| Field rendering (dispatcher, all types) | `so-connect/resources/views/components/form/fields/field.blade.php` |
| Builder save + `field_options` whitelist | `so-connect/app/Http/Controllers/Admin/FormBuilderController.php` |
| Builder UI (Alpine) | `so-connect/resources/js/components/form-builder.js` |
| **Tally condition editor (UI)** | `so-connect/resources/js/components/tally-editor.js` |
| **Tally condition editor (view)** | `so-connect/resources/views/pages/admin/scoring/rules/editor.blade.php` |
| Variables/options feed to tally editor | `so-connect/app/Http/Controllers/Admin/ScoringRuleController.php` (`variablesPayload()`) |
| Tally evaluation engine | `so-connect/app/Services/Scoring/ScoringRuleEngine.php` |
| Tally AST whitelist validator | `so-connect/app/Services/Scoring/TriggerValidator.php` |
| Field model + schema | `so-connect/app/Models/Form/FormDescription.php` (`field_options` is a JSON bag) |

### Key facts discovered

**A. DB-backed dropdowns already exist — but hardcoded, one type per entity.**
`FieldType` already defines special (kit-scoped) types that pull from the DB:
- `org-select` — organizations (scoped to the officer's orgs)
- `event-select` — the org's **approved event plans**
- `workplan-select` — finalized workplans
- `workplan-events` — approved plans of the current workplan

Each one has its own: validation branch in `FieldType::validationRules()`, its own
render `@case` in `field.blade.php`, and its own resolver method in
`SpecialFieldData`. They are unlocked per form via `FieldKit`.
**Feature 2 is a generalization of this pattern** into an admin-choosable "source"
rather than a new hardcoded type for every entity.

**B. `field_options` is a JSON bag, but the builder whitelists keys.**
`FormBuilderController::validatePayload()` explicitly enumerates every allowed
`field_options.*` key (see the block around lines 250–291). Its own comment warns:
unlisted keys are **silently dropped** on save. Any new option key (`source`, search
config) **must** be added to this whitelist or it will vanish.

**C. Feature 3 is ~80% already implemented for static options.**
- `tally-editor.js` `optionsFor(row)` returns a field's `options` and the editor view
  renders a `<select>` of them, falling back to a free-text `<input>` only when a field
  has no options (`editor.blade.php`, the `optionsFor(child)` / `!optionsFor(child)`
  branches).
- `ScoringRuleController::variablesPayload()` already sends `options` for optioned
  fields: `FieldType::isOptioned($f) ? FieldType::optionPairs(...) : []`.

  So **static pre-defined options are already pickable** in the tally editor today.
  The remaining gap: the **new dynamic/search fields carry no static `options`**, so
  they fall back to free-text unless we also feed their DB entries into the payload.

**D. The tally engine compares stored values.**
`ScoringRuleEngine` compares the submitted/stored value (e.g. ids for selects). If a
dynamic source exposes its option `value` as the **stored id**, comparisons work with
no engine change.

---

## 4. Proposed design

### Sequencing
1. **Feature 2 first** — the `OptionSource` registry is the foundation the other two build on.
2. **Feature 1** — the search field is a sourced select with a typeahead UI.
3. **Feature 3** — feed sourced entries into the tally editor.
4. Tests.

### Feature 2 — Dynamic option sources (foundation)

Add a **code-defined registry** mirroring the existing `FieldKit` / `SystemFunction`
style (a static `catalog()` method).

- **New file `so-connect/app/Forms/OptionSource.php`** — maps a source key to metadata:
  `{ label, model/query, value column, label column, searchable, scope }`.
  Example source keys: `organizations`, `approved_events` (current org's approved event
  plans), `officers`, `members`, `finalized_workplans`.
- Each source declares a **scope**:
  - a **submitter-facing** query that respects authorization
    (`OrganizationAuthorizationService` — e.g. an officer only sees *their* org's
    approved events), used on the live form; **and**
  - an **admin/unscoped** variant used by the tally editor (which scores across all
    orgs and must not be limited to the acting admin's org).
- **New `field_options.source`** key on a `select` or the new `search` field. When set,
  options come from the source; when null, behavior is unchanged (static `options`).

Wiring / files to touch:
- `FieldType::validationRules()` — when `source` is set, validate the submitted value
  with `exists:` / `in:` against the resolved source set (defends against spoofed
  values outside the list).
- `SpecialFieldData::resolve()` **or** a new small `OptionSourceData` resolver — resolve
  each sourced field's list into the render context, scoped to the submitter.
- `field.blade.php` — the `select` `@case` renders sourced options exactly as it renders
  static ones.
- `FormBuilderController::validatePayload()` — **whitelist** `field_options.source`
  (and any search config keys). *(See finding B — do not skip this.)*
- `form-builder.js` — config panel gains an **"Options: Pre-defined / From registered
  entries"** toggle; when "registered entries," show a source dropdown fed by
  `OptionSource::catalog()`.

**Security note:** sources MUST be scoped on the live form. A submitter must never be
offered — or able to submit — entries they shouldn't see (e.g. another org's events).
The `exists:`/`in:` validation must run against the *scoped* set, not the full table.

### Feature 1 — "Search bar" field type

- New `FieldType::SEARCH` constant + `catalog()` entry. Conceptually a **sourced select
  with a typeahead UI** — better than a giant `<select>` for large sources (all members,
  all officers).
- **New Alpine component** `so-connect/resources/js/components/search-select-field.js`
  plus a render `@case` in `field.blade.php`: a combobox that filters the source list and
  submits the chosen entry's id via a hidden input while showing its label.
- Reuses the same `OptionSource` registry (Feature 2) for its source.
- Validation: same `exists:`/`in:` against the scoped source as Feature 2.

> **OPEN DECISION (shapes F1):** how does the search field load entries?
> - (Recommended for a real "search bar") a lightweight **authenticated AJAX endpoint**
>   `GET /forms/options/{source}?q=`, scoped + authorized per request — good for large lists.
> - **Inline-injected list** (resolve full scoped list server-side, filter client-side,
>   like `event-select` does today) — simpler, but poor for large sources.
> - **Both / size-based** — inline for small scoped sources, AJAX for large ones, chosen
>   per source in the registry. Most work.

### Feature 3 — Options in the tally condition editor

- Extend `ScoringRuleController::variablesPayload()` so fields with a dynamic `source`
  resolve their entries into the same `options` array the editor already consumes —
  using each source's **admin/unscoped** variant (see finding C + Feature 2 scoping).
- For `search` / large sources, swap the editor's plain `<select>` value-picker for the
  same searchable combobox instead of emitting thousands of `<option>`s
  (`tally-editor.js` + `editor.blade.php`).
- **No `ScoringRuleEngine` change needed** if option `value` == stored id (finding D).

---

## 5. Data model summary

`form_descriptions.field_options` (JSON) gains:

```jsonc
{
  // existing keys: options[], min, max, step, accept, columns[], visible_when{}, ...
  "source": "approved_events"   // NEW: key into App\Forms\OptionSource::catalog()
  // (optional future search config keys, e.g. min query length — whitelist if added)
}
```

- `field_type`: gains `search` (new).
- No schema migration strictly required (uses the existing JSON `field_options`), unless
  we choose to index/denormalize a source column later.

---

## 6. File-by-file change checklist (implementation phase)

Feature 2:
- [ ] `app/Forms/OptionSource.php` — new registry (catalog + scoped/unscoped resolvers).
- [ ] `app/Forms/FieldType.php` — `source`-aware validation in `validationRules()`.
- [ ] `app/Forms/SpecialFieldData.php` (or new resolver) — inject scoped source lists.
- [ ] `resources/views/components/form/fields/field.blade.php` — render sourced `select`.
- [ ] `app/Http/Controllers/Admin/FormBuilderController.php` — whitelist `source` key.
- [ ] `resources/js/components/form-builder.js` — pre-defined vs registered-entries toggle.

Feature 1:
- [ ] `app/Forms/FieldType.php` — add `SEARCH` constant + catalog entry + validation.
- [ ] `resources/js/components/search-select-field.js` — new combobox component.
- [ ] `resources/views/components/form/fields/field.blade.php` — `search` render `@case`.
- [ ] (if AJAX chosen) new route + controller action `GET /forms/options/{source}`.
- [ ] `app/Http/Controllers/Admin/FormBuilderController.php` — whitelist search config.

Feature 3:
- [ ] `app/Http/Controllers/Admin/ScoringRuleController.php` — resolve sourced entries
      into `variablesPayload()` options (admin/unscoped).
- [ ] `resources/js/components/tally-editor.js` — searchable value picker for large sources.
- [ ] `resources/views/pages/admin/scoring/rules/editor.blade.php` — combobox for source
      values.

Cross-cutting:
- [ ] Build assets (`npm run build`) — Vite/Tailwind/DaisyUI project.
- [ ] Tests (Pest) — see below.

---

## 7. Testing notes

- Project uses **Pest 4** (`pestphp/pest`, `pest-plugin-laravel`).
- Suggested coverage:
  - `OptionSource` scoped vs unscoped resolution (an officer sees only their org's
    approved events; admin/unscoped sees all).
  - `FormBuilderController` persists and reloads `field_options.source` (guards against
    the silent-drop whitelist bug — finding B).
  - Submit validation rejects a value outside the scoped source; accepts one inside.
  - `variablesPayload()` includes sourced options for dynamic fields.
  - Rendering: a sourced `select` and a `search` field render their entries.

---

## 8. Open decisions (recap)

1. **Branch base** (blocker) — rebase working branch onto `backend_api` / wait for merge
   / commit onto `backend_api`. *No work possible until resolved.*
2. **Search data delivery** — AJAX endpoint (recommended) / inline / size-based.
3. **Source catalog scope** — confirm the exact initial set of registered-entry sources
   to ship (organizations, approved events, officers, members, workplans, …?).
4. Whether to eventually re-express the existing bespoke `org-select` / `event-select`
   types on top of `OptionSource` (nice-to-have; not required for these three features).
