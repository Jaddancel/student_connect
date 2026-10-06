## Plan: Restrict Form Management to Type 1

Enforce superadmin-only access for form template management and upload forms, using the existing superadmin middleware and controller checks, while keeping form requests and generated documents flows unchanged for officers/presidents.

**Steps**

1. Update routes to require both auth and superadmin for /forms/manage and /upload-forms (GET+POST). Keep other form request routes unchanged.
2. Tighten controller authorization:
    - In FormWorkflowController::manageForms and ::storeTemplate, require user_type === 1 and return 403 if not.
    - In SidebarMenuController::uploadForms and ::storeUploadedForm, require user_type === 1 and return 403 if not.
3. Update MenuHelper to hide Manage Document Forms and Upload Documents for non-type-1 users, while leaving My Documents and form request entries intact.
4. Verify that superadmins can manage/upload forms and non-type-1 users receive 403 and no menu entries.

**Relevant files**

- `/home/jaddancel/projects/student_connect/so-connect/routes/web.php` — add superadmin middleware to routes.
- `/home/jaddancel/projects/student_connect/so-connect/app/Http/Controllers/FormWorkflowController.php` — enforce type-1 check in manage and store.
- `/home/jaddancel/projects/student_connect/so-connect/app/Http/Controllers/SidebarMenuController.php` — enforce type-1 check in upload endpoints.
- `/home/jaddancel/projects/student_connect/so-connect/app/Helpers/MenuHelper.php` — hide manage/upload menu items for non-type-1 users.
- `/home/jaddancel/projects/student_connect/so-connect/app/Http/Middleware/EnsureSuperAdmin.php` — confirm message and reuse.

**Verification**

1. Log in as type-1 user, open /forms/manage and /upload-forms, confirm access and functionality.
2. Log in as non-type-1 user, confirm 403 for /forms/manage and /upload-forms.
3. Confirm menu does not show Manage Document Forms or Upload Documents for non-type-1 users, but shows them for type-1.

**Decisions**

- Restrict /forms/manage and /upload-forms to user_type 1.
- Return 403 for unauthorized access.
- Hide manage/upload menu entries for non-type-1 users.
- Do not change form request/submit or generated document access.
