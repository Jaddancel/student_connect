/**
 * Typeahead combobox for the `search` field type (and sourced selects that opt
 * into search). Filters an inline-injected option list client-side and submits
 * the chosen entry's stored id via a hidden input while showing its label.
 *
 * The option list is resolved server-side, already scoped to what the submitter
 * is authorized to see, so client-side filtering never widens the set.
 */
export function searchSelectField(config) {
    return {
        options: config.options || [], // [{ value, label }]
        selected: config.selected ? String(config.selected) : '',
        query: '',
        open: false,

        // Cap the rendered list so a large source (all members/officers) stays
        // responsive; the query narrows it well before the cap bites.
        limit: 50,

        init() {
            this.query = this.selectedLabel();
        },

        selectedLabel() {
            const opt = this.options.find((o) => String(o.value) === String(this.selected));
            return opt ? opt.label : '';
        },

        get filtered() {
            const q = this.query.trim().toLowerCase();
            const list = q
                ? this.options.filter((o) => String(o.label).toLowerCase().includes(q))
                : this.options;
            return list.slice(0, this.limit);
        },

        onInput() {
            this.open = true;
            // Editing the text abandons a prior pick until a new one is chosen;
            // an emptied box clears the selection outright.
            if (this.query.trim() === '' || this.query !== this.selectedLabel()) {
                this.selected = '';
            }
        },

        choose(opt) {
            this.selected = String(opt.value);
            this.query = opt.label;
            this.open = false;
        },

        clear() {
            this.selected = '';
            this.query = '';
            this.open = false;
        },
    };
}
