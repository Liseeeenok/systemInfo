<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Screenshot capture
    |--------------------------------------------------------------------------
    | The X11 screenshot is written by a small systemd service running as the
    | desktop user (see deploy/systeminfo-screen.service) and only read here.
    */
    'screen_path' => env('SYSTEMINFO_SCREEN_PATH', '/run/systeminfo/screen.jpg'),
    'screen_interval' => (int) env('SYSTEMINFO_SCREEN_INTERVAL', 4),

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    */
    'metrics_interval' => (int) env('SYSTEMINFO_METRICS_INTERVAL', 5),
    'prune_days' => (int) env('SYSTEMINFO_PRUNE_DAYS', 14),
];
