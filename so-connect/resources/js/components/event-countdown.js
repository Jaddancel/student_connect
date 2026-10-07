export function eventCountdown(events) {
    return {
        events,
        nextEvent: null,
        hours: "00",
        minutes: "00",
        seconds: "00",
        timer: null,

        init() {
            this.update();
            this.timer = setInterval(() => this.update(), 1000);
        },

        update(now = Date.now()) {
            this.nextEvent = this.events.find(
                (event) => Date.parse(event.start) > now,
            ) || null;
            const remaining = this.nextEvent
                ? Math.ceil((Date.parse(this.nextEvent.start) - now) / 1000)
                : 0;
            this.hours = String(Math.floor(remaining / 3600)).padStart(2, "0");
            this.minutes = String(Math.floor((remaining % 3600) / 60)).padStart(2, "0");
            this.seconds = String(remaining % 60).padStart(2, "0");
        },

        destroy() {
            clearInterval(this.timer);
        },
    };
}
