/**
 * Super admin System Dashboard (pages/dashboard/superadmin.blade.php).
 *
 * `uptimeClock` ticks the header's server uptime locally from the seconds the
 * server reported at render time. `systemDashboard` owns the "Data View"
 * period switcher and the three period-driven widgets, refetching
 * /superadmin/dashboard/metrics whenever the period changes.
 */

export function uptimeClock(initialSeconds) {
    return {
        available: Number.isFinite(initialSeconds),
        days: 0,
        digits: ["0", "0", "0", "0", "0", "0"],
        startedAt: 0,
        timer: null,

        init() {
            if (!this.available) return;
            this.startedAt = Date.now() - initialSeconds * 1000;
            this.update();
            this.timer = setInterval(() => this.update(), 1000);
        },

        update() {
            const total = Math.max(0, Math.floor((Date.now() - this.startedAt) / 1000));
            this.days = Math.floor(total / 86400);
            const hours = Math.floor((total % 86400) / 3600);
            const minutes = Math.floor((total % 3600) / 60);
            const seconds = total % 60;
            this.digits = [hours, minutes, seconds]
                .map((part) => String(part).padStart(2, "0"))
                .join("")
                .split("");
        },

        destroy() {
            clearInterval(this.timer);
        },
    };
}

const SEMESTER_RED = "#ef4444";
const BRAND = "#465fff";

function chartPalette() {
    const dark = window.appTheme?.isDark?.() ?? false;
    return {
        grid: dark ? "rgba(255,255,255,0.05)" : "#f3f4f6",
        label: dark ? "#6b7280" : "#9ca3af",
    };
}

