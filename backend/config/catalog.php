<?php

return [
    'public_branch_slug' => env('PUBLIC_CATALOGUE_BRANCH_SLUG', 'tms-rusape-main'),
    'reservation_expiration_minutes' => (int) env('RESERVATION_EXPIRATION_MINUTES', 15),
];
