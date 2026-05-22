<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum Recipients Per Operation
    |--------------------------------------------------------------------------
    |
    | The maximum number of recipients allowed in a single bulk email operation.
    | Requests exceeding this limit will be rejected with a validation error.
    |
    */

    'max_recipients_per_operation' => env('EMAIL_TRACKING_MAX_RECIPIENTS', 2000),

    /*
    |--------------------------------------------------------------------------
    | Chunk Size
    |--------------------------------------------------------------------------
    |
    | The number of recipient records to load and process at a time during
    | bulk operations, preventing memory exhaustion on large sends.
    |
    */

    'chunk_size' => env('EMAIL_TRACKING_CHUNK_SIZE', 100),

    /*
    |--------------------------------------------------------------------------
    | Delay Between Emails (Seconds)
    |--------------------------------------------------------------------------
    |
    | The delay in seconds between dispatching individual email jobs to stay
    | within SMTP provider rate limits (e.g., Hostinger).
    |
    */

    'delay_between_emails_seconds' => env('EMAIL_TRACKING_DELAY_SECONDS', 30),

    /*
    |--------------------------------------------------------------------------
    | Maximum Retry Count
    |--------------------------------------------------------------------------
    |
    | The maximum number of times a failed email can be retried before the
    | system rejects further retry attempts for that record.
    |
    */

    'max_retry_count' => env('EMAIL_TRACKING_MAX_RETRIES', 3),

    /*
    |--------------------------------------------------------------------------
    | Progress Poll Interval (Milliseconds)
    |--------------------------------------------------------------------------
    |
    | The interval in milliseconds at which the frontend progress bar polls
    | the server for updated bulk operation status.
    |
    */

    'progress_poll_interval_ms' => 3000,

];
