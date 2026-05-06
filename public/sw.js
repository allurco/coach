// Coach. service worker — minimal for installability.
// No offline caching yet; the app needs network for Gemini + DB anyway.
// Lives at the site root so its scope covers everything.

const VERSION = 'coach-v1';

self.addEventListener('install', (event) => {
    // Tie skipWaiting() to the lifecycle so the browser holds the install
    // state until activation is ready, instead of resolving the promise
    // detached from waitUntil().
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

// No fetch handler: we want the browser's default network behavior. Add an
// event.respondWith(...) here when implementing an offline/cache strategy.
