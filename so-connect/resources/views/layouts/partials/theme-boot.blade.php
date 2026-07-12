{{-- Shared theme bootstrap: anti-flash class application + Alpine theme store.
     The saved choice in localStorage wins; with no saved choice the app always
     starts in light mode so every page opens consistently. --}}
<script>
    (function () {
        const theme = localStorage.getItem('theme') || 'light';
        // Only <html> exists while <head> is parsing; body classes are applied
        // by the Alpine store once the document is ready.
        document.documentElement.classList.toggle('dark', theme === 'dark');
    })();
</script>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('theme', {
            theme: 'light',
            init() {
                this.theme = localStorage.getItem('theme') || 'light';
                this.updateTheme();
            },
            toggle() {
                this.theme = this.theme === 'light' ? 'dark' : 'light';
                localStorage.setItem('theme', this.theme);
                this.updateTheme();
            },
            updateTheme() {
                const isDark = this.theme === 'dark';
                document.documentElement.classList.toggle('dark', isDark);
                if (document.body) {
                    document.body.classList.toggle('dark', isDark);
                    document.body.classList.toggle('bg-gray-900', isDark);
                }
            }
        });
    });
</script>
