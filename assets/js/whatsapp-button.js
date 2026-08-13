/**
KINAS GROUP — WhatsApp Customer Communication  (LOGIN-GATED + CLEAN ICON)

This file is deliberately SELF-CONTAINED so we never have to touch
header.php / footer.php again:

1. LOGIN GATE  — reads the logged-in flag from the existing
   <meta name="user-data"> tag (or window.SITE_CONSTANTS). If the visitor
   is NOT logged in, every WhatsApp element (the server-rendered float,
   product buttons, card buttons) is removed and nothing is added.

2. CLEAN ICON  — the previous hand-typed SVG rendered with a sliced
   corner. We replace it everywhere with the Font Awesome
   `fab fa-whatsapp` glyph (already loaded site-wide), including the
   float button that header.php/footer.php render server-side.

Everything else (product enquiry button, listing-card buttons, dynamic
content support) is unchanged from the working version.
*/
(function () {
'use strict';
 if (window.__kinasWhatsAppJsLoaded) {
     return;
 }
 window.__kinasWhatsAppJsLoaded = true;

 const COMPANY_NAME = 'KINAS GROUP';
 const FALLBACK_WHATSAPP_NUMBER = '2349137175523';

 // Clean Font Awesome glyph (replaces the sliced SVG everywhere).
 const WHATSAPP_ICON_HTML = '<i class="fab fa-whatsapp kinas-wa-icon" aria-hidden="true"></i>';

 // ------------------------------------------------------------
 // LOGIN STATE
 // ------------------------------------------------------------
 function isLoggedIn() {
     if (window.SITE_CONSTANTS && typeof window.SITE_CONSTANTS.WHATSAPP_LOGGED_IN !== 'undefined') {
         return window.SITE_CONSTANTS.WHATSAPP_LOGGED_IN === true;
     }
     const meta = document.querySelector('meta[name="user-data"]');
     if (meta && meta.content) {
         try {
             const data = JSON.parse(meta.content);
             return data.loggedIn === true;
         } catch (e) {}
     }
     return false;
 }

 function removeAllWhatsAppElements() {
     const float = document.getElementById('kinas-whatsapp-float');
     if (float) float.remove();
     document.querySelectorAll(
         '.kinas-whatsapp-float, .kinas-whatsapp-product-btn, .kinas-whatsapp-card-btn, [data-kinas-whatsapp-global]'
     ).forEach(function (el) { el.remove(); });
 }

 // Replace any sliced SVG icon with the clean FA glyph.
 function swapIconsToFontAwesome(scope) {
     const root = scope || document;
     root.querySelectorAll(
         '.kinas-whatsapp-float svg, .kinas-whatsapp-product-btn svg, .kinas-whatsapp-card-btn svg'
     ).forEach(function (svg) {
         const holder = document.createElement('span');
         holder.className = 'kinas-wa-icon-wrap';
         holder.innerHTML = WHATSAPP_ICON_HTML;
         svg.replaceWith(holder);
     });
 }

 // ------------------------------------------------------------
 // HELPERS
 // ------------------------------------------------------------
 function getWhatsAppNumber() {
     let number = '';
     if (window.SITE_CONSTANTS && window.SITE_CONSTANTS.WHATSAPP_NUMBER) {
         number = window.SITE_CONSTANTS.WHATSAPP_NUMBER;
     } else {
         const meta = document.querySelector('meta[name="whatsapp-number"]');
         if (meta && meta.content) {
             number = meta.content;
         } else if (document.body && document.body.dataset.whatsappNumber) {
             number = document.body.dataset.whatsappNumber;
         } else {
             number = FALLBACK_WHATSAPP_NUMBER;
         }
     }
     return String(number || '').replace(/[^0-9]/g, '');
 }
 function isRestrictedPath() {
     const path = window.location.pathname || '/';
     return (
         path.indexOf('/admin') === 0 ||
         path.indexOf('/agent') === 0 ||
         path.indexOf('/user') === 0 ||
         path.indexOf('/auth') === 0 ||
         path.indexOf('/api') === 0
     );
 }
 function buildWhatsAppLink(message) {
     const number = getWhatsAppNumber();
     if (!number) return '';
     return 'https://wa.me/' + number + '?text=' + encodeURIComponent(message);
 }
 function generalMessage() {
     return 'Hello ' + COMPANY_NAME + ',\n\nI would like to make an enquiry.';
 }
 function productMessage(title, url, price) {
     let message =
         'Hello ' + COMPANY_NAME + ',\n\n' +
         'I am interested in this product:\n\n' +
         'Product: ' + title;
     if (price) message += '\nPrice: ' + price;
     if (url) message += '\nLink: ' + url;
     message += '\n\nPlease let me know more about availability and next steps.';
     return message;
 }
 function cleanText(value) {
     return String(value || '').replace(/\s+/g, ' ').trim();
 }

 // ------------------------------------------------------------
 // INLINE FALLBACK STYLES (incl. FA glyph sizing)
 // ------------------------------------------------------------
 function injectFallbackStyles() {
     if (document.getElementById('kinas-whatsapp-inline-styles')) return;
     const style = document.createElement('style');
     style.id = 'kinas-whatsapp-inline-styles';
     style.textContent = `
         .kinas-whatsapp-float { position: fixed; bottom: 24px; right: 24px; z-index: 99999; width: 56px; height: 56px; border-radius: 50%; background: #25D366; color: #ffffff; display: flex; align-items: center; justify-content: center; text-decoration: none; box-shadow: 0 8px 24px rgba(0,0,0,0.18); transition: transform .2s ease, background .2s ease; }
         .kinas-whatsapp-float:hover { background: #128C7E; transform: translateY(-2px); }
         .kinas-whatsapp-float .kinas-wa-icon { font-size: 28px; line-height: 1; display: block; color: #ffffff; }
         .kinas-whatsapp-tooltip { position: absolute; right: 66px; top: 50%; transform: translateY(-50%); background: #0A0A0A; color: #fff; padding: 8px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; white-space: nowrap; opacity: 0; pointer-events: none; transition: opacity .2s ease; }
         .kinas-whatsapp-float:hover .kinas-whatsapp-tooltip { opacity: 1; }
         .kinas-whatsapp-product-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: #25D366; color: #fff !important; padding: 12px 20px; border-radius: 8px; text-decoration: none !important; font-weight: 700; font-size: 14px; transition: background .2s ease; border: none; cursor: pointer; }
         .kinas-whatsapp-product-btn:hover { background: #128C7E; }
         .kinas-whatsapp-product-btn .kinas-wa-icon { font-size: 18px; line-height: 1; display: block; color: #fff; }
         .kinas-whatsapp-card-btn { position: absolute; top: 10px; right: 10px; z-index: 6; width: 36px; height: 36px; border-radius: 50%; background: rgba(37,211,102,.96); color: #fff; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,.18); transition: transform .2s ease, background .2s ease; }
         .kinas-whatsapp-card-btn:hover { background: #128C7E; transform: scale(1.08); }
         .kinas-whatsapp-card-btn .kinas-wa-icon { font-size: 18px; line-height: 1; display: block; color: #fff; }
         .kinas-whatsapp-detail-wrap { margin-top: 14px; }
         @media (max-width: 768px) {
             .kinas-whatsapp-float { bottom: 18px; right: 18px; width: 52px; height: 52px; }
             .kinas-whatsapp-float .kinas-wa-icon { font-size: 25px; }
             .kinas-whatsapp-tooltip { display: none; }
             .kinas-whatsapp-product-btn { width: 100%; }
         }
     `;
     document.head.appendChild(style);
 }

 // ------------------------------------------------------------
 // GLOBAL FLOATING BUTTON (only built if missing, logged-in only)
 // ------------------------------------------------------------
 function addGlobalWhatsAppButton() {
     if (isRestrictedPath()) return;
     if (document.getElementById('kinas-whatsapp-float')) return;
     if (document.querySelector('[data-kinas-whatsapp-global]')) return;
     const link = buildWhatsAppLink(generalMessage());
     if (!link) return;
     const button = document.createElement('a');
     button.id = 'kinas-whatsapp-float';
     button.className = 'kinas-whatsapp-float';
     button.href = link;
     button.target = '_blank';
     button.rel = 'noopener noreferrer';
     button.setAttribute('aria-label', 'Chat with ' + COMPANY_NAME + ' on WhatsApp');
     button.innerHTML = WHATSAPP_ICON_HTML + '<span class="kinas-whatsapp-tooltip">Chat with us on WhatsApp</span>';
     document.body.appendChild(button);
 }

 // ------------------------------------------------------------
 // PRODUCT DETAIL PAGE BUTTON
 // ------------------------------------------------------------
 function getProductDetails() {
     const titleElement = document.querySelector('.je-spec-title, h1');
     const priceElement = document.querySelector('.je-spec-price, .price');
     const canonicalElement = document.querySelector('link[rel="canonical"]');
     const title = cleanText(titleElement ? titleElement.textContent : document.title);
     const price = cleanText(priceElement ? priceElement.textContent : '');
     const url = canonicalElement && canonicalElement.href ? canonicalElement.href : window.location.href;
     return { title: title, price: price, url: url };
 }
 function createProductWhatsAppButton() {
     const product = getProductDetails();
     if (!product.title) return null;
     const link = buildWhatsAppLink(productMessage(product.title, product.url, product.price));
     if (!link) return null;
     const button = document.createElement('a');
     button.className = 'kinas-whatsapp-product-btn';
     button.href = link;
     button.target = '_blank';
     button.rel = 'noopener noreferrer';
     button.setAttribute('aria-label', 'Enquire about this product on WhatsApp');
     button.innerHTML = WHATSAPP_ICON_HTML + '<span>Enquire on WhatsApp</span>';
     return button;
 }
 function addDetailPageWhatsAppButton() {
     if (isRestrictedPath()) return;
     if (document.querySelector('.kinas-whatsapp-product-btn')) return;
     const detailWrapper = document.querySelector('.je-detail-wrap, .detail-container, .je-spec-panel, .detail-info');
     if (!detailWrapper) return;
     const button = createProductWhatsAppButton();
     if (!button) return;
     const ctaRow = document.querySelector('.je-cta-row, .action-buttons');
     if (ctaRow) { ctaRow.appendChild(button); return; }
     const priceElement = document.querySelector('.je-spec-price, .price');
     if (priceElement && priceElement.parentElement) {
         const wrapper = document.createElement('div');
         wrapper.className = 'kinas-whatsapp-detail-wrap';
         wrapper.appendChild(button);
         priceElement.insertAdjacentElement('afterend', wrapper);
         return;
     }
     detailWrapper.appendChild(button);
 }

 // ------------------------------------------------------------
 // LISTING CARD BUTTON
 // ------------------------------------------------------------
 function createCardWhatsAppButton(title, url, price) {
     const button = document.createElement('button');
     button.type = 'button';
     button.className = 'kinas-whatsapp-card-btn';
     button.setAttribute('aria-label', 'Enquire about this listing on WhatsApp');
     button.setAttribute('title', 'Enquire on WhatsApp');
     button.innerHTML = WHATSAPP_ICON_HTML;
     button.addEventListener('click', function (event) {
         event.preventDefault();
         event.stopPropagation();
         const link = buildWhatsAppLink(productMessage(title, url, price));
         if (link) window.open(link, '_blank', 'noopener');
     });
     return button;
 }
 function addListingCardWhatsAppButtons(root) {
     const scope = root instanceof Element ? root : document;
     const cards = scope.querySelectorAll('.je-card, .listing-card, .product-card, .marketplace-item');
     cards.forEach(function (card) {
         if (card.dataset.kinasWhatsappCard === '1') return;
         const titleElement = card.querySelector('.je-card-title, .listing-title, .product-title, .item-title, h3, h4');
         const imageElement = card.querySelector('img');
         const title = cleanText(titleElement ? titleElement.textContent : (imageElement && imageElement.alt ? imageElement.alt : document.title));
         const url = card.href || (card.querySelector('a[href]') ? card.querySelector('a[href]').href : '') || window.location.href;
         const priceElement = card.querySelector('.je-card-price, .price, .product-price, .item-price');
         const price = cleanText(priceElement ? priceElement.textContent : '');
         const target = card.querySelector('.je-card-img, .card-image, .product-image, .item-image') || card;
         if (window.getComputedStyle(target).position === 'static') target.style.position = 'relative';
         target.appendChild(createCardWhatsAppButton(title, url, price));
         card.dataset.kinasWhatsappCard = '1';
     });
 }

 // ------------------------------------------------------------
 // INITIALISATION (LOGIN-GATED)
 // ------------------------------------------------------------
 function initialiseWhatsAppButtons() {
     // GUEST: strip every WhatsApp element (incl. the server-rendered
     // float) and add nothing. Logged-in users get the full set.
     if (!isLoggedIn()) {
         removeAllWhatsAppElements();
         return;
     }
     const number = getWhatsAppNumber();
     if (!number) return;
     injectFallbackStyles();
     // Fix the sliced SVG on the server-rendered float, if present.
     swapIconsToFontAwesome(document);
     addGlobalWhatsAppButton();
     addDetailPageWhatsAppButton();
     addListingCardWhatsAppButtons(document);
     observeDynamicContent();
 }
 function observeDynamicContent() {
     if (!window.MutationObserver || !document.body) return;
     let debounceTimer = null;
     const observer = new MutationObserver(function () {
         if (debounceTimer) return;
         debounceTimer = window.setTimeout(function () {
             debounceTimer = null;
             if (!isLoggedIn()) { removeAllWhatsAppElements(); return; }
             swapIconsToFontAwesome(document);
             addGlobalWhatsAppButton();
             addDetailPageWhatsAppButton();
             addListingCardWhatsAppButtons(document);
         }, 250);
     });
     observer.observe(document.body, { childList: true, subtree: true });
 }
 if (document.readyState === 'loading') {
     document.addEventListener('DOMContentLoaded', initialiseWhatsAppButtons);
 } else {
     initialiseWhatsAppButtons();
 }
})();
