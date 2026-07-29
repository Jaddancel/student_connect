# Student Connect — Workflow Reference

Hand-authored notes for multi-step flows the page index alone can't explain.
Only link pages that also appear in the page list you were given — if a step
below mentions a page not in that list, the current user cannot reach it;
describe the step without linking it.

## Creating and publishing a form (Admin only)

1. Admin sidebar → **Form Builder** → *New Form*. Build the canvas: drag
   fields from the left palette into rows/columns, set each field's label and
   type, and optionally map it to a **universal field** so scans or profile
   data can prefill it.
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
3. Admins can also add brand-new **custom criteria** alongside the built-in
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
- Admin sidebar → **Waiver Review** lists submitted waiver scans with their
  verdict (valid / needs review / unvalidated) for a human to make the final
  call.

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
