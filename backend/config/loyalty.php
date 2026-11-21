<?php

return [
    'inactivity_days' => (int) env('LOYALTY_INACTIVITY_DAYS', 90),
    'min_qualifying_minor' => (int) env('LOYALTY_MIN_QUALIFYING_MINOR', 10000),
    'min_qualifying_sales' => (int) env('LOYALTY_MIN_QUALIFYING_SALES', 1),
    'loyalty_discount_percent' => (float) env('LOYALTY_DISCOUNT_PERCENT', 5),
    'combined_approval_threshold_percent' => (float) env('LOYALTY_APPROVAL_THRESHOLD_PERCENT', 10),
    'marketing_channels' => ['sms', 'email', 'whatsapp'],
];
