// Mapping of custom domains to their division folders
const DIVISIONS = {
  'kinasvolt.com': 'kinas-volt',
  'williamsconnecthome.com': 'williams-connect-home',
  'kinasauto.com': 'kinas-auto',
  'kinasstore.com': 'kinas-store'
};

async function handleRequest(request) {
  const url = new URL(request.url);
  const hostname = url.hostname.toLowerCase();
  
  // Check if the domain is one of our divisions
  if (DIVISIONS[hostname]) {
    // Get the division folder name
    const division = DIVISIONS[hostname];
    
    // Get the path (e.g., /about, /products, etc.)
    let path = url.pathname;
    
    // If it's the root path, don't add extra slash
    if (path === '/') {
      path = '';
    }
    
    // Build the new URL - preserve query strings (if any)
    const redirectUrl = `https://kinas-group.com/divisions/${division}${path}${url.search}`;
    
    // Return 301 permanent redirect
    return Response.redirect(redirectUrl, 301);
  }
  
  // If domain not in our list, just pass through normally
  return fetch(request);
}

addEventListener('fetch', event => {
  event.respondWith(handleRequest(event.request));
});
