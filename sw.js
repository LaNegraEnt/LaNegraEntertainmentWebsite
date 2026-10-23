// La Negra Entertainment – opruim-service-worker.
// De oude site registreerde een service worker die pagina's voor altijd uit de cache serveerde,
// waardoor terugkerende bezoekers oude versies bleven zien. Deze versie wist die cache,
// meldt zichzelf af en laat open tabbladen de actuele site laden. Niet verwijderen:
// browsers met de oude worker halen dit bestand op om zichzelf bij te werken.
self.addEventListener('install', function () { self.skipWaiting(); });

self.addEventListener('activate', function (event) {
  event.waitUntil((async function () {
    const keys = await caches.keys();
    await Promise.all(keys.map(function (k) { return caches.delete(k); }));
    await self.registration.unregister();
    const clients = await self.clients.matchAll({ type: 'window' });
    clients.forEach(function (c) { c.navigate(c.url); });
  })());
});

// Niets onderscheppen: alles gaat gewoon naar de server
self.addEventListener('fetch', function () {});
