<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Collects a full snapshot of the local machine state.
 *
 * Everything is read from /proc, /sys and a handful of CLI tools; every part is
 * defensive because the collector runs unattended in a loop and must never die.
 */
class MetricsCollector
{
    public function __construct(private readonly string $statePath) {}

    public static function make(?string $statePath = null): self
    {
        return new self($statePath ?? storage_path('app/systeminfo/state.json'));
    }

    /**
     * Build one sample of the machine state.
     */
    public function sample(): array
    {
        $now = microtime(true);
        $state = $this->loadState();

        $stat = $this->readCpuStat();
        $cpu = $this->cpuUsage($state['cpu'] ?? null, $stat);
        $mem = $this->memory();
        $net = $this->network($state, $now);
        $disks = $this->disks();
        $temps = $this->temperatures();
        [$topCpu, $topMem] = $this->processes();
        $load = $this->loadAverage();

        $this->saveState([
            'ts' => $now,
            'cpu' => $stat,
            'net' => $net['counters'],
        ]);

        $diskTotal = (int) array_sum(array_column($disks, 'total'));
        $diskUsed = (int) array_sum(array_column($disks, 'used'));

        return [
            'sampled_at' => now(),
            'cpu_percent' => $cpu['total'],
            'cpu_per_core' => $cpu['cores'],
            'cpu_count' => max(1, count($cpu['cores'])),
            'load1' => $load[0],
            'load5' => $load[1],
            'load15' => $load[2],
            'mem_total' => $mem['total'],
            'mem_used' => $mem['used'],
            'mem_available' => $mem['available'],
            'mem_cached' => $mem['cached'],
            'swap_total' => $mem['swap_total'],
            'swap_used' => $mem['swap_used'],
            'net_rx_rate' => $net['rx_rate'],
            'net_tx_rate' => $net['tx_rate'],
            'net_ifaces' => $net['ifaces'],
            'disk_total' => $diskTotal,
            'disk_used' => $diskUsed,
            'disks' => $disks,
            'temps' => $temps['list'],
            'temp_cpu' => $temps['cpu'],
            'temp_gpu' => $temps['gpu'],
            'uptime' => $this->uptime(),
            'procs' => $this->processCount(),
            'top_cpu' => $topCpu,
            'top_mem' => $topMem,
        ];
    }

    /**
     * Static host facts (not worth storing on every sample).
     */
    public function hostInfo(): array
    {
        $osRelease = File::exists('/etc/os-release')
            ? parse_ini_string((string) File::get('/etc/os-release'))
            : [];

        $cpuModel = 'unknown';
        foreach (File::lines('/proc/cpuinfo') as $line) {
            if (str_starts_with($line, 'model name')) {
                $cpuModel = trim(explode(':', $line, 2)[1] ?? 'unknown');
                break;
            }
        }

        return [
            'hostname' => gethostname() ?: 'unknown',
            'kernel' => php_uname('r'),
            'os' => $osRelease['PRETTY_NAME'] ?? php_uname('s'),
            'cpu_model' => $cpuModel,
            'cpu_count' => $this->coreCount(),
            'uptime' => $this->uptime(),
            'boot_time' => (int) (time() - $this->uptime()),
            'addresses' => $this->addresses(),
        ];
    }

    /* ------------------------------------------------------------------ CPU */

    private function readCpuStat(): array
    {
        $cores = [];
        foreach (File::lines('/proc/stat') as $line) {
            if (! str_starts_with($line, 'cpu')) {
                continue;
            }
            $parts = preg_split('/\s+/', trim($line));
            $name = array_shift($parts);
            $values = array_map('intval', $parts);
            $cores[$name] = [
                'total' => array_sum($values),
                'idle' => ($values[3] ?? 0) + ($values[4] ?? 0), // idle + iowait
            ];
        }

        return $cores;
    }

    private function cpuUsage(?array $previous, array $current): array
    {
        $cores = [];
        foreach ($current as $name => $ticks) {
            if ($name === 'cpu') {
                continue;
            }
            $prev = $previous[$name] ?? null;
            if (! $prev) {
                $cores[$name] = 0.0;

                continue;
            }
            $dTotal = $ticks['total'] - $prev['total'];
            $dIdle = $ticks['idle'] - $prev['idle'];
            $cores[$name] = $dTotal > 0
                ? round(max(0, min(100, 100 * (1 - $dIdle / $dTotal))), 1)
                : 0.0;
        }

        $prev = $previous['cpu'] ?? null;
        $cur = $current['cpu'] ?? null;
        $total = 0.0;
        if ($prev && $cur) {
            $dTotal = $cur['total'] - $prev['total'];
            $dIdle = $cur['idle'] - $prev['idle'];
            $total = $dTotal > 0 ? round(max(0, min(100, 100 * (1 - $dIdle / $dTotal))), 1) : 0.0;
        }

        return ['total' => $total, 'cores' => array_values($cores)];
    }

