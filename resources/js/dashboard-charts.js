/**
 * TransKinerja — Grafik dashboard (ApexCharts)
 * Tema warna: DigiTrans OSDMRB (teal petrol #163D4F + aksen emas #E8A13C)
 *
 * Grafik dirender pada elemen ber-atribut data-chart:
 *  - data-chart="area" : tren garis/area halus (smooth spline) bergradasi
 *                        data-labels (JSON array), data-values (JSON array), [data-judul]
 *  - data-chart="donut": donat komposisi status dengan total di tengah
 *                        data-labels, data-values, [data-seri-nama]
 *  - data-chart="bar"  : batang horizontal bergradasi (satu seri)
 *                        data-labels, data-values
 *  - data-chart="bar-multi" : batang bertumpuk beberapa seri
 *                        data-labels, data-series (JSON: [{name, data, color?}])
 * Semua grafik: animasi masuk lembut (ease-in-out), tooltip halus,
 * dan otomatis responsif terhadap ukuran layar (desktop → mobile).
 */
import ApexCharts from 'apexcharts';

const WARNA = {
    primary: '#163D4F',
    primaryLight: '#2B5F78',
    primarySoft: '#5E8FA6',
    accent: '#E8A13C',
    success: '#0E9F6E',
    warning: '#E8A13C',
    danger: '#E02424',
    slate: '#94A3B8',
    abu: '#64748B',
    gridLine: '#E2E8F0',
};

const kurangiGerak = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

const animasiDasar = kurangiGerak
    ? { enabled: false }
    : {
          enabled: true,
          easing: 'easeinout',
          speed: 900,
          animateGradually: { enabled: true, delay: 200 },
          dynamicAnimation: { enabled: true, speed: 450 },
      };

const dasar = {
    fontFamily: "'Plus Jakarta Sans', 'Figtree', sans-serif",
    animations: animasiDasar,
    grid: { borderColor: WARNA.gridLine, strokeDashArray: 4, padding: { left: 8, right: 8, top: 0, bottom: 0 } },
    tooltip: { theme: 'dark', style: { fontSize: '12px', fontFamily: "'Plus Jakarta Sans', sans-serif" } },
    states: { hover: { filter: { type: 'lighten' } }, active: { filter: { type: 'darken' } } },
};

const baca = (el, attr, fallback = null) => {
    const mentah = el.dataset[attr];
    if (mentah === undefined || mentah === '') return fallback;
    try { return JSON.parse(mentah); } catch (e) { return fallback; }
};

/* ---------- Grafik area / garis halus ---------- */
function buatArea(el) {
    const labels = baca(el, 'labels', []);
    const values = baca(el, 'values', []);
    const judul = el.dataset.judul || 'Kegiatan';

    const minMobile = window.innerWidth < 640;
    const opsi = {
        ...dasar,
        chart: {
            type: 'area',
            height: el.dataset.tinggi ? parseInt(el.dataset.tinggi, 10) : (minMobile ? 220 : 270),
            fontFamily: dasar.fontFamily,
            toolbar: { show: false },
            zoom: { enabled: false },
            sparkline: { enabled: false },
            animations: { ...animasiDasar, speed: 1100 },
        },
        series: [{ name: judul, data: values }],
        colors: [WARNA.primaryLight],
        stroke: { curve: 'smooth', width: 3, lineCap: 'round' },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.45,
                opacityTo: 0.02,
                stops: [0, 90, 100],
                colorStops: [
                    { offset: 0, color: WARNA.primaryLight, opacity: 0.45 },
                    { offset: 100, color: WARNA.accent, opacity: 0.05 },
                ],
            },
        },
        markers: {
            size: 0,
            hover: { size: 6, sizeOffset: 3 },
            strokeColor: '#fff',
            strokeWidth: 2,
        },
        dataLabels: { enabled: false },
        xaxis: {
            categories: labels,
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: {
                style: { fontSize: '10px', colors: WARNA.abu, fontWeight: 600 },
                trim: true,
                hideOverlappingLabels: true,
            },
            tooltip: { enabled: false },
            crosshairs: {
                stroke: { color: WARNA.primarySoft, dashArray: 3 },
                fill: { type: 'solid' },
            },
        },
        yaxis: {
            min: 0,
            forceNiceScale: true,
            tickAmount: minMobile ? 3 : 5,
            labels: {
                formatter: (v) => (Number.isInteger(+v) ? v : Math.round(v)),
                style: { fontSize: '10px', colors: WARNA.abu },
            },
        },
        tooltip: {
            ...dasar.tooltip,
            y: { formatter: (v) => `${v} kegiatan` },
            marker: { show: true },
        },
    };
    new ApexCharts(el, opsi).render();
}

