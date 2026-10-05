<?php

/*
| Report Templates (app/Reports): what the schema-introspected data catalog
| exposes to admin-authored report tokens, and how hard the engine may query.
*/

return [
    // Tables never offered to report tokens (framework internals, secrets).
    'deny_tables' => [
        'migrations',
        'cache',
        'cache_locks',
        'sessions',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
        'invitation_tokens',
        'personal_access_tokens',
        'app_settings',
        'signature_references',
        'report_templates',
        'after_event_report_notifications',
    ],

    // Column names (case-insensitive regexes) never offered, on any table.
    'deny_column_patterns' => [
        '/password/i',
        '/token/i',
        '/secret/i',
        '/remember/i',
        '/google_id/i',
        '/api_?key/i',
        '/_hash$/i',
        '/otp/i',
        '/two_factor/i',
    ],

    // Relations for FK-less columns: [table, column, foreign_table, foreign_column].
    // Entries whose tables/columns don't exist are ignored.
    'relations' => [
        ['organization_advisers', 'organization_id', 'organizations', 'organization_id'],
        ['accomplishment_media', 'organization_id', 'organizations', 'organization_id'],
        ['accomplishment_media', 'form_submission_id', 'form_submissions', 'form_submission_id'],
        ['documents', 'author', 'users', 'user_id'],
        ['posts', 'organization_id', 'organizations', 'organization_id'],
        ['posts', 'author', 'users', 'user_id'],
        ['action_logs', 'user_id', 'users', 'user_id'],
        ['signature_references', 'user_id', 'users', 'user_id'],
    ],

    // Columns whose stored code prints as a label: "table.column" => [class, method].
    'enums' => [
        'organizations.organization_type' => [\App\Enums\OrganizationType::class, 'label'],
    ],

    // Engine guards.
    'max_rows' => (int) env('REPORTS_MAX_ROWS', 5000),
    'preview_rows' => 10,
    'max_depth' => 3,
];
