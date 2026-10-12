<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { getJson } from '../lib/api';
import {
    formatBytes,
    formatClock,
    formatPercent,
    formatRange,
    formatRate,
    formatUptime,
} from '../lib/format';
import CoreGrid from './CoreGrid.vue';
import DiskList from './DiskList.vue';
import MetricChart from './MetricChart.vue';
import ProcessTable from './ProcessTable.vue';
import ScreenPanel from './ScreenPanel.vue';
import StatCard from './StatCard.vue';
import TempList from './TempList.vue';

const props = defineProps({
    screenInterval: { type: Number, default: 4 },
    metricsInterval: { type: Number, default: 5 },
    user: { type: Object, default: () => ({}) },
    logoutUrl: { type: String, default: '/logout' },
    csrfToken: { type: String, default: '' },
    routes: { type: Object, required: true },
});

const host = ref(null);
const snapshot = ref(null);
const meta = ref(null);
const points = ref([]);
const minutes = ref(30);
const ranges = ref([5, 15, 60, 180, 720, 1440, 4320, 10080, 43200]);
const error = ref(null);
const clock = ref(Date.now());

let currentTimer = null;
let historyTimer = null;
let clockTimer = null;

async function loadCurrent() {
    try {
        const data = await getJson(props.routes.current);
        host.value = data.host;
        snapshot.value = data.snapshot;
        meta.value = data.meta;
        error.value = null;
    } catch (e) {
        error.value = e.status === 401 ? 'Сессия истекла — войдите заново' : `Нет связи с сервером (${e.message})`;
    }
}

async function loadHistory() {
    try {
        const data = await getJson(`${props.routes.history}?minutes=${minutes.value}`);
        points.value = data.points || [];
        if (data.ranges?.length) ranges.value = data.ranges;
    } catch (e) {
        // keep the previous series on a transient failure
    }
}

function selectRange(value) {
    minutes.value = value;
    loadHistory();
}

onMounted(() => {
    loadCurrent();
    loadHistory();
    currentTimer = setInterval(loadCurrent, 2000);
    historyTimer = setInterval(loadHistory, 20000);
    clockTimer = setInterval(() => {
        clock.value = Date.now();
    }, 1000);
});

onBeforeUnmount(() => {
    clearInterval(currentTimer);
    clearInterval(historyTimer);
    clearInterval(clockTimer);
});

const liveUptime = computed(() => {
    if (!host.value?.uptime) return 0;
    const drift = snapshot.value?.sampled_at_ts ? (clock.value / 1000 - snapshot.value.sampled_at_ts) : 0;

    return host.value.uptime + Math.max(0, drift);
});

const sampleAge = computed(() => meta.value?.sample_age_seconds ?? null);
const collectorStale = computed(() => sampleAge.value !== null && sampleAge.value > props.metricsInterval * 3 + 5);

const diskSeries = computed(() =>
    points.value.map((point) => ({
        ...point,
        disk_pct: point.disk_total > 0 ? (100 * point.disk_used) / point.disk_total : 0,
    })),
);

const historySpan = computed(() => {
    const oldest = meta.value?.oldest_sample_at;
    if (!oldest) return 'менее 5 минут';

    const seconds = Math.max(0, (Date.now() - new Date(oldest).getTime()) / 1000);
    if (seconds < 3600) return `${Math.round(seconds / 60)} мин (${meta.value?.samples_stored ?? 0} сэмплов)`;

    return `${(seconds / 3600).toFixed(1)} ч (${meta.value?.samples_stored ?? 0} сэмплов)`;
});

const cpuSeries = [{ name: 'CPU', field: 'cpu', color: '#38bdf8', type: 'area' }];
const memSeries = [{ name: 'Занято', field: 'mem_used', color: '#a78bfa', type: 'area' }];
const loadSeries = [
    { name: '1 мин', field: 'load1', color: '#22d3ee' },
    { name: '5 мин', field: 'load5', color: '#facc15' },
    { name: '15 мин', field: 'load15', color: '#fb7185' },
];
const netSeries = [
    { name: 'Приём', field: 'rx', color: '#34d399', type: 'area' },
    { name: 'Отдача', field: 'tx', color: '#60a5fa', type: 'area' },
];
const tempSeries = [
    { name: 'CPU', field: 'temp_cpu', color: '#fb7185' },
    { name: 'GPU', field: 'temp_gpu', color: '#f59e0b' },
];
const diskSeriesDefs = [{ name: 'Занято, %', field: 'disk_pct', color: '#c084fc', type: 'area' }];
</script>

