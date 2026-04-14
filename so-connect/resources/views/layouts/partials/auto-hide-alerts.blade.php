<script>
    (function () {
        const AUTO_HIDE_MS = 10000;
        const ALERT_SELECTOR = [
            '[role="alert"]',
            '.auto-hide-alert',
            '.alert',
            '.border-success-300.bg-success-50',
            '.border-error-300.bg-error-50',
            '.border-warning-300.bg-warning-50',
        ].join(', ');

        const timers = new WeakMap();

        const isVisible = (element) => {
            if (!(element instanceof HTMLElement)) {
                return false;
            }

            return !!(element.offsetWidth || element.offsetHeight || element.getClientRects().length);
        };

        const clearTimer = (element) => {
            const existingTimer = timers.get(element);

            if (existingTimer) {
                clearTimeout(existingTimer);
                timers.delete(element);
            }
        };

        const hideAlert = (element) => {
            if (!(element instanceof HTMLElement) || !element.isConnected) {
                return;
            }

            element.dataset.autoHiding = '1';
            element.style.transition = 'opacity 250ms ease';
            element.style.opacity = '0';

            window.setTimeout(() => {
                if (!element.isConnected) {
                    return;
                }

                element.classList.add('hidden');
                element.setAttribute('aria-hidden', 'true');
                element.dataset.autoHiding = '0';
                element.style.removeProperty('opacity');
                element.style.removeProperty('transition');
            }, 260);
        };

        const scheduleAutoHide = (element) => {
            if (!(element instanceof HTMLElement) || !element.matches(ALERT_SELECTOR)) {
                return;
            }

            if (element.dataset.autoHiding === '1') {
                return;
            }

            if (!isVisible(element)) {
                clearTimer(element);

                return;
            }

            clearTimer(element);

            const timeoutId = window.setTimeout(() => {
                timers.delete(element);
                hideAlert(element);
            }, AUTO_HIDE_MS);

            timers.set(element, timeoutId);
        };

        const scanForAlerts = (root) => {
            if (!(root instanceof HTMLElement || root instanceof Document)) {
                return;
            }

            if (root instanceof HTMLElement && root.matches(ALERT_SELECTOR)) {
                scheduleAutoHide(root);
            }

            root.querySelectorAll(ALERT_SELECTOR).forEach(scheduleAutoHide);
        };

        document.addEventListener('DOMContentLoaded', () => {
            scanForAlerts(document);

            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'childList') {
                        mutation.addedNodes.forEach((node) => {
                            if (node instanceof HTMLElement) {
                                scanForAlerts(node);
                            }
                        });
                    }

                    if (mutation.type === 'attributes' && mutation.target instanceof HTMLElement) {
                        scheduleAutoHide(mutation.target);
                    }
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['class', 'style'],
            });
        });
    })();
</script>