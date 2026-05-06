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

// IMPORTANT: Chrome's "Add to Home Screen" install banner requires the
// service worker to have a fetch event handler — even an empty one. Without
// this listener the PWA criteria don't pass and the prompt never fires.
// We're not implementing a caching strategy (yet); the listener is here
// solely to satisfy that criterion. Add event.respondWith(...) inside if/
// when offline support gets added.
self.addEventListener('fetch', () => {});
