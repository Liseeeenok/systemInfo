<script setup>
import { formatBytes } from '../lib/format';

defineProps({
    title: { type: String, required: true },
    rows: { type: Array, default: () => [] },
    mode: { type: String, default: 'cpu' },
});
</script>

<template>
    <section class="card p-4">
        <header class="mb-3 flex items-baseline justify-between">
            <h2 class="card-title">{{ title }}</h2>
            <span class="text-[11px] text-slate-500">топ-8</span>
        </header>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="text-[10px] uppercase tracking-wide text-slate-500">
                        <th class="pb-2 pr-3 font-medium">PID</th>
                        <th class="pb-2 pr-3 font-medium">Процесс</th>
                        <th class="pb-2 pr-3 font-medium">Польз.</th>
                        <th class="pb-2 pr-3 text-right font-medium">CPU</th>
                        <th class="pb-2 pr-3 text-right font-medium">RAM</th>
                        <th class="pb-2 text-right font-medium">RSS</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/70">
                    <tr v-for="row in rows" :key="`${row.pid}-${row.name}`" class="hover:bg-slate-800/30">
                        <td class="py-1.5 pr-3 tabular-nums text-slate-500">{{ row.pid }}</td>
                        <td class="max-w-[180px] truncate py-1.5 pr-3 font-medium text-slate-200">{{ row.name }}</td>
                        <td class="py-1.5 pr-3 text-slate-500">{{ row.user }}</td>
                        <td
                            class="py-1.5 pr-3 text-right tabular-nums"
                            :class="mode === 'cpu' ? 'font-semibold text-sky-300' : 'text-slate-400'"
                        >
                            {{ row.cpu.toFixed(1) }}%
                        </td>
                        <td
                            class="py-1.5 pr-3 text-right tabular-nums"
                            :class="mode === 'mem' ? 'font-semibold text-violet-300' : 'text-slate-400'"
                        >
                            {{ row.mem.toFixed(1) }}%
                        </td>
                        <td class="py-1.5 text-right tabular-nums text-slate-400">{{ formatBytes(row.rss) }}</td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="6" class="py-3 text-center text-slate-500">Нет данных</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
