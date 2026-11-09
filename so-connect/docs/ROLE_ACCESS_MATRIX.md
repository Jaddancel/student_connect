# Role and access contract

This document is the source of truth for account-level and organization-level
authorization. Code should use the named methods on `App\Models\User` and the
organization authorization service instead of comparing raw `user_type` values.

## Account roles

| Account role | Stored value | Access contract |
| --- | ---: | --- |
| Super Admin | `1` | Inherits every Admin capability and adds system-only controls such as account administration, backup/restore, data export, ID templates, action logs, and destructive organization recovery actions. |
| Admin | `2` | Operates the system: content and form authoring, request decisions, scoring, reports, semester configuration, announcements, and support administration. Cannot use Super Admin-only controls. |
| Organization User | `3` | Receives permissions from the user's role in the selected organization. Does not receive system administration access. |
| Guest Applicant | `4` | Can view only the account-request lifecycle and account settings allowed to all authenticated users. |

Use these `User` methods:

- `isSuperAdmin()` for Super Admin-only controls.
- `isAdmin()` only when behavior must intentionally exclude Super Admin.
- `isAdministrative()` for normal Admin operations; this includes Super Admin.
- `isOrganizationUser()` for accounts whose permissions come from organization membership.
- `isGuest()` for pending applicants.

The `admin` middleware means operational administrative access and therefore
allows both Admin and Super Admin. The `superadmin` middleware remains exclusive.

## Organization roles

Organization roles are stored in `organization_officers.role` and always apply
within an authorized organization. The active-organization switcher narrows UI
operations to one organization; background and cross-user business logic may use
the explicitly named `all*OrganizationIdsForUser` methods.

| Organization role | Capabilities |
| --- | --- |
| President | All Officer capabilities plus president-only membership review and other explicitly president-gated actions. |
| Officer | Submit and manage organization forms, events, workplans, reports, and organization records exposed to officers. |
| Member | General organization membership visibility only. No officer submission or approval capability. |

System administrators do not acquire an organization role. When an operational
page is intended for both system administrators and organization officials, use
`officer.or.admin` or `president.or.admin`. When the page is specifically an
organization-user experience, keep the organization-role middleware exclusive.

Dashboard authorization uses `sysadmin`/`admin` for system administrators and
`officer`/`president` for organization roles. Do not use `admin` as a synonym
for an organization officer.

## Authorization rules

1. Enforce access on the server through middleware, policies, gates, or scoped
   service queries. Hiding a menu item is not authorization.
2. Navigation and dashboard search must mirror server access so users do not see
   links that lead to `403`, and authorized users can discover their tools.
3. Super Admin inheritance applies to operational Admin pages and data scopes,
   not to organization-only submission experiences.
4. Any new role-sensitive feature must include tests for Super Admin, Admin,
   President, Officer or Member as applicable, Guest, and unauthenticated access.
5. Destructive or system-recovery actions remain Super Admin-only unless this
   contract is explicitly revised.