    private function coreCount(): int
    {
        $count = 0;
        foreach (File::lines('/proc/cpuinfo') as $line) {
            if (str_starts_with($line, 'processor')) {
                $count++;
            }
        }

        return max(1, $count);
    }

    private function loadAverage(): array
    {
        $load = sys_getloadavg() ?: [0.0, 0.0, 0.0];

        return array_map(fn ($v) => round((float) $v, 2), array_pad($load, 3, 0.0));
    }

    private function uptime(): int
    {
        $raw = File::exists('/proc/uptime') ? (string) File::get('/proc/uptime') : '';
        $parts = explode(' ', trim($raw));

        return (int) (float) ($parts[0] ?? 0);
    }

    /* --------------------------------------------------------------- Memory */

    private function memory(): array
    {
        $info = [];
        foreach (File::lines('/proc/meminfo') as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $info[trim($key)] = ((int) filter_var($value, FILTER_SANITIZE_NUMBER_INT)) * 1024;
        }

        $total = $info['MemTotal'] ?? 0;
        $available = $info['MemAvailable'] ?? ($info['MemFree'] ?? 0);
        $cached = ($info['Cached'] ?? 0) + ($info['Buffers'] ?? 0) + ($info['SReclaimable'] ?? 0);
        $swapTotal = $info['SwapTotal'] ?? 0;
        $swapFree = $info['SwapFree'] ?? 0;

        return [
            'total' => $total,
            'used' => max(0, $total - $available),
            'available' => $available,
            'free' => $info['MemFree'] ?? 0,
            'cached' => $cached,
            'swap_total' => $swapTotal,
            'swap_used' => max(0, $swapTotal - $swapFree),
        ];
    }

    /* -------------------------------------------------------------- Network */

    private function network(array $state, float $now): array
    {
        $previous = $state['net'] ?? [];
        $previousAt = (float) ($state['ts'] ?? 0);
        $elapsed = $previousAt > 0 ? max(0.001, $now - $previousAt) : 0;

        $counters = [];
        $ifaces = [];
        $rxRate = 0;
        $txRate = 0;

        foreach (File::lines('/proc/net/dev') as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            [$name, $rest] = explode(':', $line, 2);
            $name = trim($name);
            if ($name === 'lo') {
                continue;
            }
            $fields = preg_split('/\s+/', trim($rest));
            $rx = (int) ($fields[0] ?? 0);
            $tx = (int) ($fields[8] ?? 0);
            $counters[$name] = ['rx' => $rx, 'tx' => $tx];

            $prev = $previous[$name] ?? null;
            $ifaceRx = 0;
            $ifaceTx = 0;
            if ($prev && $elapsed > 0) {
                $ifaceRx = max(0, (int) round(($rx - (int) $prev['rx']) / $elapsed));
                $ifaceTx = max(0, (int) round(($tx - (int) $prev['tx']) / $elapsed));
            }

            $rxRate += $ifaceRx;
            $txRate += $ifaceTx;

            $ifaces[] = [
                'name' => $name,
                'rx_total' => $rx,
                'tx_total' => $tx,
                'rx_rate' => $ifaceRx,
                'tx_rate' => $ifaceTx,
            ];
        }

        usort($ifaces, fn ($a, $b) => ($b['rx_rate'] + $b['tx_rate']) <=> ($a['rx_rate'] + $a['tx_rate']));

        return [
            'rx_rate' => $rxRate,
            'tx_rate' => $txRate,
            'ifaces' => $ifaces,
            'counters' => $counters,
        ];
    }

    /* ---------------------------------------------------------------- Disks */

