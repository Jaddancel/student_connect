import test from "node:test";
import assert from "node:assert/strict";
import {
    logoThemeColor,
    organizationDashboard,
    semesterTimeText,
} from "../../resources/js/components/organization-dashboard.js";

const now = Date.parse("2026-10-12T12:00:00+08:00");

test("shows a live countdown at seven days or less and a day count above seven", () => {
    assert.equal(
        semesterTimeText("2026-10-19T12:00:00+08:00", now),
        "Time until semester end: 7d 00h 00m 00s",
    );
    assert.equal(
        semesterTimeText("2026-10-18T12:00:00+08:00", now),
        "Time until semester end: 6d 00h 00m 00s",
    );
    assert.equal(
        semesterTimeText("2026-10-20T13:02:03+08:00", now),
        "Days until semester end: 9",
    );
    assert.equal(
        semesterTimeText(new Date(now + 7 * 86400000 + 1).toISOString(), now),
        "Days until semester end: 8",
    );
    assert.equal(
        semesterTimeText(new Date(now + 7 * 86400000 - 1000).toISOString(), now),
        "Time until semester end: 6d 23h 59m 59s",
    );
});

test("shows explicit missing, invalid and expired semester states", () => {
    assert.equal(semesterTimeText(null, now), "Semester end date not configured.");
    assert.equal(semesterTimeText("invalid", now), "Semester end date unavailable.");
    assert.equal(semesterTimeText(new Date(now).toISOString(), now), "Semester has ended.");
    assert.equal(semesterTimeText(new Date(now - 1000).toISOString(), now), "Semester has ended.");
    assert.equal(
        semesterTimeText(new Date(now + 1000).toISOString(), now),
        "Time until semester end: 0d 00h 00m 01s",
    );
});

test("samples the dominant logo color, ignoring white, black and transparent pixels", () => {
    const pixels = new Uint8ClampedArray([
        255, 255, 255, 255,
        0, 0, 0, 255,
        200, 30, 30, 0,
        30, 160, 60, 255,
        32, 162, 62, 255,
        30, 160, 60, 255,
        200, 30, 30, 255,
    ]);
    assert.equal(logoThemeColor(pixels), "30, 160, 60");
    assert.equal(logoThemeColor([255, 255, 255, 255]), "148, 163, 184");
    assert.equal(logoThemeColor([]), "148, 163, 184");
});

test("clears the live semester timer when the banner is removed", () => {
    const dashboard = organizationDashboard(new Date(Date.now() + 864000000).toISOString());
    dashboard.$refs = {};
    dashboard.$nextTick = (callback) => callback();
    dashboard.init();
    assert.ok(dashboard.timer);
    dashboard.destroy();
    assert.equal(dashboard.timer._destroyed, true);
});
