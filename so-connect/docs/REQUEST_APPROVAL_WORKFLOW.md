# Request and approval contract

This document is the source of truth for request review in StudentConnect.

## Reviewer inboxes

- **Admin — Requests & Approvals Center:** project requests, joint statements,
  accomplishment reports, financial reports, recognition requests, and
  workplans. The existing detail pages remain the authoritative review view,
  but they all submit decisions through one guarded decision service.
- **Admin — Activity Requests:** remains separate because waiver validation and
  manual waiver review must pass before approval.
- **Admin — Promotion Requests:** sign-up and organization-role changes remain
  separate because they change accounts and memberships.
- **Admin — Form Requests:** other builder forms use their form-specific queue.
- **Organization President — Membership Requests:** the president reviews only
  requests for organizations they lead.

These are separate inboxes for different reviewer scopes, not separate decision
rules.

## Approval chain

| Request | First decision | Final decision |
| --- | --- | --- |
| Organization membership | President of the receiving organization | Admin |
| Sign-up / new officer | Admin | Admin |
| Officer or president promotion | President when organization-scoped; Admin for member-initiated officer-to-president changes | Same reviewer |
| Activity / event | President when organization-scoped; Admin where the request type is Admin-scoped | Same reviewer |
| Unified document workflows | Admin | Admin |
| Other builder-form requests | Admin | Admin |

Only membership registration has a mandatory two-stage President → Admin chain.
An Admin cannot complete its final membership stage until the President stage is
approved.

## Decision rules

1. A request stage starts as **pending** and can be decided once.
2. Approval and rejection are terminal for that request stage. A later click,
   stale browser tab, bulk action, or concurrent reviewer cannot replace it.
3. Rejection always requires a reason of 3–1000 characters.
4. Rejected data remains immutable as the audit record. Correction means a new
   submission/request; it does not reopen or rewrite the rejected request.
5. Decision code locks the request row inside a database transaction before it
   checks pending state and performs side effects. This serializes concurrent
   reviewers and prevents duplicate memberships, events, approvals, and
   generated documents.
6. A successful domain side effect and its approval record are committed
   together. If required document generation fails, the request stays pending.
7. The `approvals` row (and `stage` for multi-stage membership) is the
   authoritative decision state. Email and in-app notifications report that
   state; they do not define it.

Joint statements are the documented exception to immediate document generation:
approval may complete with a warning when its printable template is not ready,
because the detail page provides a separate generation action.
