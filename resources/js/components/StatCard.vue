<script setup>
import { computed } from 'vue';
import { toneClasses } from '../lib/format';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: String, required: true },
    hint: { type: String, default: '' },
    percent: { type: Number, default: null },
    tone: { type: String, default: 'slate' },
});

const classes = computed(() => toneClasses(props.tone));
const width = computed(() => `${Math.max(0, Math.min(100, props.percent ?? 0))}%`);
const critical = computed(() => (props.percent ?? 0) >= 85);
</script>

<template>
    <div class="card px-4 py-3">
        <div class="flex items-center justify-between gap-2">
            <span class="card-title">{{ label }}</span>
            <span v-if="percent !== null" class="text-[11px] font-semibold tabular-nums" :class="classes.text">
                {{ Math.round(percent) }}%
            </span>
        </div>

        <div class="mt-1.5 text-xl font-semibold tracking-tight tabular-nums text-slate-100">{{ value }}</div>
        <div v-if="hint" class="mt-0.5 truncate text-[11px] text-slate-500">{{ hint }}</div>

        <div v-if="percent !== null" class="mt-2.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-800/80">
            <div
                class="h-full rounded-full transition-all duration-500"
                :class="critical ? 'bg-rose-500' : classes.bar"
                :style="{ width }"
            ></div>
        </div>
    </div>
</template>
