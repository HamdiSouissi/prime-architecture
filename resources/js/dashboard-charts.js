import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    ArcElement,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';

// Only the controllers used by the dashboards are registered to keep the chunk small.
Chart.register(
    BarController,
    BarElement,
    CategoryScale,
    DoughnutController,
    ArcElement,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
);

const rtl = document.documentElement.dir === 'rtl';
const locale = rtl ? 'ar-TN' : 'fr-TN';
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const money = new Intl.NumberFormat(locale, { minimumFractionDigits: 3, maximumFractionDigits: 3 });
const compact = new Intl.NumberFormat(locale, { notation: 'compact', maximumFractionDigits: 1 });
const palette = ['#3730a3', '#0f766e', '#b45309', '#0369a1', '#7e22ce', '#be123c'];

function backgroundColor(datasets, index) {
    return datasets[index]?.color ?? palette[index % palette.length];
}

function formatValue(value, kind) {
    return kind === 'currency' ? `${money.format(value)} TND` : compact.format(value);
}

function buildConfig(chart) {
    const kind = chart.datasets[0]?.kind ?? 'count';
    const dashed = chart.datasets[0]?.dashed ?? false;
    const shared = {
        responsive: true,
        maintainAspectRatio: false,
        animation: reduceMotion ? false : { duration: 400 },
        plugins: {
            legend: {
                display: chart.datasets.length > 1,
                rtl,
                position: 'bottom',
                labels: { usePointStyle: true, boxWidth: 10, color: '#475569' },
            },
            tooltip: {
                rtl,
                textDirection: rtl ? 'rtl' : 'ltr',
                callbacks: { label: context => `${context.dataset.label}: ${formatValue(context.parsed.y ?? context.parsed, kind)}` },
            },
        },
        scales: {
            x: {
                reverse: rtl,
                grid: { display: false },
                ticks: { color: '#475569', maxRotation: 0, autoSkip: true },
            },
            y: {
                beginAtZero: true,
                border: { display: false },
                grid: { color: '#e2e8f0' },
                ticks: { color: '#475569', callback: value => formatValue(value, kind) },
            },
        },
    };

    const datasets = chart.datasets.map((dataset, index) => {
        const color = backgroundColor(chart.datasets, index);
        if (chart.type === 'line') {
            return {
                label: dataset.label,
                data: dataset.data,
                borderColor: color,
                backgroundColor: `${color}22`,
                borderWidth: 2,
                borderDash: dashed ? [6, 4] : [],
                pointRadius: 3,
                pointHoverRadius: 5,
                tension: 0.3,
                fill: false,
            };
        }

        return {
            label: dataset.label,
            data: dataset.data,
            backgroundColor: dataset.colors ?? chart.datasets.map((_, i) => backgroundColor(chart.datasets, i)),
            borderRadius: 8,
            maxBarThickness: 42,
            borderWidth: 0,
        };
    });

    if (chart.type === 'doughnut') {
        return {
            type: 'doughnut',
            data: { labels: chart.labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: reduceMotion ? false : { duration: 400 },
                cutout: '62%',
                plugins: {
                    legend: { rtl, position: 'bottom', labels: { usePointStyle: true, boxWidth: 10, color: '#475569' } },
                    tooltip: { rtl, callbacks: { label: context => `${context.label}: ${formatValue(context.parsed, 'count')}` } },
                },
            },
        };
    }

    return {
        type: chart.type === 'line' ? 'line' : 'bar',
        data: { labels: chart.labels, datasets },
        options: shared,
    };
}

for (const element of document.querySelectorAll('[data-dashboard-chart]')) {
    let payload;
    try {
        payload = JSON.parse(element.dataset.dashboardChart);
    } catch {
        continue;
    }
    const canvas = element.querySelector('canvas');
    if (!canvas) continue;
    new Chart(canvas, buildConfig(payload));
}