/* ---------- Grafik donat komposisi ---------- */
function buatDonut(el) {
    const labels = baca(el, 'labels', []);
    const values = baca(el, 'values', []);
    const warna = baca(el, 'colors', [WARNA.success, WARNA.warning, WARNA.primaryLight, WARNA.danger, WARNA.slate]);
    const seriNama = el.dataset.seriNama || 'Total';
    const total = values.reduce((a, b) => a + b, 0);
    const minMobile = window.innerWidth < 640;

    const opsi = {
        ...dasar,
        chart: {
            type: 'donut',
            height: minMobile ? 250 : 280,
            fontFamily: dasar.fontFamily,
            animations: { ...animasiDasar, speed: 1000 },
        },
        series: values,
        labels,
        colors: warna,
        stroke: { width: 2, colors: ['#fff'] },
        dataLabels: {
            enabled: true,
            formatter: (v) => `${Math.round(v)}%`,
            style: { fontSize: '11px', fontWeight: 700, fontFamily: dasar.fontFamily },
            dropShadow: { enabled: false },
        },
        legend: {
            position: 'bottom',
            fontSize: '12px',
            fontWeight: 600,
            markers: { size: 7, strokeWidth: 0 },
            itemMargin: { horizontal: 8, vertical: 4 },
            labels: { colors: WARNA.abu },
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '72%',
                    labels: {
                        show: true,
                        name: { show: true, fontSize: '11px', color: WARNA.abu, offsetY: -2, formatter: () => seriNama },
                        value: {
                            show: true,
                            fontSize: '26px',
                            fontWeight: 800,
                            color: WARNA.primary,
                            offsetY: 6,
                            formatter: (v) => `${v}`,
                        },
                        total: {
                            show: true,
                            showAlways: true,
                            label: seriNama,
                            fontSize: '11px',
                            color: WARNA.abu,
                            formatter: () => total.toLocaleString('id-ID'),
                        },
                    },
                },
            },
        },
        tooltip: {
            ...dasar.tooltip,
            y: { formatter: (v) => `${v} kegiatan (${total ? ((v / total) * 100).toFixed(1) : 0}%)` },
        },
        responsive: [{ breakpoint: 480, options: { legend: { position: 'bottom', fontSize: '11px' } } }],
    };
    new ApexCharts(el, opsi).render();
}

