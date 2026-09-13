// Installability, and an offline notice for navigations. Nothing is cached
// on purpose: boards change under people, and files are too big to keep
// copies of on a phone -- "no connection" is better than a stale board.
self.addEventListener('install', () => self.skipWaiting())
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()))

const OFFLINE = `<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Offline · SlipNote</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;font:16px/1.5 system-ui,sans-serif;background:#f3f5fa;color:#1b1f27;text-align:center;padding:24px}
@media(prefers-color-scheme:dark){body{background:#1a1c20;color:#e6e8ec}}h1{font-size:22px;margin:0 0 6px}p{margin:0;opacity:.7}</style>
<div><h1>You're offline</h1><p>SlipNote needs a connection to open a board. Try again when you're back online.</p></div>`

self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') return
    event.respondWith(fetch(event.request).catch(() =>
        new Response(OFFLINE, { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } })
    ))
})
