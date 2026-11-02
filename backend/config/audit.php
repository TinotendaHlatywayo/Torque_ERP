<?php

return [
    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 365),
    'immutable' => true,
];
