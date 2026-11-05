import { captureException, init } from '@sentry/browser';

init({
    dsn: 'https://1244a70854ae429599aa0403d94d6647@sentry.bukajuv.net/1',
    environment: ['localhost', '127.0.0.1', '[::1]'].includes(location.hostname)
        ? 'development'
        : 'production',
    integrations: integrations => integrations.filter(
        integration => integration.name !== 'BrowserSession',
    ),
});

window.kofeinMonitoring = { captureException };

window.addEventListener('error', event => {
    if (event.target instanceof HTMLScriptElement && event.target.src) {
        captureException(new Error('Script could not be loaded.'), {
            extra: { scriptUrl: event.target.src },
        });
    }
}, true);
