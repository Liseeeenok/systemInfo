<?php

namespace App\Console\Commands;

use App\Models\MetricSample;
use Illuminate\Console\Command;

class PruneMetrics extends Command
{
    protected $signature = 'systeminfo:prune {--days= : retention window in days (default: config)}';

    protected $description = 'Delete metric samples older than the retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('systeminfo.prune_days', 14));
        $cutoff = now()->subDays(max(1, $days));

        $deleted = MetricSample::query()->where('sampled_at', '<', $cutoff)->delete();

        $this->info("Deleted {$deleted} samples older than {$cutoff->toDateTimeString()} ({$days} days)");

        return self::SUCCESS;
    }
}
