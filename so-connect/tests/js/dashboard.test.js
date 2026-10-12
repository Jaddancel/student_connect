import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/dashboard.js', import.meta.url), 'utf8');

async function dashboard() {
    const stores = {};
    const calls = [];
    let response = { ok: true, json: async () => ({ data: [] }) };
    const Alpine = {
        store(name, value) {
            if (value) stores[name] = value;
            return stores[name];
        },
    };
    vm.runInNewContext(source, {
        window: { Alpine },
        document: { addEventListener: (_name, callback) => callback() },
        fetch: async (url) => {
            calls.push(url);
            return response;
        },
    });
    await new Promise((resolve) => setImmediate(resolve));
    return { stores, calls, respond: (next) => { response = next; } };
}

for (const window of ['quarter', 'year']) {
    test(`${window} sends the rolling window and charts all days including zero days`, async () => {
        const { stores, calls, respond } = await dashboard();
        const start = window === 'quarter' ? new Date(2026, 6, 12, 12) : new Date(2025, 9, 12, 12);
        const end = new Date(2026, 9, 12, 12);
        respond({
            ok: true,
            json: async () => ({
                range: { starts_at: start.toISOString(), ends_at: end.toISOString() },
                data: [
                    { requested_at: start.toISOString() },
                    { requested_at: end.toISOString() },
                ],
            }),
        });
        await stores.dashboardWindow.setWindow(window);
        assert.equal(calls.at(-1), `/api/dashboard/requests/membership?window=${window}`);
        assert.equal(stores.dashboardData.totalRequests, 2);
        assert.equal(stores.dashboardData.volumeSeries.length, window === 'quarter' ? 93 : 366);
        assert.equal(stores.dashboardData.volumeSeries.reduce((sum, bucket) => sum + bucket.count, 0), 2);
        assert.ok(stores.dashboardData.volumeSeries.some((bucket) => bucket.count === 0));
        assert.equal(stores.dashboardData.volumeLegendLabel, 'Requests per day');
        assert.equal(stores.dashboardData.metricWindowLabel, window === 'quarter' ? 'Last quarter' : 'Last year');
        assert.equal(stores.dashboardData.error, null);
        await stores.dashboardType.toggleType('events');
        assert.equal(calls.at(-1), `/api/dashboard/requests/events?window=${window}`);
        await stores.dashboardType.toggleType('roles');
        assert.ok(calls.includes(`/api/dashboard/requests/policy?window=${window}`));
    });
}

test('shows API errors without stale counts or hourly chart data', async () => {
    const { stores, respond } = await dashboard();
    respond({ ok: false, json: async () => ({ message: 'Unable to load dashboard data.' }) });
    await stores.dashboardWindow.setWindow('year');
    assert.equal(stores.dashboardData.error, 'Unable to load dashboard data.');
    assert.equal(stores.dashboardData.totalRequests, 0);
    assert.equal(stores.dashboardData.volumeSeries.length, 0);
    assert.equal(stores.dashboardData.loading, false);
});

test('preserves existing day, month and overall request parameters', async () => {
    const { stores, calls } = await dashboard();
    assert.equal(calls.at(-1), '/api/dashboard/requests/membership?hours=24');
    await stores.dashboardWindow.setWindow('month');
    assert.equal(calls.at(-1), '/api/dashboard/requests/membership?hours=720');
    await stores.dashboardWindow.setWindow('all');
    assert.equal(calls.at(-1), '/api/dashboard/requests/membership');
});