export function systemDashboard({ metrics, url }) {
    // Chart instances live outside Alpine's reactive state; proxying them breaks ApexCharts.
    let activityChart = null;
    let registrationsChart = null;
    let unsubscribeTheme = null;

    return {
        period: metrics.period,
        metrics,
        loading: false,
        error: "",

        init() {
            this.renderActivity();
            this.renderRegistrations();
            unsubscribeTheme = window.appTheme?.onChange?.(() => {
                this.renderActivity();
                this.renderRegistrations();
            });
            this.$watch("period", (value) => this.load(value));
        },

        destroy() {
            activityChart?.destroy();
            registrationsChart?.destroy();
            unsubscribeTheme?.();
        },

        async load(period) {
            this.loading = true;
            this.error = "";
            try {
                const response = await fetch(`${url}?period=${encodeURIComponent(period)}`, {
                    headers: { Accept: "application/json" },
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                this.metrics = await response.json();

                const pageUrl = new URL(window.location.href);
                pageUrl.searchParams.set("period", period);
                window.history.replaceState({}, "", pageUrl);

                this.renderActivity();
                this.renderRegistrations();
            } catch (e) {
                this.error = "Couldn't load data for this period. Please try again.";
            } finally {
                this.loading = false;
            }
        },

        get activityTitle() {
            const count = this.metrics.activity.points.filter((p) => p.major).length;
            return `Activity over the last ${count} ${this.metrics.period_noun}`;
        },

        get registrationsTitle() {
            return `User registrations over the last ${this.metrics.registrations.labels.length} ${this.metrics.period_noun}`;
        },

        get errorRate() {
            return this.metrics.error_rate;
        },

        get errorRateText() {
            const rate = this.errorRate.rate;
            return rate === null ? "—" : `${rate.toFixed(2)}%`;
        },

        get comparisonText() {
            const e = this.errorRate;
            if (!e.previous_label) return "No previous period to compare.";
            if (e.comparison === null) {
                return e.rate === null
                    ? "No traffic recorded in this period yet."
                    : `No traffic recorded in ${e.previous_label}.`;
            }
            const word = { higher: "Higher than", lower: "Lower than", equal: "Equal to" }[e.comparison];
            return `${word} ${e.previous_label} (${e.previous_rate.toFixed(2)}%)`;
        },

        get comparisonClass() {
            return {
                higher: "text-error-600 dark:text-error-400",
                lower: "text-success-600 dark:text-success-400",
                equal: "text-gray-500 dark:text-gray-400",
            }[this.errorRate.comparison] ?? "text-gray-400 dark:text-gray-500";
        },

        activityAnnotations(palette) {
            const xaxis = [];
            // Period labels sit exactly on each period's first point; ApexCharts
            // thins numeric ticks, so axis labels alone can't guarantee that.
            this.metrics.activity.points.forEach((point, index) => {
                if (!point.major) return;
                xaxis.push({
                    x: index,
                    borderColor: palette.grid,
                    strokeDashArray: 4,
                    label: {
                        text: point.major,
                        position: "bottom",
                        orientation: "horizontal",
                        textAnchor: index === 0 ? "start" : "middle",
                        offsetY: 22,
                        borderWidth: 0,
                        // Colour comes from .sc-period-label in app.css (the global
                        // .apexcharts-text rule would override an inline fill).
                        style: { background: "transparent", fontSize: "11px", cssClass: "sc-period-label" },
                    },
                });
            });
            for (const marker of this.metrics.activity.semesters) {
                if (marker.prep_from_x !== null && marker.prep_to_x !== null) {
                    xaxis.push({
                        x: marker.prep_from_x,
                        x2: marker.prep_to_x,
                        fillColor: SEMESTER_RED,
                        opacity: 0.12,
                        borderColor: SEMESTER_RED,
                        strokeDashArray: 3,
                    });
                }
                if (marker.start_x !== null) {
                    xaxis.push({
                        x: marker.start_x,
                        borderColor: SEMESTER_RED,
                        strokeDashArray: 0,
                        borderWidth: 2,
                    });
                }
            }
            return { xaxis };
        },

        renderActivity() {
            const el = this.$refs.activityChart;
            if (!el || !window.ApexCharts) return;

            const points = this.metrics.activity.points;
            const palette = chartPalette();

            activityChart?.destroy();
            activityChart = new window.ApexCharts(el, {
                // Numeric point-index axis: semester markers carry fractional
                // positions, which category axes can't place.
                series: [{ name: "Activity", data: points.map((p, i) => [i, p.total]) }],
                chart: {
                    type: "area",
                    height: 300,
                    fontFamily: "Outfit, sans-serif",
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    background: "transparent",
                    animations: { enabled: true, speed: 500 },
                },
                colors: [BRAND],
                stroke: { curve: "smooth", width: 2.5 },
                fill: {
                    type: "gradient",
                    gradient: { shadeIntensity: 1, opacityFrom: 0.25, opacityTo: 0.02, stops: [0, 100] },
                },
                dataLabels: { enabled: false },
                markers: { size: 0, hover: { size: 4 } },
                grid: { borderColor: palette.grid, strokeDashArray: 3, padding: { top: 16 } },
                annotations: this.activityAnnotations(palette),
                xaxis: {
                    type: "numeric",
                    min: 0,
                    max: Math.max(points.length - 1, 1),
                    labels: {
                        // Blank, but kept so the plot reserves room for the period annotations.
                        formatter: () => "",
                    },
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    tooltip: { enabled: false },
                },
                yaxis: {
                    min: 0,
                    forceNiceScale: true,
                    labels: {
                        style: { colors: [palette.label], fontSize: "11px" },
                        formatter: (v) => Math.round(v).toLocaleString(),
                    },
                },
                tooltip: {
                    custom: ({ dataPointIndex }) => {
                        const p = points[dataPointIndex];
                        if (!p) return "";
                        return `<div class="px-3 py-2 text-xs">
                            <div class="mb-1 font-semibold">${p.range}</div>
                            <div>Requests: <b>${p.requests}</b></div>
                            <div>Documents generated: <b>${p.generations}</b></div>
                            <div>Downloads: <b>${p.downloads}</b></div>
                            <div class="mt-1 border-t pt-1">Total: <b>${p.total}</b></div>
                        </div>`;
                    },
                },
            });
            activityChart.render();
        },

        renderRegistrations() {
            const el = this.$refs.registrationsChart;
            if (!el || !window.ApexCharts) return;

            const { labels, counts } = this.metrics.registrations;
            const palette = chartPalette();

            registrationsChart?.destroy();
            registrationsChart = new window.ApexCharts(el, {
                series: [{ name: "New users", data: counts }],
                chart: {
                    type: "bar",
                    height: 260,
                    fontFamily: "Outfit, sans-serif",
                    toolbar: { show: false },
                    background: "transparent",
                    animations: { enabled: true, speed: 500 },
                },
                colors: [BRAND],
                plotOptions: {
                    bar: { columnWidth: "55%", borderRadius: 5, borderRadiusApplication: "end" },
                },
                dataLabels: { enabled: false },
                grid: { borderColor: palette.grid, strokeDashArray: 3 },
                xaxis: {
                    categories: labels,
                    labels: {
                        rotate: -30,
                        hideOverlappingLabels: false,
                        style: { colors: Array(labels.length).fill(palette.label), fontSize: "10px" },
                    },
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                },
                yaxis: {
                    min: 0,
                    forceNiceScale: true,
                    labels: {
                        style: { colors: [palette.label], fontSize: "11px" },
                        formatter: (v) => Math.round(v).toLocaleString(),
                    },
                },
                tooltip: { y: { formatter: (v) => `${v.toLocaleString()} new user${v === 1 ? "" : "s"}` } },
            });
            registrationsChart.render();
        },
    };
}