/* ---------- Grafik batang horizontal (satu seri, bergradasi) ---------- */
function buatBar(el) {
    const labels = baca(el, 'labels', []);
    const values = baca(el, 'values', []);
    const warnaUtama = el.dataset.warna || WARNA.primary;
    const minMobile = window.innerWidth < 640;
    const tinggi = el.dataset.tinggi
        ? parseInt(el.dataset.tinggi, 10)
        : Math.max(minMobile ? 240 : 280, labels.length * (minMobile ? 34 : 38));

    const opsi = {
        ...dasar,
        chart: {
            type: 'bar',
            height: tinggi,
            width: '100%',
            fontFamily: dasar.fontFamily,
            toolbar: { show: false },
            animations: { ...animasiDasar, speed: 1000 },
        },
        series: [{ name: el.dataset.judul || 'Kegiatan', data: values }],
        colors: [warnaUtama],
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 6,
                borderRadiusApplication: 'end',
                barHeight: minMobile ? '55%' : '62%',
                distributed: false,
            },
        },
        fill: {
            type: 'gradient',
            gradient: {
                type: 'horizontal',
                shadeIntensity: 0.6,
                gradientToColors: [WARNA.primarySoft],
                inverseColors: false,
                opacityFrom: 1,
                opacityTo: 0.85,
                stops: [0, 100],
            },
        },
        dataLabels: {
            enabled: true,
            textAnchor: 'start',
            offsetX: 2,
            style: { fontSize: minMobile ? '10px' : '11px', fontWeight: 700, colors: [WARNA.abu] },
            formatter: (v) => ` ${v}`,
            background: { enabled: false },
        },
        xaxis: {
            categories: labels,
            axisBorder: { show: false },
            axisTicks: { show: false },
            tickAmount: minMobile ? 4 : undefined,
            labels: { style: { fontSize: '10px', colors: WARNA.abu }, hideOverlappingLabels: true },
        },
        yaxis: {
            labels: {
                maxWidth: minMobile ? 96 : 170,
                style: { fontSize: minMobile ? '10px' : '11px', fontWeight: 600, colors: WARNA.abu },
                trim: true,
            },
        },
        grid: { ...dasar.grid, padding: { left: 4, right: minMobile ? 30 : 24, top: 0, bottom: 0 } },
        tooltip: { ...dasar.tooltip, y: { formatter: (v) => `${v} ${el.dataset.satuan || 'kegiatan'}` } },
    };
    new ApexCharts(el, opsi).render();
}

/* ---------- Grafik batang bertumpuk (multi seri) ---------- */
function buatBarMulti(el) {
    const labels = baca(el, 'labels', []);
    const series = baca(el, 'series', []);
    const minMobile = window.innerWidth < 640;
    const tinggi = Math.max(minMobile ? 260 : 300, labels.length * 44);

    const opsi = {
        ...dasar,
        chart: {
            type: 'bar',
            height: tinggi,
            stacked: true,
            fontFamily: dasar.fontFamily,
            toolbar: { show: false },
            animations: { ...animasiDasar, speed: 1000 },
        },
        series,
        colors: series.map((s) => s.color).filter(Boolean).length === series.length
            ? series.map((s) => s.color)
            : [WARNA.primary, WARNA.accent, WARNA.success],
        plotOptions: {
            bar: { horizontal: true, borderRadius: 4, borderRadiusApplication: 'end', barHeight: '58%' },
        },
        stroke: { width: 1, colors: ['transparent'] },
        dataLabels: {
            enabled: !minMobile,
            style: { fontSize: '10px', fontWeight: 700, colors: ['#fff'] },
            formatter: (v) => (v > 0 ? v : ''),
        },
        legend: {
            position: 'top',
            horizontalAlign: 'right',
            fontSize: '11px',
            fontWeight: 600,
            markers: { size: 7, strokeWidth: 0 },
            itemMargin: { horizontal: 6, vertical: 2 },
            labels: { colors: WARNA.abu },
        },
        xaxis: {
            categories: labels,
            axisBorder: { show: false },
            axisTicks: { show: false },
            tickAmount: minMobile ? 4 : undefined,
            labels: { style: { fontSize: '10px', colors: WARNA.abu }, hideOverlappingLabels: true },
        },
        yaxis: {
            labels: { maxWidth: minMobile ? 90 : 160, style: { fontSize: minMobile ? '10px' : '11px', fontWeight: 600, colors: WARNA.abu }, trim: true },
        },
        grid: { ...dasar.grid, padding: { left: 4, right: 16, top: 4, bottom: 0 } },
        tooltip: { ...dasar.tooltip, y: { formatter: (v) => `${v} kegiatan` } },
    };
    new ApexCharts(el, opsi).render();
}

/* ---------- Inisialisasi saat DOM siap ---------- */
function init() {
    const peta = { area: buatArea, donut: buatDonut, bar: buatBar, 'bar-multi': buatBarMulti };
    document.querySelectorAll('[data-chart]').forEach((el) => {
        const jenis = el.dataset.chart;
        if (peta[jenis]) peta[jenis](el);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
