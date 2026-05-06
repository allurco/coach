// Coach. service worker — minimal for installability.
// No offline caching yet; the app needs network for Gemini + DB anyway.
// Lives at the site root so its scope covers everything.

const VERSION = 'coach-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    // Network-first for everything. The browser handles caching via HTTP.
    return;
});
