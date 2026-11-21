<?php

return [
    'reorder' => [
        'sales_window_days' => 30,
        'lead_time_days' => 7,
        'days_of_cover' => 14,
    ],
    'ageing' => [
        'slow_moving_threshold_days' => 30,
        'dead_stock_threshold_days' => 90,
    ],
    'abc' => [
        'sales_window_days' => 90,
        'a_cumulative_contribution_percent' => 70,
        'b_cumulative_contribution_percent' => 90,
    ],
    'cash_forecast' => [
        'history_window_days' => 30,
        'horizon_days' => 30,
        'minimum_cash_events' => 3,
    ],
];
