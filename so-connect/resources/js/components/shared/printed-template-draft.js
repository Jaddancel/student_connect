/**
 * Step-2 printed-template plumbing shared by the form builder and the report
 * template builder. Both talk to the same event-driven OnlyOffice component
 * (components/form-builder/onlyoffice-template.blade.php):
 *  - entering Step 2 POSTs a draft sync, then dispatches
 *    'printed-template:sync' with the editor URLs;
 *  - saving first asks the component to flush pending editor changes into the
 *    draft ('printed-template:flush-request' → 'printed-template:flush-done').
 */

/**
 * POST a draft sync. Resolves { detail, draftId } on success, or
 * { error } on failure.
 */
export async function syncPrintedTemplateDraft(url, csrf, body) {
    try {
        const res = await fetch(url, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrf,
                Accept: "application/json",
            },
            body: JSON.stringify(body),
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok) {
            const firstError = json.errors
                ? Object.values(json.errors).flat()[0]
                : null;
            return {
                error:
                    firstError ||
                    json.message ||
                    "Could not prepare the printed template.",
            };
        }
        return {
            draftId: json.draftId,
            detail: {
                configUrl: json.configUrl,
                importUrl: json.importUrl,
                versionUrl: json.versionUrl,
                slots: json.slots,
                addUrl: json.addUrl,
            },
        };
    } catch (e) {
        return { error: "Could not prepare the printed template." };
    }
}

/** Boot (or reboot) the editor component against a synced draft. */
export function dispatchPrintedTemplateSync(detail) {
    window.dispatchEvent(new CustomEvent("printed-template:sync", { detail }));
}

/**
 * Ask the Step-2 component to flush pending editor changes into the draft.
 * Resolves true once the draft holds the latest edits (or there is nothing to
 * flush); false on timeout, in which case the caller must not save — adopting
 * the draft would fold the stale pre-edit document and clear the draft before
 * the Document Server's final save lands.
 */
export function flushPrintedTemplate() {
    return new Promise((resolve) => {
        const done = (event) => {
            window.removeEventListener("printed-template:flush-done", done);
            clearTimeout(timer);
            resolve(!event.detail || event.detail.ok !== false);
        };
        window.addEventListener("printed-template:flush-done", done);
        // Slightly longer than the component's own flush timeout, so an
        // absent/unresponsive component can't hang the save forever.
        const timer = setTimeout(() => {
            window.removeEventListener("printed-template:flush-done", done);
            resolve(false);
        }, 25000);
        window.dispatchEvent(new CustomEvent("printed-template:flush-request"));
    });
}
