import test from 'node:test';
import assert from 'node:assert/strict';
import { systemDashboard } from '../../resources/js/components/system-dashboard.js';

function fixture(context) {
    const previousWindow = globalThis.window;
    const charts = [];
    const ticks = [];
    let themeListener;
    let unsubscribed = false;
    globalThis.window = {
        ApexCharts: class {
            constructor(element, options) {
                this.width = element.width;
                this.options = options;
                this.destroyed = false;
                charts.push(this);
            }
            render() {}
            destroy() { this.destroyed = true; }
        },
        appTheme: {
            isDark: () => false,
            onChange(callback) {
                themeListener = callback;
                return () => { unsubscribed = true; };
            },
        },
    };
    context.after(() => { globalThis.window = previousWindow; });
    const component = systemDashboard({
        metrics: {
            period: 'quarterly',
            activity: { points: [], semesters: [] },
            registrations: { labels: [], counts: [] },
        },
        url: '/metrics',
    });
    component.$refs = {
        activityChart: { width: 1438 },
        registrationsChart: { width: 934 },
    };
    component.$nextTick = (callback) => ticks.push(callback);
    component.$watch = () => {};
    return { component, charts, ticks, theme: () => themeListener(), unsubscribed: () => unsubscribed };
}

test('measures chart widths only after Alpine has applied sidebar layout', (context) => {
    const { component, charts, ticks, theme, unsubscribed } = fixture(context);
    component.init();
    assert.equal(charts.length, 0);
    component.$refs.activityChart.width = 1243;
    component.$refs.registrationsChart.width = 804;
    ticks.shift()();
    assert.deepEqual(charts.map((chart) => chart.width), [1243, 804]);
    theme();
    assert.equal(charts.length, 4);
    assert.ok(charts.slice(0, 2).every((chart) => chart.destroyed));
    component.destroy();
    assert.ok(charts.every((chart) => chart.destroyed));
    assert.equal(unsubscribed(), true);
});

test('does not render deferred charts after their component has been removed', (context) => {
    const { component, charts, ticks, unsubscribed } = fixture(context);
    component.init();
    component.destroy();
    ticks.shift()();
    assert.equal(charts.length, 0);
    assert.equal(unsubscribed(), true);
});
