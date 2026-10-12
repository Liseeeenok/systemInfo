<script setup>
import { formatBytes } from '../lib/format';

defineProps({
    disks: { type: Array, default: () => [] },
});

function barClass(percent) {
    if (percent >= 90) return 'bg-rose-500';
    if (percent >= 75) return 'bg-amber-500';

    return 'bg-violet-500';
}
</script>

<template>
    <section class="card p-4">
        <header class="mb-3 flex items-baseline justify-between">
            <h2 class="card-title">Диски</h2>
            <span class="text-[11px] text-slate-500">{{ disks.length }} ФС</span>
        </header>

        <p v-if="!disks.length" class="text-xs text-slate-500">Нет данных</p>

        <ul class="space-y-3">
            <li v-for="disk in disks" :key="disk.mount">
                <div class="flex items-baseline justify-between gap-2 text-xs">
                    <span class="truncate font-medium text-slate-300">{{ disk.mount }}</span>
                    <span class="shrink-0 tabular-nums text-slate-400">
                        {{ formatBytes(disk.used) }} / {{ formatBytes(disk.total) }}
                    </span>
                </div>
                <div class="mt-1.5 flex items-center gap-2">
                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-800/80">
                        <div
                            class="h-full rounded-full transition-all duration-500"
                            :class="barClass(disk.percent)"
                            :style="{ width: `${Math.min(100, disk.percent)}%` }"
                        ></div>
                    </div>
                    <span class="w-10 shrink-0 text-right text-[11px] tabular-nums text-slate-400">
                        {{ disk.percent.toFixed(0) }}%
                    </span>
                </div>
                <div class="mt-1 truncate text-[10px] text-slate-600">
                    {{ disk.source }} · {{ disk.fstype }} · свободно {{ formatBytes(disk.available) }}
                </div>
            </li>
        </ul>
    </section>
</template>
