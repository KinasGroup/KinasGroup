// Mapping of custom domains to their division folders
const DIVISIONS = {
  'kinasvolt.com': 'kinas-volt',
  'williamsconnecthome.com': 'williams-connect-home',
  'kinasauto.com': 'kinas-automobile',
  'kinasstore.com': 'kinas-marketplace'
};

const GLOBAL_PATHS = [
  '/assets/', '/templates/', '/includes/', '/pages/', '/blog/',
  '/auth/', '/api/', '/admin/', '/agent/', '/user/',
  '/database/', '/generated-pdfs/', '/roundcube/', '/public/'
];

export default {
  async fetch(request) {
    const url = new URL(request.url);
    let hostname = url.hostname.toLowerCase();
    if (hostname.startsWith('www.')) hostname = hostname.substring(4);

    if (!DIVISIONS[hostname]) {
      return fetch(request);
    }

    const division = DIVISIONS[hostname];
    let path = url.pathname;

    // === KEY FIX: Force division index with proper context ===
    if (path === '/' || path === '/index.php' || path === '') {
      path = `/divisions/${division}/index.php`;
    }

    // Each division domain gets its own sitemap (its real, self-contained
    // set of URLs on ITS OWN domain) rather than the shared corporate one —
    // a division's sitemap.xml should only ever list URLs that live on that
    // same domain, which the old shared file never did (it hardcoded a
    // different domain entirely). kinas-group.com continues to serve its
    // own separate corporate-only sitemap unchanged.
    if (path === '/sitemap.xml') {
      path = `/divisions/${division}/sitemap.xml`;
    }

    const isGlobalAsset = GLOBAL_PATHS.some(prefix => path.startsWith(prefix));

    const headers = new Headers(request.headers);
    headers.set('Host', 'kinas-group.com');
    headers.set('X-Original-Host', hostname);           // ← Tell PHP the real domain
    headers.set('X-Forwarded-Proto', 'https');
    headers.set('X-Forwarded-Host', hostname);

    let targetUrl = `https://kinas-group.com:8080${path}${url.search}`;

    try {
      const response = await fetch(targetUrl, {
        method: request.method,
        headers: headers,
        body: request.body,
        // Was 'follow' — that made the Worker's OWN fetch() silently chase
        // any 30x itself (e.g. cart.php's `header('Location: /auth/login.php')`
        // when not logged in) and hand the browser a 200 with the login
        // page's HTML — while the browser's address bar stayed on
        // /divisions/kinas-marketplace/cart.php. login.php builds its
        // asset paths relative to ITS real location (/auth/, one level
        // deep); the browser resolved them relative to what it thought
        // its own URL was (/divisions/kinas-marketplace/, two levels
        // deep) instead, so every CSS/JS reference 404'd — bare,
        // unstyled page. 'manual' + forwarding the 30x below makes the
        // browser do a real navigation instead, landing it on the true
        // URL with paths resolving correctly.
        redirect: 'manual'
      });

      // Backend issued a redirect (e.g. requireLogin() sending an
      // unauthenticated visitor to /auth/login.php) — pass it straight
      // through to the browser instead of following it ourselves.
      if (response.status >= 300 && response.status < 400 && response.headers.has('Location')) {
        const location = response.headers.get('Location');
        const newHeaders = new Headers(response.headers);
        // Location is already domain-relative (e.g. "/auth/login.php") in
        // this codebase, so the browser resolves it against whichever
        // division domain it's actually on — no rewriting needed. If a
        // future redirect ever included the internal backend's own host
        // (kinas-group.com), it would leak here, so guard against that.
        if (location && location.includes('kinas-group.com')) {
          newHeaders.set('Location', location.replace('kinas-group.com', hostname));
        }
        return new Response(null, { status: response.status, headers: newHeaders });
      }

      const newHeaders = new Headers(response.headers);

      // Shared static assets (css/js/images/fonts under the paths in
      // GLOBAL_PATHS) are byte-identical no matter which of the 4 division
      // domains requested them and don't change on every deploy. Letting
      // the browser (and Cloudflare's edge) cache them long-term instead of
      // re-fetching from the origin on every visit cuts load time and
      // origin bandwidth. Only applied to successful, cacheable asset
      // responses — never to PHP pages, which can be logged-in/dynamic.
      if (isGlobalAsset && response.ok && !path.endsWith('.php')) {
        newHeaders.set('Cache-Control', 'public, max-age=604800, stale-while-revalidate=86400');
      }

      // Response body is streamed straight through untouched — this used
      // to buffer the entire HTML into a string (via response.text()) to
      // inject a <base> tag; that injection was removed since it broke
      // relative links on pages served from their true directory (see the
      // login.php note above), but the buffering step was accidentally
      // left behind, adding latency and memory overhead on every HTML
      // response for no remaining benefit.
      return new Response(response.body, {
        status: response.status,
        headers: newHeaders
      });

    } catch (error) {
      console.error(`Proxy error for ${hostname}:`, error);
      return new Response('Service temporarily unavailable', { status: 503 });
    }
  }
};
