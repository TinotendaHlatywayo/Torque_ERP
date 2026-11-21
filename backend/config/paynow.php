<?php

return [
    'enabled' => (bool) env('PAYNOW_ENABLED', false),
    'integration_id' => env('PAYNOW_INTEGRATION_ID'),
    'integration_key' => env('PAYNOW_INTEGRATION_KEY'),
    'currency' => strtoupper(env('PAYNOW_CURRENCY', 'USD')),
    'initiate_url' => env('PAYNOW_INITIATE_URL', 'https://www.paynow.co.zw/interface/initiatetransaction'),
    'connect_timeout_seconds' => min(10, max(1, (int) env('PAYNOW_CONNECT_TIMEOUT_SECONDS', 3))),
    'timeout_seconds' => min(30, max(1, (int) env('PAYNOW_TIMEOUT_SECONDS', 8))),
    'poll_retries' => min(2, max(0, (int) env('PAYNOW_POLL_RETRIES', 2))),
    'max_polls_per_callback' => 1,
];
