/**
 * PWA: register the service worker.
 * Runs after the page loads so it doesn't block rendering.
 */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js', { scope: '/' })
            .then((registration) => {
                // Request background sync permission if available
                if ('sync' in registration) {
                    navigator.serviceWorker.ready.then((reg) => {
                        reg.sync.register('gps-sync').catch(() => {});
                    });
                }
            })
            .catch((err) => {
                console.warn('SW registration failed:', err);
            });
    });
}