<template>
    <div class="min-h-screen">
        <header class="sticky top-0 z-40 border-b border-slate-800/80 bg-slate-950/85 backdrop-blur">
            <div class="mx-auto flex max-w-[1800px] flex-wrap items-center gap-x-5 gap-y-2 px-4 py-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-sky-500 to-indigo-600">
                        <svg class="h-4.5 w-4.5 text-white" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.5h4.5v7H3v-7Zm6.75-9h4.5v16h-4.5v-16Zm6.75 4.5H21v11.5h-4.5V9Z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-white">SystemInfo</div>
                        <div class="text-[11px] text-slate-500">
                            {{ host?.hostname || 'legion' }} · {{ host?.os || 'Linux' }}
                        </div>
                    </div>
                </div>

                <div class="hidden min-w-0 flex-1 items-center gap-4 text-[11px] text-slate-500 lg:flex">
                    <span class="truncate">{{ host?.cpu_model }} · {{ host?.cpu_count }} ядер</span>
                    <span>ядро {{ host?.kernel }}</span>
                    <span>аптайм {{ formatUptime(liveUptime) }}</span>
                    <span v-if="host?.addresses?.length">
                        {{ host.addresses.map((a) => `${a.iface}:${a.address}`).join('  ') }}
                    </span>
                </div>

                <div class="ml-auto flex items-center gap-3">
                    <span
                        class="flex items-center gap-2 rounded-full border px-3 py-1 text-[11px] font-medium"
                        :class="collectorStale ? 'border-rose-500/40 bg-rose-500/10 text-rose-300' : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300'"
                    >
                        <span class="h-1.5 w-1.5 rounded-full" :class="collectorStale ? 'bg-rose-400' : 'bg-emerald-400'"></span>
                        {{ collectorStale ? 'сборщик молчит' : 'online' }}
                    </span>
                    <span class="hidden text-[11px] text-slate-500 sm:inline">семпл {{ formatClock(snapshot?.sampled_at_ts ? snapshot.sampled_at_ts * 1000 : null) }}</span>
                    <div class="hidden text-right sm:block">
                        <div class="text-xs font-medium text-slate-300">{{ user?.name }}</div>
                        <div class="text-[10px] text-slate-500">{{ user?.email }}</div>
                    </div>
                    <form :action="logoutUrl" method="POST">
                        <input type="hidden" name="_token" :value="csrfToken">
                        <button
                            type="submit"
                            class="rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-300 transition hover:border-rose-500/60 hover:text-rose-300"
                        >
                            Выйти
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-[1800px] space-y-4 px-4 py-5">
            <p
                v-if="error"
                class="rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-2.5 text-sm text-rose-200"
            >
                {{ error }}
            </p>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7">
                <StatCard
                    label="CPU"
                    tone="sky"
                    :value="formatPercent(snapshot?.cpu_percent)"
                    :hint="`${snapshot?.cpu_count || 0} ядер`"
                    :percent="snapshot?.cpu_percent ?? null"
                />
                <StatCard
                    label="Память"
                    tone="violet"
                    :value="formatBytes(snapshot?.mem_used)"
                    :hint="`из ${formatBytes(snapshot?.mem_total)} · кэш ${formatBytes(snapshot?.mem_cached)}`"
                    :percent="snapshot?.mem_percent ?? null"
                />
                <StatCard
                    label="Load avg"
                    tone="cyan"
                    :value="(snapshot?.load?.[0] ?? 0).toFixed(2)"
                    :hint="`5м ${(snapshot?.load?.[1] ?? 0).toFixed(2)} · 15м ${(snapshot?.load?.[2] ?? 0).toFixed(2)}`"
                    :percent="snapshot?.cpu_count ? Math.min(100, ((snapshot.load[0] ?? 0) / snapshot.cpu_count) * 100) : null"
                />
                <StatCard
                    label="Диск"
                    tone="amber"
                    :value="formatBytes(snapshot?.disk_used)"
                    :hint="`из ${formatBytes(snapshot?.disk_total)}`"
                    :percent="snapshot?.disk_percent ?? null"
                />
                <StatCard
                    label="Температура"
                    tone="rose"
                    :value="snapshot?.temp_cpu !== null && snapshot?.temp_cpu !== undefined ? `${snapshot.temp_cpu.toFixed(1)}°C` : '—'"
                    :hint="snapshot?.temp_gpu !== null && snapshot?.temp_gpu !== undefined ? `GPU ${snapshot.temp_gpu.toFixed(1)}°C` : 'GPU н/д'"
                    :percent="snapshot?.temp_cpu ? Math.min(100, (snapshot.temp_cpu / 100) * 100) : null"
                />
                <StatCard
                    label="Сеть"
                    tone="emerald"
                    :value="`↓ ${formatRate(snapshot?.net_rx_rate)}`"
                    :hint="`↑ ${formatRate(snapshot?.net_tx_rate)}`"
                />
                <StatCard
                    label="Аптайм"
                    tone="slate"
                    :value="formatUptime(liveUptime)"
                    :hint="`процессов: ${snapshot?.procs ?? 0} · сэмплов: ${meta?.samples_stored ?? 0}`"
                />
            </div>

            <div class="grid gap-4 xl:grid-cols-3">
                <div class="xl:col-span-2">
                    <ScreenPanel :url="routes.screen" :interval="screenInterval" />
                </div>
                <div class="space-y-4">
                    <CoreGrid :cores="snapshot?.cpu_per_core || []" :avg="snapshot?.cpu_percent || 0" />
                    <TempList :temps="snapshot?.temps || []" />
                    <DiskList :disks="snapshot?.disks || []" />
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 pt-1">
                <span class="text-[11px] uppercase tracking-wide text-slate-500">Период:</span>
                <button
                    v-for="range in ranges"
                    :key="range"
                    type="button"
                    class="rounded-lg border px-3 py-1.5 text-xs font-medium transition"
                    :class="
                        minutes === range
                            ? 'border-sky-500 bg-sky-500/15 text-sky-200'
                            : 'border-slate-800 text-slate-400 hover:border-slate-600 hover:text-slate-200'
                    "
                    @click="selectRange(range)"
                >
                    {{ formatRange(range) }}
                </button>
                <span class="ml-auto text-[11px] text-slate-500">
                    {{ points.length }} точек · шаг {{ meta?.metrics_interval }} с · история: {{ historySpan }}
                </span>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <MetricChart title="Загрузка CPU" unit="%" :y-max="100" :points="points" :series="cpuSeries" />
                <MetricChart title="Использование памяти" unit="bytes" :points="points" :series="memSeries" />
                <MetricChart title="Load average" unit="load" :points="points" :series="loadSeries" />
                <MetricChart title="Сеть" unit="rate" :points="points" :series="netSeries" />
                <MetricChart title="Температуры" unit="temp" :points="points" :series="tempSeries" />
                <MetricChart title="Заполнение диска" unit="%" :points="diskSeries" :series="diskSeriesDefs" />
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <ProcessTable title="Топ процессов по CPU" mode="cpu" :rows="snapshot?.top_cpu || []" />
                <ProcessTable title="Топ процессов по памяти" mode="mem" :rows="snapshot?.top_mem || []" />
            </div>

            <div v-if="snapshot?.net_ifaces?.length" class="card p-4">
                <h2 class="card-title mb-3">Сетевые интерфейсы</h2>
                <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="iface in snapshot.net_ifaces"
                        :key="iface.name"
                        class="flex items-center justify-between rounded-lg border border-slate-800/80 px-3 py-2 text-xs"
                    >
                        <span class="font-medium text-slate-300">{{ iface.name }}</span>
                        <span class="tabular-nums text-slate-400">
                            ↓ {{ formatRate(iface.rx_rate) }} · ↑ {{ formatRate(iface.tx_rate) }}
                        </span>
                    </div>
                </div>
            </div>

            <footer class="pb-6 pt-2 text-center text-[11px] text-slate-600">
                Laravel 13 · Vue 3 · ECharts — история метрик хранится
                {{ meta?.oldest_sample_at ? `с ${new Date(meta.oldest_sample_at).toLocaleString('ru-RU')}` : 'менее часа' }}
            </footer>
        </main>
    </div>
</template>
