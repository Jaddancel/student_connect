const normalize = (value) => String(value ?? "").toLocaleLowerCase();

const escapeHtml = (value) =>
    String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");

/**
 * Reusable accessible combobox used by filters and ordinary selects.
 * Matching accepts non-contiguous characters and ranks exact/prefix/word
 * matches first. All highlighted labels are escaped before markup is added.
 */
export function predictiveSelect(config = {}) {
    return {
        options: (config.options || []).map((option) => ({
            value: String(option.value ?? ""),
            label: String(option.label ?? option.value ?? ""),
            keywords: String(option.keywords ?? ""),
            disabled: !!option.disabled,
        })),
        value: config.selected === null || config.selected === undefined ? "" : String(config.selected),
        query: "",
        open: false,
        activeIndex: -1,
        limit: Number(config.limit || 75),
        autoSubmit: !!config.autoSubmit,
        allowClear: config.allowClear !== false,
        clearValue: String(config.clearValue || ""),
        placeholder: String(config.placeholder || "Select an option"),
        searchPlaceholder: String(config.searchPlaceholder || "Type to search…"),

        init() {
            if (!this.allowClear && !this.value && this.options.length) this.value = this.options[0].value;
            this.query = this.selectedLabel;
            this.$watch("value", () => {
                if (!this.open) this.query = this.selectedLabel;
            });
        },

        get selectedLabel() {
            return this.options.find((option) => option.value === String(this.value))?.label || "";
        },

        match(option) {
            const needle = normalize(this.query.trim());
            const label = normalize(option.label);
            const haystack = `${label} ${normalize(option.keywords)}`;
            if (!needle) return { score: 0, indices: [] };

            const exact = label.indexOf(needle);
            if (exact >= 0) {
                const indices = Array.from({ length: needle.length }, (_, index) => exact + index);
                const word = exact === 0 || /\s|[-/]/.test(label[exact - 1] || "");
                return { score: (exact === 0 ? 1000 : word ? 850 : 700) - exact, indices };
            }

            let position = 0;
            let gap = 0;
            const indices = [];
            for (const character of needle) {
                const found = haystack.indexOf(character, position);
                if (found < 0) return null;
                if (found < label.length) indices.push(found);
                gap += found - position;
                position = found + 1;
            }
            return { score: 400 - gap, indices: [...new Set(indices)] };
        },

        get filtered() {
            return this.options
                .filter((option) => !option.disabled)
                .map((option) => ({ option, result: this.match(option) }))
                .filter((entry) => entry.result !== null)
                .sort((a, b) => b.result.score - a.result.score || a.option.label.localeCompare(b.option.label))
                .slice(0, this.limit)
                .map((entry) => ({ ...entry.option, indices: entry.result.indices }));
        },

        show() {
            this.open = true;
            if (this.query === this.selectedLabel) this.query = "";
            this.activeIndex = this.filtered.findIndex((option) => option.value === String(this.value));
            if (this.activeIndex < 0 && this.filtered.length) this.activeIndex = 0;
        },

        onInput() {
            this.open = true;
            this.activeIndex = this.filtered.length ? 0 : -1;
            if (this.query !== this.selectedLabel) this.value = "";
        },

        move(delta) {
            if (!this.open) this.show();
            const count = this.filtered.length;
            if (!count) return;
            this.activeIndex = (this.activeIndex + delta + count) % count;
            this.$nextTick(() => this.$refs.list?.querySelector('[data-active="true"]')?.scrollIntoView({ block: "nearest" }));
        },

        chooseActive() {
            const option = this.filtered[this.activeIndex];
            if (option) this.choose(option);
        },

        choose(option) {
            this.value = option.value;
            this.query = option.label;
            this.open = false;
            this.activeIndex = -1;
            this.submitIfNeeded();
        },

        clear() {
            if (!this.allowClear) return;
            this.value = this.clearValue;
            this.query = "";
            this.open = false;
            this.activeIndex = -1;
            this.submitIfNeeded();
        },

        close() {
            this.open = false;
            this.query = this.selectedLabel;
        },

        submitIfNeeded() {
            if (this.autoSubmit) this.$nextTick(() => this.$el.closest("form")?.requestSubmit());
        },

        highlighted(option) {
            const indices = new Set(option.indices || []);
            return [...String(option.label)].map((character, index) =>
                indices.has(index)
                    ? `<mark class="rounded bg-brand-100 font-semibold text-brand-800 dark:bg-brand-500/30 dark:text-brand-200">${escapeHtml(character)}</mark>`
                    : escapeHtml(character),
            ).join("");
        },
    };
}
