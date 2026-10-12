<?php

namespace App\Http\Controllers;

use App\Models\MetricSample;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'screenInterval' => config('systeminfo.screen_interval', 4),
            'metricsInterval' => config('systeminfo.metrics_interval', 5),
            'latestSampleAt' => MetricSample::query()->max('sampled_at'),
        ]);
    }
}
