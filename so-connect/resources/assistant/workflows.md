# Student Connect — Workflow Reference

Hand-authored notes for multi-step flows the page index alone can't explain.
Only link pages that also appear in the page list you were given — if a step
below mentions a page not in that list, the current user cannot reach it;
describe the step without linking it.

## Creating and publishing a form (Admin only)

1. Admin sidebar → **Form Builder** → *New Form*. Build the canvas: drag
   fields from the left palette into rows/columns, set each field's label and
   type, and optionally map it to a **universal field** so scans or profile
   data can prefill it. A **Table** field takes admin-defined columns; the
   person filling the form adds as many rows as they need.
2. Field keys generate from the label automatically and freeze once the form
   is first saved — renaming the label later does not change the key.
3. Optionally bind the form to a **system function** (Sign Up, New Event, New
   Workplan, Org Membership Registration, Organization Accreditation) so
   submissions feed that built-in workflow instead of just generating a
   document.
4. A form **publishes automatically on save** once it has a printed PDF
   template attached — there is no separate Active/Published toggle. Without
   a template it stays saved but unlisted until one is added.
5. Published forms appear to officers/presidents under **Organization
   Forms** (or as the bound system function's entry point). Admins author and
   review forms; they don't fill them out themselves.

### Officer signatures in printed templates

Step 2 of both Form Builder and Report Template Builder offers **Organization
President Signature**, **Organization Treasurer Signature**, **Organization
Auditor Signature**, and **Organization Secretary Signature** under
**Organization**. These universal tokens insert the current position holder's
saved profile signature as an image; they do not require a signature field in
Step 1. Positions marked **Others** are excluded.

Forms resolve signatures from the submission's organization; reports use the
organization selected for generation. The latest officer/president assignment
for each position is used. If that officer has no saved signature (or the
image file is missing), the token prints blank.

## Request approve/decline lifecycle

Submitting a form creates a **request**, not an instant document. The
submitter is redirected back with "submitted for approval" and can track it
on the form's page under "Your recent submissions" (Pending / Approved /
Rejected).

- Admin sidebar → **Requests** lists Activity Requests, Workplan Submissions,
  and Promotion Requests, then one entry per form page — each with a
  pending-count badge.
- Opening a pending request shows the submitted answers. **Approve** generates
  the document (the requester finds it under Documents) or performs the bound
  system function's effect (creates the event/workplan/account/membership).
  **Reject** (optionally with a reason) generates nothing and lets the
  requester resubmit.
- New Event and New Workplan submissions are reviewed from their own
  dedicated Activity Requests / Workplan Submissions pages, not a generic
  per-form queue.

## Scoring rule triggers (Admin only)

Admin sidebar → **Organization Scoring** shows every organization's computed
score across six categories; **Rankings** is the leaderboard. **Scoring
Rules** is where scoring itself is configured:

1. Each of the built-in criteria can have a custom **trigger**, authored in a
   drag-and-drop block editor: "when `<a form>` submission is approved, if
   `<condition on its fields>`, then add `<N>` instance(s)" to that criterion.
2. A criterion with an enabled trigger uses the rule's own tally instead of
   its built-in behavior; disabling the trigger reverts to the built-in count.
3. A trigger on the **After Event Report** form counts every filed report (it
   has no approval step) and can also test the fields of the New Event
   submission that created the reported event — they appear in the variable
   picker under "New Event (linked event)".
4. List fields (text lists, tables, photo sets) are marked "(rows)" in the
   variable picker: comparing one (e.g. "Faculty Advisers ≥ 2") compares its
   number of rows, and "one instance per row of" adds one per row.
5. Admins can also add brand-new **custom criteria** alongside the built-in
   ones.

## Waiver review

Waivers are scanned photos of a signed physical consent form, validated
against an event's expected participant/date details.

- Admin/SuperAdmin sidebar → **Waiver Templates**: define zones (text,
  signature, stamp) on a reference photo of the physical waiver, the same way
  ID templates are authored below.
- A participant scans their signed waiver on the relevant form; the scanner
  extracts the zone text and checks name/date/signature/stamp against what
  was expected — advisory only, it never blocks submission.
- Submitted waiver scans, with their verdict (valid / needs review /
  unvalidated), are reviewed from the activity request review view, where a
  human makes the final call.

## ID template zones (SuperAdmin only)

SuperAdmin sidebar → **ID Templates** defines how the ID scanner reads a
student ID photo:

1. Upload a reference photo of the ID (front, and optionally back), then drag
   rectangular **zones** over it — one per piece of information to extract
   (name, student ID number, birthday, signature, …).
2. Each zone has a **Type**: *Text* (OCR'd, optionally cleaned up with a
   regex) or *Signature* (the crop is captured as an image, not OCR'd).
3. Exactly one template is marked **default** — the one new signups are
   scanned against. Saving requires at least one zone with an image per side.
4. This is unrelated to **Waiver Templates** above — ID templates read
   government/school IDs for signup prefill; waiver templates read signed
   consent forms.
