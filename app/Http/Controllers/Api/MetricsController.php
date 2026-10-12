<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MetricSample;
use App\Services\MetricsCollector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    /** Selectable history windows, in minutes. */
    private const RANGES = [5, 15, 60, 180, 720, 1440, 4320, 10080, 43200];

    /** Maximum number of points returned for a chart. */
    private const MAX_POINTS = 420;

    public function current(): JsonResponse
    {
        $sample = MetricSample::query()->orderByDesc('sampled_at')->first();

        if (! $sample) {
            return response()->json([
                'host' => MetricsCollector::make()->hostInfo(),
                'snapshot' => null,
                'meta' => $this->meta(),
            ]);
        }

        return response()->json([
            'host' => MetricsCollector::make()->hostInfo(),
            'snapshot' => $sample->toSnapshot(),
            'meta' => $this->meta($sample),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $minutes = (int) $request->integer('minutes', 30);
        $minutes = $this->clampRange($minutes);

        $from = now()->subMinutes($minutes);
        $bucket = max(
            (int) ceil((float) config('systeminfo.metrics_interval', 5)),
            (int) ceil($minutes * 60 / self::MAX_POINTS)
        );

        $rows = DB::table('metric_samples')
            ->where('sampled_at', '>=', $from)
            ->selectRaw(
                <<<'SQL'
                floor(extract(epoch from sampled_at) / ?) * ? as bucket,
                avg(cpu_percent) as cpu,
                avg(load1) as load1,
                avg(load5) as load5,
                avg(load15) as load15,
                avg(mem_used) as mem_used,
                avg(mem_total) as mem_total,
                avg(net_rx_rate) as rx,
                avg(net_tx_rate) as tx,
                avg(temp_cpu) as temp_cpu,
                avg(temp_gpu) as temp_gpu,
                avg(disk_used) as disk_used,
                avg(disk_total) as disk_total,
                count(*) as samples
                SQL,
                [$bucket, $bucket]
            )
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        $points = $rows->map(fn ($row) => [
            'ts' => ((int) $row->bucket) * 1000,
            'cpu' => round((float) $row->cpu, 1),
            'load1' => round((float) $row->load1, 2),
            'load5' => round((float) $row->load5, 2),
            'load15' => round((float) $row->load15, 2),
            'mem_used' => (int) $row->mem_used,
            'mem_total' => (int) $row->mem_total,
            'rx' => (int) $row->rx,
            'tx' => (int) $row->tx,
            'temp_cpu' => $row->temp_cpu === null ? null : round((float) $row->temp_cpu, 1),
            'temp_gpu' => $row->temp_gpu === null ? null : round((float) $row->temp_gpu, 1),
            'disk_used' => (int) $row->disk_used,
            'disk_total' => (int) $row->disk_total,
        ])->values();

        return response()->json([
            'minutes' => $minutes,
            'bucket_seconds' => $bucket,
            'from' => $from->toIso8601String(),
            'to' => now()->toIso8601String(),
            'ranges' => self::RANGES,
            'points' => $points,
        ]);
    }

    private function clampRange(int $minutes): int
    {
        if (in_array($minutes, self::RANGES, true)) {
            return $minutes;
        }

        foreach (self::RANGES as $range) {
            if ($minutes <= $range) {
                return $range;
            }
        }

        return end(self::RANGES);
    }

    private function meta(?MetricSample $sample = null): array
    {
        $stored = MetricSample::query()->count();
        $oldestRaw = MetricSample::query()->orderBy('sampled_at')->value('sampled_at');
        $oldest = $oldestRaw ? Carbon::parse($oldestRaw)->toIso8601String() : null;

        return [
            'metrics_interval' => (int) config('systeminfo.metrics_interval', 5),
            'screen_interval' => (int) config('systeminfo.screen_interval', 4),
            'samples_stored' => $stored,
            'oldest_sample_at' => $oldest,
            'chart_ranges' => self::RANGES,
            'sample_age_seconds' => $sample?->sampled_at ? (int) $sample->sampled_at->diffInSeconds(now()) : null,
        ];
    }
}
