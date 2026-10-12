<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { preloadImage } from '../lib/api';
import { formatClock } from '../lib/format';

const props = defineProps({
    url: { type: String, required: true },
    interval: { type: Number, default: 4 },
});

const src = ref(null);
const frameTime = ref(null);
const failed = ref(false);
const zoomed = ref(false);
const countdown = ref(props.interval);
const fullscreenUrl = ref(null);

let frameTimer = null;
let tickTimer = null;

async function refresh() {
    const url = `${props.url}?t=${Date.now()}`;
    const loaded = await preloadImage(url);

    if (loaded) {
        src.value = loaded;
        frameTime.value = Date.now();
        failed.value = false;
    } else {
        failed.value = true;
    }

    countdown.value = props.interval;
}

function openZoom() {
    fullscreenUrl.value = `${props.url}?t=${Date.now()}`;
    zoomed.value = true;
}

function onKeydown(event) {
    if (event.key === 'Escape') zoomed.value = false;
}

onMounted(() => {
    refresh();
    frameTimer = setInterval(refresh, Math.max(2, props.interval) * 1000);
    tickTimer = setInterval(() => {
        countdown.value = Math.max(0, countdown.value - 1);
    }, 1000);
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    clearInterval(frameTimer);
    clearInterval(tickTimer);
    window.removeEventListener('keydown', onKeydown);
});

const statusText = computed(() => (failed.value ? 'нет кадра' : `кадр ${formatClock(frameTime.value)}`));
</script>

<template>
    <section class="card overflow-hidden">
        <header class="flex items-center justify-between gap-3 border-b border-slate-800/80 px-4 py-3">
            <div class="flex items-center gap-2.5">
                <span class="h-2 w-2 rounded-full" :class="failed ? 'bg-rose-500' : 'bg-emerald-500 glow-dot'"></span>
                <h2 class="card-title">Экран сервера</h2>
                <span class="text-[11px] text-slate-500">{{ statusText }} · обновление {{ interval }} с</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="hidden text-[11px] tabular-nums text-slate-500 sm:inline">
                    обновление через {{ countdown }} с
                </span>
                <button
                    type="button"
                    class="rounded-md border border-slate-700 px-2.5 py-1 text-[11px] font-medium text-slate-300 transition hover:border-sky-500 hover:text-sky-300"
                    @click="openZoom"
                >
                    Во весь экран
                </button>
            </div>
        </header>

        <div class="relative bg-slate-950/80 p-3">
            <img
                v-if="src"
                :src="src"
                alt="Скриншот рабочего стола"
                class="w-full cursor-zoom-in rounded-lg border border-slate-800 object-contain"
                style="aspect-ratio: 16 / 9"
                @click="openZoom"
            >
            <div
                v-else
                class="flex w-full items-center justify-center rounded-lg border border-dashed border-slate-800 text-sm text-slate-500"
                style="aspect-ratio: 16 / 9"
            >
                Ожидание первого кадра…
            </div>
        </div>

        <Transition name="fade">
            <div
                v-if="zoomed"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/90 p-4 backdrop-blur-sm"
                @click="zoomed = false"
            >
                <img
                    :src="fullscreenUrl"
                    alt="Скриншот рабочего стола"
                    class="max-h-full max-w-full rounded-lg border border-slate-700 shadow-2xl"
                >
                <button
                    type="button"
                    class="absolute right-5 top-5 rounded-lg border border-slate-600 bg-slate-900/80 px-3 py-1.5 text-sm text-slate-200 hover:border-sky-500"
                    @click.stop="zoomed = false"
                >
                    Закрыть (Esc)
                </button>
            </div>
        </Transition>
    </section>
</template>
