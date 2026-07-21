// KINAS GROUP — Listing location map (property & automobile detail pages)
// Uses Leaflet + OpenStreetMap tiles: free, no API key, no billing account
// required from the client. Renders a single marker for the listing's own
// coordinates — this is what a listing detail page actually needs, not a
// full map-search browsing experience (which isn't part of any page layout
// in this build).
//
// Usage: a container <div id="listing-map" data-lat="..." data-lng="..."
// data-title="..."></div> on the page; this script does the rest.
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('listing-map');
    if (!el) return;

    const lat = parseFloat(el.dataset.lat);
    const lng = parseFloat(el.dataset.lng);
    if (!isFinite(lat) || !isFinite(lng) || (lat === 0 && lng === 0)) {
        el.innerHTML = '<div style="padding:24px;text-align:center;color:#999;font-size:14px;">Location not available for this listing.</div>';
        return;
    }

    function loadLeafletThenRender() {
        if (window.L) { render(); return; }

        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css';
        document.head.appendChild(css);

        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js';
        script.onload = render;
        script.onerror = function () {
            el.innerHTML = '<div style="padding:24px;text-align:center;color:#999;font-size:14px;">Map failed to load.</div>';
        };
        document.head.appendChild(script);
    }

    function render() {
        const map = L.map(el, {
            scrollWheelZoom: false,
            zoomControl: true
        }).setView([lat, lng], 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        const marker = L.marker([lat, lng]).addTo(map);
        const title = el.dataset.title || '';
        if (title) marker.bindPopup(title);

        // Re-center correctly once the container's real layout size is
        // known (Leaflet needs this if the map div was hidden/animated in).
        setTimeout(() => map.invalidateSize(), 150);
    }

    loadLeafletThenRender();
});
