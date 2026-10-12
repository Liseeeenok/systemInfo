const UNITS = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ', 'ПБ'];

export function formatBytes(bytes, digits = 1) {
    const value = Number(bytes) || 0;
    if (value <= 0) return `0 ${UNITS[0]}`;

    const exponent = Math.min(Math.floor(Math.log(value) / Math.log(1024)), UNITS.length - 1);
    const scaled = value / 1024 ** exponent;
    const precision = scaled >= 100 || exponent === 0 ? 0 : digits;

    return `${scaled.toFixed(precision)} ${UNITS[exponent]}`;
}

export function formatRate(bytesPerSecond, digits = 1) {
    const value = Number(bytesPerSecond) || 0;

    return `${formatBytes(value, digits)}/с`;
}

export function formatPercent(value, digits = 1) {
    return `${(Number(value) || 0).toFixed(digits)}%`;
}

export function formatUptime(seconds) {
    const total = Math.max(0, Math.floor(Number(seconds) || 0));
    const days = Math.floor(total / 86400);
    const hours = Math.floor((total % 86400) / 3600);
    const minutes = Math.floor((total % 3600) / 60);

    if (days > 0) return `${days}д ${hours}ч ${minutes}м`;
    if (hours > 0) return `${hours}ч ${minutes}м`;

    return `${minutes}м`;
}

export function formatClock(timestamp, withSeconds = true) {
    if (!timestamp) return '—';
    const date = new Date(timestamp);
    if (Number.isNaN(date.getTime())) return '—';

    return date.toLocaleTimeString('ru-RU', {
        hour: '2-digit',
        minute: '2-digit',
        ...(withSeconds ? { second: '2-digit' } : {}),
    });
}

export function formatDateTime(timestamp) {
    if (!timestamp) return '—';
    const date = new Date(timestamp);
    if (Number.isNaN(date.getTime())) return '—';

    return date.toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

export function formatRange(minutes) {
    if (minutes < 60) return `${minutes} мин`;
    if (minutes < 1440) return `${Math.round(minutes / 60)} ч`;
    if (minutes < 10080) return `${Math.round(minutes / 1440)} дн`;

    return `${Math.round(minutes / 10080)} нед`;
}

export function toneClasses(tone) {
    const tones = {
        sky: { text: 'text-sky-400', bar: 'bg-sky-500', soft: 'bg-sky-500/10 text-sky-300' },
        violet: { text: 'text-violet-400', bar: 'bg-violet-500', soft: 'bg-violet-500/10 text-violet-300' },
        emerald: { text: 'text-emerald-400', bar: 'bg-emerald-500', soft: 'bg-emerald-500/10 text-emerald-300' },
        amber: { text: 'text-amber-400', bar: 'bg-amber-500', soft: 'bg-amber-500/10 text-amber-300' },
        rose: { text: 'text-rose-400', bar: 'bg-rose-500', soft: 'bg-rose-500/10 text-rose-300' },
        cyan: { text: 'text-cyan-400', bar: 'bg-cyan-500', soft: 'bg-cyan-500/10 text-cyan-300' },
        slate: { text: 'text-slate-300', bar: 'bg-slate-500', soft: 'bg-slate-500/10 text-slate-300' },
    };

    return tones[tone] || tones.slate;
}
