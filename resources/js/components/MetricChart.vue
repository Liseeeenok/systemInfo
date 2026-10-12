<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import * as echarts from 'echarts';
import { formatBytes, formatRate } from '../lib/format';

const props = defineProps({
    title: { type: String, default: '' },
    points: { type: Array, default: () => [] },
    series: { type: Array, required: true },
    unit: { type: String, default: '' },
    height: { type: Number, default: 230 },
    yMax: { type: Number, default: null },
    showLegend: { type: Boolean, default: true },
});

const el = ref(null);
let chart = null;

function axisFormatter(value) {
    switch (props.unit) {
        case '%':
            return `${value}%`;
        case 'bytes':
            return formatBytes(value, 0);
        case 'rate':
            return `${formatBytes(value, 0)}/с`;
        case 'temp':
            return `${value}°`;
        default:
            return value;
    }
}

function valueFormatter(value) {
    switch (props.unit) {
        case '%':
            return `${(Number(value) || 0).toFixed(1)}%`;
        case 'bytes':
            return formatBytes(value);
        case 'rate':
            return formatRate(value);
        case 'temp':
            return `${(Number(value) || 0).toFixed(1)} °C`;
        default:
            return Number(value).toFixed(2);
    }
}

function buildOption() {
    const points = props.points || [];

    return {
        backgroundColor: 'transparent',
        animation: true,
        animationDuration: 260,
        grid: {
            left: 6,
            right: 14,
            top: props.showLegend && props.series.length > 1 ? 34 : 12,
            bottom: 4,
            containLabel: true,
        },
        tooltip: {
            trigger: 'axis',
            backgroundColor: 'rgba(2, 6, 23, 0.94)',
            borderColor: 'rgba(51, 65, 85, 0.9)',
            borderWidth: 1,
            padding: [8, 12],
            textStyle: { color: '#e2e8f0', fontSize: 12 },
            axisPointer: { type: 'line', lineStyle: { color: 'rgba(148, 163, 184, 0.35)' } },
            formatter: (params) => {
                if (!params?.length) return '';
                const header = new Date(params[0].value[0]).toLocaleString('ru-RU', {
                    day: '2-digit',
                    month: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                });
                const rows = params
                    .map(
                        (item) =>
                            `<div style="display:flex;align-items:center;gap:8px;margin-top:4px">
                                <span style="width:8px;height:8px;border-radius:999px;background:${item.color}"></span>
                                <span style="color:#94a3b8">${item.seriesName}</span>
                                <span style="margin-left:auto;font-weight:600;color:#f8fafc">${valueFormatter(item.value[1])}</span>
                            </div>`,
                    )
                    .join('');

                return `<div style="font-size:11px;color:#64748b">${header}</div>${rows}`;
            },
        },
        legend:
            props.showLegend && props.series.length > 1
                ? {
                      top: 0,
                      right: 0,
                      icon: 'roundRect',
                      itemWidth: 10,
                      itemHeight: 10,
                      itemGap: 14,
                      textStyle: { color: '#94a3b8', fontSize: 11 },
                  }
                : { show: false },
        xAxis: {
            type: 'time',
            boundaryGap: false,
            axisLine: { lineStyle: { color: 'rgba(51, 65, 85, 0.9)' } },
            axisTick: { show: false },
            axisLabel: { color: '#64748b', fontSize: 11, hideOverlap: true },
            splitLine: { show: false },
        },
        yAxis: {
            type: 'value',
            min: 0,
            max: props.yMax,
            axisLine: { show: false },
            axisTick: { show: false },
            axisLabel: { color: '#64748b', fontSize: 11, formatter: axisFormatter },
            splitLine: { lineStyle: { color: 'rgba(51, 65, 85, 0.35)' } },
        },
        series: props.series.map((definition) => ({
            name: definition.name,
            type: 'line',
            smooth: 0.25,
            showSymbol: false,
            sampling: 'lttb',
            lineStyle: { width: 2, color: definition.color },
            itemStyle: { color: definition.color },
            areaStyle:
                definition.type === 'area'
                    ? {
                          opacity: 0.9,
                          color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                              { offset: 0, color: `${definition.color}55` },
                              { offset: 1, color: 'rgba(2, 6, 23, 0)' },
                          ]),
                      }
                    : undefined,
            data: points.map((point) => [point.ts, point[definition.field]]),
        })),
    };
}

function render() {
    if (!chart) return;
    chart.setOption(buildOption(), { replaceMerge: ['series'] });
}

onMounted(() => {
    if (!el.value) return;
    chart = echarts.init(el.value, null, { renderer: 'canvas' });
    render();
    window.addEventListener('resize', resize);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', resize);
    chart?.dispose();
    chart = null;
});

function resize() {
    chart?.resize();
}

watch(() => [props.points, props.series, props.unit], render, { deep: true });
</script>

<template>
    <section class="card p-4">
        <header class="mb-1 flex items-baseline justify-between gap-3">
            <h2 class="card-title">{{ title }}</h2>
            <slot name="aside" />
        </header>
        <div ref="el" :style="{ height: `${height}px`, width: '100%' }"></div>
    </section>
</template>
