<?php

declare(strict_types=1);

return [
    'admin_routes' => [
        'enabled' => true,
        'prefix' => 'admin/scheduler',
        'middleware' => ['web', 'larena-auth.entry', 'larena-auth.admin-required'],
    ],
    'tick' => [
        'max_schedules' => 100,
        'missed_grace_seconds' => 60,
    ],
];
