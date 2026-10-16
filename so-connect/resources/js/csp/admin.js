/*
 * Alpine components for the "admin" views, registered before Alpine.start().
 * They replace inline x-data objects/methods and multi-statement handlers,
 * which Alpine's CSP build (no 'unsafe-eval') cannot evaluate.
 */
export default function registerAdmin(Alpine) {
    // Client-side pagination shared by the admin *-requests index pages.
    Alpine.data("adminRequestPagination", ({ perPage = 10, total = 0 } = {}) => ({
        page: 1,
        perPage,
        total,
        get pages() {
            return Math.max(1, Math.ceil(this.total / this.perPage));
        },
        get visiblePages() {
            const s = Math.max(1, this.page - 1);
            const e = Math.min(this.pages, this.page + 1);
            return Array.from({ length: e - s + 1 }, (_, i) => s + i);
        },
        onPage(i) {
            return i >= (this.page - 1) * this.perPage && i < this.page * this.perPage;
        },
        setPage(p) {
            this.page = Math.max(1, Math.min(this.pages, p));
        },
        get from() {
            return this.total === 0 ? 0 : (this.page - 1) * this.perPage + 1;
        },
        get to() {
            return Math.min(this.page * this.perPage, this.total);
        },
    }));
}
