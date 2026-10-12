<script setup>
import { computed } from 'vue';

const props = defineProps({
    cores: { type: Array, default: () => [] },
    avg: { type: Number, default: 0 },
});

function barClass(value) {
    if (value >= 85) return 'bg-rose-500';
    if (value >= 60) return 'bg-amber-500';
    if (value >= 30) return 'bg-sky-500';

    return 'bg-emerald-500';
}

const cells = computed(() => props.cores.map((value, index) => ({ index, value })));
</script>

<template>
    <section class="card p-4">
        <header class="mb-3 flex items-baseline justify-between">
            <h2 class="card-title">Ядра</h2>
            <span class="text-[11px] text-slate-500">{{ cores.length }} шт · средняя {{ avg.toFixed(0) }}%</span>
        </header>

        <div class="grid grid-cols-2 gap-x-4 gap-y-2.5 sm:grid-cols-3 xl:grid-cols-4">
            <div v-for="cell in cells" :key="cell.index" class="flex items-center gap-2">
                <span class="w-6 shrink-0 text-[11px] tabular-nums text-slate-500">{{ cell.index }}</span>
                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-800/80">
                    <div
                        class="h-full rounded-full transition-all duration-500"
                        :class="barClass(cell.value)"
                        :style="{ width: `${Math.max(2, cell.value)}%` }"
                    ></div>
                </div>
                <span class="w-9 shrink-0 text-right text-[11px] tabular-nums text-slate-400">
                    {{ cell.value.toFixed(0) }}%
                </span>
            </div>
        </div>
    </section>
</template>
