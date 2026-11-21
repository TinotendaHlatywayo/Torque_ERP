<?php

return [
    'log_channel' => env('OBSERVABILITY_LOG_CHANNEL', 'observability'),

    'failed_job_channel' => env('FAILED_JOB_LOG_CHANNEL', 'observability'),

    'correlation_header' => 'X-Correlation-Id',

    'request_header' => 'X-Request-Id',

    'redacted_keys' => [
        'password', 'password_confirmation', 'secret', 'api_key', 'apikey', 'integration_key',
        'token', 'access_token', 'refresh_token', 'authorization', 'cookie', 'set_cookie',
        'card_number', 'cardnumber', 'cvv', 'iban', 'bank_account', 'bank_details',
        'national_id', 'date_of_birth', 'address', 'phone', 'email', 'customer_email',
    ],
];