    private function disks(): array
    {
        $output = $this->run(
            "df -B1 --output=source,fstype,size,used,avail,pcent,target -x tmpfs -x devtmpfs -x squashfs -x overlay -x efivarfs -x ramfs 2>/dev/null"
        );

        $disks = [];
        foreach (array_slice(preg_split('/\R/', trim($output)) ?: [], 1) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) < 7) {
                continue;
            }
            [$source, $fstype, $size, $used, $avail, $pcent, $target] = $parts;
            $total = (int) $size;
            if ($total <= 0) {
                continue;
            }
            $disks[] = [
                'source' => $source,
                'fstype' => $fstype,
                'mount' => $target,
                'total' => $total,
                'used' => (int) $used,
                'available' => (int) $avail,
                'percent' => (float) rtrim($pcent, '%'),
            ];
        }

        return $disks;
    }

    /* ----------------------------------------------------------- Temperatur */

    private function temperatures(): array
    {
        $json = $this->run('sensors -j 2>/dev/null');
        $decoded = json_decode($json, true);
        $list = [];
        $cpu = null;
        $gpu = null;

        if (is_array($decoded)) {
            foreach ($decoded as $chip => $sections) {
                if (! is_array($sections)) {
                    continue;
                }
                foreach ($sections as $section => $values) {
                    if (! is_array($values)) {
                        continue;
                    }
                    foreach ($values as $key => $value) {
                        if (! preg_match('/^temp\d+_input$/', (string) $key) || ! is_numeric($value)) {
                            continue;
                        }
                        $celsius = round((float) $value, 1);
                        $label = trim(str_replace(['-pci-', '-acpi-', '_hwmon'], [' ', ' ', ' '], (string) $chip)).' '.$section;
                        $entry = [
                            'key' => $chip.'.'.$section,
                            'label' => $label,
                            'chip' => $chip,
                            'sensor' => $section,
                            'celsius' => $celsius,
                        ];
                        $list[] = $entry;

                        if ($cpu === null && str_contains((string) $chip, 'k10temp')) {
                            $cpu = $celsius;
                        }
                        if ($gpu === null && str_contains((string) $chip, 'amdgpu')) {
                            $gpu = $celsius;
                        }
                        if ($cpu === null && str_contains((string) $chip, 'coretemp')) {
                            $cpu = $celsius;
                        }
                    }
                }
            }
        }

        usort($list, fn ($a, $b) => $b['celsius'] <=> $a['celsius']);

        return ['list' => $list, 'cpu' => $cpu, 'gpu' => $gpu];
    }

    /* ------------------------------------------------------------ Processes */

    private function processes(): array
    {
        return [
            $this->topProcesses('-pcpu'),
            $this->topProcesses('-pmem'),
        ];
    }

    private function topProcesses(string $sort): array
    {
        $output = $this->run(
            "ps -eo pid,user,comm,pcpu,pmem,rss --sort={$sort} --no-headers 2>/dev/null | head -n 8"
        );

        $rows = [];
        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) < 6) {
                continue;
            }
            [$pid, $user, $comm, $cpu, $mem, $rss] = $parts;
            $rows[] = [
                'pid' => (int) $pid,
                'user' => $user,
                'name' => $comm,
                'cpu' => (float) $cpu,
                'mem' => (float) $mem,
                'rss' => ((int) $rss) * 1024,
            ];
        }

        return $rows;
    }

    private function processCount(): int
    {
        $count = 0;
        foreach (File::directories('/proc') as $dir) {
            if (is_numeric(basename($dir))) {
                $count++;
            }
        }

        return $count;
    }

    /* -------------------------------------------------------------- Network */

    private function addresses(): array
    {
        $output = $this->run("ip -o -4 addr show scope global 2>/dev/null");
        $addresses = [];
        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            if (preg_match('/^\d+:\s+(\S+)\s+inet\s+(\S+)/', trim($line), $m)) {
                $addresses[] = ['iface' => $m[1], 'address' => $m[2]];
            }
        }

        return $addresses;
    }

    /* ---------------------------------------------------------------- State */

    private function loadState(): array
    {
        try {
            if (File::exists($this->statePath)) {
                $decoded = json_decode((string) File::get($this->statePath), true);

                return is_array($decoded) ? $decoded : [];
            }
        } catch (Throwable) {
            // ignore, start fresh
        }

        return [];
    }

    private function saveState(array $state): void
    {
        try {
            File::ensureDirectoryExists(dirname($this->statePath));
            File::put($this->statePath, json_encode($state));
        } catch (Throwable) {
            // non fatal
        }
    }

    private function run(string $command): string
    {
        try {
            return (string) shell_exec($command);
        } catch (Throwable) {
            return '';
        }
    }
}
