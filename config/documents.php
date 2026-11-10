<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disk for uploaded documents
    |--------------------------------------------------------------------------
    |
    | "local" keeps files on the server disk (fine for development).
    | "s3" keeps them in the S3-compatible bucket (Railway Bucket), which
    | survives deploys. Production sets DOCUMENTS_DISK=s3.
    |
    */

    'disk' => env('DOCUMENTS_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Human review of scanned documents
    |--------------------------------------------------------------------------
    |
    | By default every scanned document waits for an officer to confirm its
    | category ("Needs review"). Set DOCUMENT_AUTO_FILE=true only if the
    | department accepts automatic filing above the confidence threshold.
    |
    */

    'auto_file' => (bool) env('DOCUMENT_AUTO_FILE', false),

    'auto_file_threshold' => (float) env('DOCUMENT_AUTO_FILE_THRESHOLD', 0.95),

    /*
    |--------------------------------------------------------------------------
    | "Confirm all" on the documents list
    |--------------------------------------------------------------------------
    |
    | The button confirms only documents still waiting for review whose scan
    | confidence is at or above this value (0.90 means 90%). Each one is still
    | an officer's confirmation and is written to the audit trail.
    |
    */

    'bulk_confirm_threshold' => (float) env('DOCUMENT_BULK_CONFIRM_THRESHOLD', 0.90),

];
