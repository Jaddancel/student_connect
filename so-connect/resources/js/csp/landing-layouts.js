/*
 * Alpine components for the "landing-layouts" views, registered before Alpine.start().
 * They replace inline x-data objects/methods and multi-statement handlers,
 * which Alpine's CSP build (no 'unsafe-eval') cannot evaluate.
 */
export default function registerLandingLayouts(Alpine) {
    // <body> of layouts/app and layouts/fullscreen-layout (was an inline x-init).
    Alpine.data("layoutBody", () => ({
        'loaded': true,
        init() {
            const $store = this.$store;
            $store.sidebar.isExpanded = window.innerWidth >= 1280;
            const checkMobile = () => {
                if (window.innerWidth < 1280) {
                    $store.sidebar.setMobileOpen(false);
                    $store.sidebar.isExpanded = false;
                } else {
                    $store.sidebar.isMobileOpen = false;
                    $store.sidebar.isExpanded = true;
                }
            };
            window.addEventListener('resize', checkMobile);
        },
    }));

    // layouts/sidebar. `submenuPaths` lists every sub-item as
    // { key: 'group-item', relativePath: ltrim(path, '/'), path } in menu order.
    Alpine.data("layoutSidebar", ({ currentPath = '', submenuPaths = [] } = {}) => ({
        openSubmenus: {},
        init() {
            // Auto-open Dashboard menu on page load
            this.initializeActiveMenus();
        },
        initializeActiveMenus() {
            // Check if any submenu item matches current path
            submenuPaths.forEach((subItem) => {
                if (currentPath === subItem.relativePath ||
                    window.location.pathname === subItem.path) {
                    this.openSubmenus[subItem.key] = true;
                }
            });
        },
        toggleSubmenu(groupIndex, itemIndex) {
            const key = groupIndex + '-' + itemIndex;
            const newState = !this.openSubmenus[key];

            // Close all other submenus when opening a new one
            if (newState) {
                this.openSubmenus = {};
            }

            this.openSubmenus[key] = newState;
        },
        isSubmenuOpen(groupIndex, itemIndex) {
            const key = groupIndex + '-' + itemIndex;
            return this.openSubmenus[key] || false;
        },
        isActive(path) {
            return window.location.pathname === path || currentPath === path.replace(/^\//, '');
        },
        handleSidebarMouseEnter() {
            if (!this.$store.sidebar.isExpanded) this.$store.sidebar.setHovered(true);
        },
    }));

    // layouts/app-header
    Alpine.data("appHeader", () => ({
        isApplicationMenuOpen: false,
        toggleApplicationMenu() {
            this.isApplicationMenuOpen = !this.isApplicationMenuOpen;
        }
    }));

    // layouts/app-header — desktop dashboard search
    Alpine.data("appHeaderSearch", () => ({
        query: '',
        results: [],
        open: false,
        loading: false,
        activeIndex: -1,
        async search() {
            if (this.query.trim().length < 1) {
                this.results = [];
                this.open = false;
                return;
            }
            this.loading = true;
            try {
                const res = await fetch(`/api/dashboard-search?q=${encodeURIComponent(this.query)}`);
                this.results = await res.json();
                this.open = this.results.length > 0;
                this.activeIndex = -1;
            } catch (e) {
                this.results = [];
            }
            this.loading = false;
        },
        navigate(path) {
            this.open = false;
            this.query = '';
            this.results = [];
            window.location = path;
        },
        moveDown() {
            if (!this.open) return;
            this.activeIndex = (this.activeIndex + 1) % this.results.length;
        },
        moveUp() {
            if (!this.open) return;
            this.activeIndex = this.activeIndex <= 0 ? this.results.length - 1 : this.activeIndex - 1;
        },
        select() {
            if (this.activeIndex >= 0 && this.results[this.activeIndex]) {
                this.navigate(this.results[this.activeIndex].path);
            }
        },
        close() {
            this.open = false;
            this.activeIndex = -1;
        },
        categoryColor(cat) {
            const map = {
                'General': 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300',
                'Account': 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300',
                'Forms': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
                'Admin': 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
                'Superadmin': 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
            };
            return map[cat] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400';
        }
    }));
}
