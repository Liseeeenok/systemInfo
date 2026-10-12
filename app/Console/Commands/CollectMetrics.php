<?php

namespace App\Console\Commands;

use App\Models\MetricSample;
use App\Services\MetricsCollector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CollectMetrics extends Command
{
    protected $signature = 'systeminfo:collect
                            {--loop : keep sampling until the process is stopped}
                            {--interval= : seconds between samples (default: config)}';

    protected $description = 'Collect a server metrics sample and store it (memory, cpu, disks, network, temps, processes)';

    public function handle(): int
    {
        $interval = (int) ($this->option('interval') ?: config('systeminfo.metrics_interval', 5));
        $interval = max(1, $interval);

        if (! $this->option('loop')) {
            $this->takeSample();

            return self::SUCCESS;
        }

        $this->info("Collecting metrics every {$interval}s (Ctrl-C / systemctl stop to finish)");
        $stats = ['ok' => 0, 'failed' => 0, 'lastReport' => time()];

        while (true) {
            $startedAt = microtime(true);

            try {
                $this->takeSample();
                $stats['ok']++;
            } catch (Throwable $e) {
                $stats['failed']++;
                $this->error('sample failed: '.$e->getMessage());
                report($e);
            }

            if (time() - $stats['lastReport'] >= 3600) {
                $this->line(sprintf('[%s] samples ok: %d, failed: %d', now()->toDateTimeString(), $stats['ok'], $stats['failed']));
                $stats['lastReport'] = time();
            }

            $elapsed = microtime(true) - $startedAt;
            $sleep = $interval - $elapsed;
            if ($sleep > 0) {
                usleep((int) round($sleep * 1_000_000));
            }
        }
    }

    private function takeSample(): void
    {
        // The long running loop must not keep a dead connection after a DB restart.
        DB::reconnect();

        $data = MetricsCollector::make()->sample();

        MetricSample::query()->create($data);
    }
}
