export function logoThemeColor(pixels) {
    const colors = new Map();
    for (let i = 0; i < pixels.length; i += 4) {
        const [r, g, b, alpha] = pixels.slice(i, i + 4);
        const brightest = Math.max(r, g, b);
        const darkest = Math.min(r, g, b);
        if (alpha < 128 || brightest < 40 || brightest - darkest < 30) {
            continue;
        }
        const key = [r, g, b].map((value) => Math.floor(value / 32)).join(",");
        const bucket = colors.get(key) || { count: 0, r: 0, g: 0, b: 0 };
        bucket.count++;
        bucket.r += r;
        bucket.g += g;
        bucket.b += b;
        colors.set(key, bucket);
    }
    const dominant = [...colors.values()].sort((a, b) => b.count - a.count)[0];
    return dominant
        ? [dominant.r, dominant.g, dominant.b]
            .map((value) => Math.round(value / dominant.count)).join(", ")
        : "148, 163, 184";
}

export function semesterTimeText(end, now = Date.now()) {
    if (!end) return "Semester end date not configured.";
    const remaining = Date.parse(end) - now;
    if (!Number.isFinite(remaining)) return "Semester end date unavailable.";
    if (remaining <= 0) return "Semester has ended.";
    const totalSeconds = Math.ceil(remaining / 1000);
    const days = Math.ceil(remaining / 86400000);
    if (days > 7) return `Days until semester end: ${days}`;
    const countdownDays = Math.floor(totalSeconds / 86400);
    const hours = String(Math.floor((totalSeconds % 86400) / 3600)).padStart(2, "0");
    const minutes = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, "0");
    const seconds = String(totalSeconds % 60).padStart(2, "0");
    return `Time until semester end: ${countdownDays}d ${hours}h ${minutes}m ${seconds}s`;
}

export function organizationDashboard(end) {
    return {
        themeColor: "148, 163, 184",
        themeError: false,
        semesterText: semesterTimeText(end),
        timer: null,

        init() {
            if (end && Date.parse(end) > Date.now()) {
                this.timer = setInterval(() => {
                    this.semesterText = semesterTimeText(end);
                    if (Date.parse(end) <= Date.now()) clearInterval(this.timer);
                }, 1000);
            }
            this.$nextTick(() => {
                const logo = this.$refs.logo;
                if (logo?.complete && logo.naturalWidth > 0) this.applyLogoTheme(logo);
            });
        },

        applyLogoTheme(logo) {
            try {
                const canvas = document.createElement("canvas");
                canvas.width = canvas.height = 40;
                const context = canvas.getContext("2d");
                if (!context) throw new Error("Canvas is unavailable.");
                context.drawImage(logo, 0, 0, 40, 40);
                this.themeColor = logoThemeColor(context.getImageData(0, 0, 40, 40).data);
                this.themeError = false;
            } catch (error) {
                console.error("Unable to derive organization dashboard theme from logo.", error);
                this.themeError = true;
            }
        },

        destroy() {
            clearInterval(this.timer);
        },
    };
}
