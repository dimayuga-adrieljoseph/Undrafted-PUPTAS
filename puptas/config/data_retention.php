<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Data Retention & Disposal Configuration
    |--------------------------------------------------------------------------
    |
    | Defines statutory and operational retention lifespans for all personal,
    | application, and system logs under the PUPTAS Data Privacy Architecture.
    |
    | Note: Specific periods reflect the team's operational interpretation
    | of the Data Privacy Act of 2012 (RA 10173) and NPC proportionality principles,
    | and are configurable by the institutional data controller.
    |
    // Master switch for automated scheduler execution (keeps demo/seed data safe unless explicitly enabled)
    'enabled' => env('DATA_RETENTION_AUTO_PURGE_ENABLED', false),

    'periods' => [
        // Soft-deleted or deactivated applicant PII (1 academic year)
        'soft_deleted_users_days' => env('RETENTION_SOFT_DELETED_USERS_DAYS', 365),

        // System, security, and audit logs
        'audit_logs_days' => env('RETENTION_AUDIT_LOGS_DAYS', 180),

        // Email dispatch and delivery logs
        'email_logs_days' => env('RETENTION_EMAIL_LOGS_DAYS', 90),

        // Generated credential slips (SAR, GVS, F137 PDF files)
        'generated_documents_days' => env('RETENTION_GENERATED_DOCS_DAYS', 180),

        // Orphaned or unlinked file uploads
        'orphaned_files_days' => env('RETENTION_ORPHANED_FILES_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Disks to Clean
    |--------------------------------------------------------------------------
    |
    | Physical disks inspected during automated file unlinking / purging.
    |
    */
    'disks' => [
        'public',
        'local',
    ],

];
