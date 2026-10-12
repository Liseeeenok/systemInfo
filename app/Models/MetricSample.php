<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetricSample extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sampled_at' => 'datetime',
            'cpu_per_core' => 'array',
            'net_ifaces' => 'array',
            'disks' => 'array',
            'temps' => 'array',
            'top_cpu' => 'array',
            'top_mem' => 'array',
            'cpu_percent' => 'float',
            'load1' => 'float',
            'load5' => 'float',
            'load15' => 'float',
            'temp_cpu' => 'float',
            'temp_gpu' => 'float',
        ];
    }

    /**
     * Compact representation used by the dashboard "current state" endpoint.
     */
    public function toSnapshot(): array
    {
        return [
            'id' => $this->id,
            'sampled_at' => $this->sampled_at?->toIso8601String(),
            'sampled_at_ts' => $this->sampled_at?->getTimestamp(),
            'cpu_percent' => $this->cpu_percent,
            'cpu_per_core' => $this->cpu_per_core ?: [],
            'cpu_count' => $this->cpu_count,
            'load' => [$this->load1, $this->load5, $this->load15],
            'mem_total' => $this->mem_total,
            'mem_used' => $this->mem_used,
            'mem_available' => $this->mem_available,
            'mem_cached' => $this->mem_cached,
            'mem_percent' => $this->mem_total > 0
                ? round(100 * $this->mem_used / $this->mem_total, 1)
                : 0.0,
            'swap_total' => $this->swap_total,
            'swap_used' => $this->swap_used,
            'net_rx_rate' => $this->net_rx_rate,
            'net_tx_rate' => $this->net_tx_rate,
            'net_ifaces' => $this->net_ifaces ?: [],
            'disk_total' => $this->disk_total,
            'disk_used' => $this->disk_used,
            'disk_percent' => $this->disk_total > 0
                ? round(100 * $this->disk_used / $this->disk_total, 1)
                : 0.0,
            'disks' => $this->disks ?: [],
            'temps' => $this->temps ?: [],
            'temp_cpu' => $this->temp_cpu,
            'temp_gpu' => $this->temp_gpu,
            'uptime' => $this->uptime,
            'procs' => $this->procs,
            'top_cpu' => $this->top_cpu ?: [],
            'top_mem' => $this->top_mem ?: [],
        ];
    }
}
