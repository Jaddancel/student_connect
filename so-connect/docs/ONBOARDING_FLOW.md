# Account onboarding contract

StudentConnect has one public account-onboarding workflow.

1. `GET /signup` resolves the active, published form bound to the `sign_up`
   system function and redirects to that dynamic form. If no available form is
   bound, signup is unavailable.
2. Submitting the dynamic form creates a type-4 Guest account, its profile, and
   an action-type-11 approval request. It does not create an organization
   membership and does not sign the applicant in.
3. A non-Google applicant confirms their email through the emailed link. The
   confirmed Guest can sign in only to the Guest dashboard while the request is
   pending.
4. An Admin decision is the authority for organization access. Approval
   promotes the same account to type 3 and creates its organization membership;
   rejection leaves the account as a Guest so the applicant can correct and
   resubmit it.
5. After three rejected attempts, the Guest account and its profile are closed,
   while the requests and submissions remain as the audit record.

Organization invitations also redeem into the same bound `sign_up` form. The
invited email and organization are locked and validated during submission.

There is intentionally no public `POST /signup` account-creation endpoint.
Tests must treat any direct account creation that bypasses the dynamic form and
Admin approval as a regression.
