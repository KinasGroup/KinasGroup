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
        redirect: 'follow'
      });

      let body = response.body;
      const contentType = response.headers.get('Content-Type') || '';

      if (contentType.includes('text/html')) {
        // NOTE: previously this injected <base href="https://${hostname}/">
        // into every page. That forced ALL directory-relative links
        // (e.g. <a href="register.php"> on /auth/login.php) to resolve
        // against the domain ROOT instead of the page's real directory,
        // producing "File not found" for anything not living at "/".
        // The browser is already on the correct real domain + real path
        // (kinasauto.com/auth/login.php etc.), so no base tag is needed —
        // default relative-link resolution already works correctly.
        let html = await response.text();
        return new Response(html, {
          status: response.status,
          headers: response.headers
        });
      }

      return new Response(body, {
        status: response.status,
        headers: response.headers
      });

    } catch (error) {
      console.error(`Proxy error for ${hostname}:`, error);
      return new Response('Service temporarily unavailable', { status: 503 });
    }
  }
};