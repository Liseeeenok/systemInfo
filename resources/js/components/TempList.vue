<script setup>
defineProps({
    temps: { type: Array, default: () => [] },
});

function tone(celsius) {
    if (celsius >= 85) return 'text-rose-300 bg-rose-500/10 border-rose-500/30';
    if (celsius >= 70) return 'text-amber-300 bg-amber-500/10 border-amber-500/30';
    if (celsius >= 50) return 'text-sky-300 bg-sky-500/10 border-sky-500/30';

    return 'text-emerald-300 bg-emerald-500/10 border-emerald-500/30';
}
</script>

<template>
    <section class="card p-4">
        <header class="mb-3 flex items-baseline justify-between">
            <h2 class="card-title">Температуры</h2>
            <span class="text-[11px] text-slate-500">lm-sensors</span>
        </header>

        <p v-if="!temps.length" class="text-xs text-slate-500">Датчики недоступны</p>

        <ul class="flex flex-wrap gap-2">
            <li
                v-for="sensor in temps"
                :key="sensor.key"
                class="flex items-baseline gap-2 rounded-lg border px-2.5 py-1.5 text-xs"
                :class="tone(sensor.celsius)"
            >
                <span class="max-w-[160px] truncate opacity-80">{{ sensor.label }}</span>
                <span class="font-semibold tabular-nums">{{ sensor.celsius.toFixed(1) }}°C</span>
            </li>
        </ul>
    </section>
</template>
