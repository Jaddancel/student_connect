/**
 * Manual-filling front-end.
 *
 * `manualFormStart` sits on a builder form's action bar. It POSTs the form's
 * current values to start a manual-filling draft (freezing the exact partial
 * PDF server-side), then sends the user to the draft's resume page.
 *
 * `manualScanUploader` drives that resume page: download the partial PDF, drop
 * the completed scan, upload it, and poll parse status until the reviewable
 * form is ready.
 */
export function manualFormStart(config = {}) {
    return {
        starting: false,
        error: "",

        async start() {
            const form = this.$el.closest("form");
            if (!form || this.starting) return;

            this.starting = true;
            this.error = "";

            try {
                const res = await fetch(config.startUrl, {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": config.csrf,
                        Accept: "application/json",
                    },
                    body: new FormData(form),
                });
                const json = await res.json();
                if (json.ok && json.resume_url) {
                    window.location = json.resume_url;
                    return;
                }
                this.error = json.error || "Could not start manual filling.";
            } catch (e) {
                this.error =
                    "Could not start manual filling — please try again.";
            } finally {
                this.starting = false;
            }
        },
    };
}

export function manualScanUploader(config = {}) {
    return {
        status: config.status || "awaiting_scan",
        error: config.error || "",
        warnings: config.warnings || [],
        files: [],
        dragging: false,
        uploading: false,
        polling: false,

        init() {
            if (this.status === "preparing" || this.status === "parsing") {
                this.poll();
            }
        },

        addFiles(list) {
            for (const file of list) this.files.push(file);
        },

        removeFile(index) {
            this.files.splice(index, 1);
        },

        onDrop(event) {
            this.dragging = false;
            if (event.dataTransfer?.files)
                this.addFiles(event.dataTransfer.files);
        },

        onPick(event) {
            if (event.target?.files) this.addFiles(event.target.files);
            event.target.value = "";
        },

        async upload() {
            if (!this.files.length || this.uploading) return;
            this.uploading = true;
            this.error = "";

            const data = new FormData();
            this.files.forEach((file) => data.append("scans[]", file));
            if (config.token) data.append("manual_token", config.token);

            try {
                const res = await fetch(config.uploadUrl, {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": config.csrf,
                        Accept: "application/json",
                    },
                    body: data,
                });
                const json = await res.json();
                if (json.ok) {
                    this.status = "parsing";
                    this.files = [];
                    this.poll();
                } else {
                    this.error = json.error || "Upload failed.";
                }
            } catch (e) {
                this.error = "Upload failed — please try again.";
            } finally {
                this.uploading = false;
            }
        },

        async retry() {
            this.error = "";
            const body = new FormData();
            if (config.token) body.append("manual_token", config.token);
            try {
                const res = await fetch(config.retryUrl, {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": config.csrf,
                        Accept: "application/json",
                    },
                    body,
                });
                const json = await res.json();
                if (json.ok) {
                    this.status = "parsing";
                    this.poll();
                } else {
                    this.error = json.error || "Could not retry.";
                }
            } catch (e) {
                this.error = "Could not retry — please try again.";
            }
        },

        poll() {
            if (this.polling) return;
            this.polling = true;

            const tick = async () => {
                try {
                    const res = await fetch(config.statusUrl, {
                        headers: { Accept: "application/json" },
                    });
                    const json = await res.json();
                    this.status = json.status;
                    this.error = json.error || "";
                    this.warnings = json.warnings || [];

                    if (json.status === "review") {
                        window.location.reload();
                        return;
                    }
                    if (["awaiting_scan", "failed"].includes(json.status)) {
                        this.polling = false;
                        return;
                    }
                } catch (e) {
                    // transient — keep polling
                }
                setTimeout(tick, 2500);
            };

            setTimeout(tick, 2000);
        },
    };
}
