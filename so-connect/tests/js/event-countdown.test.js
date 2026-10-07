import test from "node:test";
import assert from "node:assert/strict";
import { eventCountdown } from "../../resources/js/components/event-countdown.js";

test("counts down using offset-aware dates without capping hours at 99", () => {
    const countdown = eventCountdown([{ start: "2026-10-12T14:01:02+08:00" }]);
    countdown.update(Date.parse("2026-10-07T12:00:00+08:00"));
    assert.equal(countdown.hours, "122");
    assert.equal(countdown.minutes, "01");
    assert.equal(countdown.seconds, "02");
});

test("advances to the next event at the start time and never counts negative", () => {
    const events = [
        { title: "First", start: "2026-10-07T12:00:00+08:00" },
        { title: "Second", start: "2026-10-07T12:01:00+08:00" },
    ];
    const countdown = eventCountdown(events);
    countdown.update(Date.parse("2026-10-07T11:59:59+08:00"));
    assert.equal(countdown.nextEvent.title, "First");
    assert.equal(countdown.seconds, "01");
    countdown.update(Date.parse(events[0].start));
    assert.equal(countdown.nextEvent.title, "Second");
    assert.equal(countdown.minutes, "01");
    countdown.update(Date.parse(events[1].start));
    assert.equal(countdown.nextEvent, null);
    assert.equal(countdown.hours + countdown.minutes + countdown.seconds, "000000");
});

test("handles an empty list and clears the timer when removed", () => {
    const countdown = eventCountdown([]);
    countdown.init();
    assert.equal(countdown.nextEvent, null);
    countdown.destroy();
    assert.equal(countdown.timer._destroyed, true);
});
