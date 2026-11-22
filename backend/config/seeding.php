<?php

return [
    'admin_email' => env('SEED_ADMIN_EMAIL', 'admin@tms.com'),
    'admin_password' => env('SEED_ADMIN_PASSWORD'),
    'branches' => [
        ['code' => 'TMS-01', 'name' => 'TMS Rusape Main Branch', 'slug' => 'tms-rusape-main'],
        ['code' => 'TMS-02', 'name' => 'TMS Mutare Main Branch', 'slug' => 'tms-mutare-main'],
        ['code' => 'TMS-03', 'name' => 'TMS Gweru Main Branch', 'slug' => 'tms-gweru-main'],
    ],
    'branch_admins' => [
        'tms-rusape-main' => ['email' => env('SEED_RUSAPE_ADMIN_EMAIL', 'admin@rusape.tms.local'), 'password' => env('SEED_RUSAPE_ADMIN_PASSWORD', 'Rusape2026!')],
        'tms-mutare-main' => ['email' => env('SEED_MUTARE_ADMIN_EMAIL', 'admin@mutare.tms.local'), 'password' => env('SEED_MUTARE_ADMIN_PASSWORD', 'Mutare2026!')],
        'tms-gweru-main' => ['email' => env('SEED_GWERU_ADMIN_EMAIL', 'admin@gweru.tms.local'), 'password' => env('SEED_GWERU_ADMIN_PASSWORD', 'Gweru2026!')],
    ],
];
