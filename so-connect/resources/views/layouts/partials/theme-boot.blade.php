{{-- Shared theme bootstrap — the single source of truth for light/dark across
     every surface (dashboard layouts, auth screens, landing page, org feeds).

     Two layers:
       1. `window.appTheme` — plain JS, no framework. Runs inline in <head> so the
          `dark` class lands on <html> before first paint (no flash), and works on
          pages that never load Alpine (the Bootstrap landing page).
       2. An Alpine `$store.theme` that simply proxies layer 1, so existing
          `$store.theme.toggle()` / `$store.theme.theme` bindings keep working.

     Resolution order: an explicit choice saved in localStorage wins; with no
     saved choice the app follows the OS/browser `prefers-color-scheme`, and
     keeps following it live if the user changes it.

     A saved choice only ever exists while it *differs* from the system: picking
     the theme the system already asks for clears it instead of storing a
     duplicate. So the toggle always alternates between "override" and "follow
     the system", and a choice made months ago can never leave the app stuck
     disagreeing with the browser. --}}
<script>
    (function () {
        const KEY = 'theme';

        const query = window.matchMedia('(prefers-color-scheme: dark)');

        /** What the OS/browser is asking for right now. */
        const system = () => (query.matches ? 'dark' : 'light');

        /** The explicit choice, or null when the user has never picked one. */
        const stored = () => {
            const value = localStorage.getItem(KEY);
            return value === 'dark' || value === 'light' ? value : null;
        };

        const read = () => stored() ?? system();

        /**
         * A saved choice is only meaningful while it contradicts the system; once
         * the two agree, drop it so the app resumes following the browser. Called
         * on boot too, to release stale pins written by earlier builds.
         */
        const releaseRedundantChoice = () => {
            if (stored() === system()) {
                localStorage.removeItem(KEY);
            }
        };

        const apply = (theme) => {
            const isDark = theme === 'dark';
            const root = document.documentElement;
            root.classList.toggle('dark', isDark);
            // Mirrored as an attribute so plain CSS (the Bootstrap landing page)
            // and `color-scheme` can key off it without depending on Tailwind.
            root.setAttribute('data-theme', theme);
            // Kept for backwards compatibility: some styles key off `.dark` on body.
            if (document.body) {
                document.body.classList.toggle('dark', isDark);
            }
        };

        const notify = (theme) => {
            document.dispatchEvent(new CustomEvent('theme-change', { detail: { theme } }));
        };

        const appTheme = {
            get current() {
                return read();
            },
            isDark() {
                return read() === 'dark';
            },
            set(theme) {
                const next = theme === 'dark' ? 'dark' : 'light';
                if (next === system()) {
                    localStorage.removeItem(KEY);
                } else {
                    localStorage.setItem(KEY, next);
                }
                apply(next);
                notify(next);
                return next;
            },
            /** True while an explicit choice is overriding the system. */
            isPinned() {
                return stored() !== null;
            },
            toggle() {
                return appTheme.set(read() === 'dark' ? 'light' : 'dark');
            },
            /** Subscribe to changes; returns an unsubscribe function. */
            onChange(handler) {
                const listener = (event) => handler(event.detail.theme);
                document.addEventListener('theme-change', listener);
                return () => document.removeEventListener('theme-change', listener);
            },
        };

        window.appTheme = appTheme;

        // Before paint: only <html> exists while <head> is parsing.
        releaseRedundantChoice();
        apply(read());
        // Once <body> exists, mirror the class onto it too.
        document.addEventListener('DOMContentLoaded', () => apply(read()));
        // Keep other tabs/windows of the app in sync.
        window.addEventListener('storage', (event) => {
            if (event.key === KEY) {
                apply(read());
                notify(read());
            }
        });
        // Follow the OS switching light/dark — but only while the user has not
        // made an explicit choice of their own. If the OS catches up to that
        // choice the override is redundant, so it is dropped and the app goes
        // back to following along.
        query.addEventListener('change', () => {
            releaseRedundantChoice();

            if (stored() !== null) {
                return;
            }

            apply(read());
            notify(read());
        });
    })();
</script>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('theme', {
            theme: window.appTheme.current,
            init() {
                this.theme = window.appTheme.current;
                window.appTheme.onChange((theme) => {
                    this.theme = theme;
                });
            },
            toggle() {
                window.appTheme.toggle();
            },
            set(theme) {
                window.appTheme.set(theme);
            },
        });
    });
</script>
