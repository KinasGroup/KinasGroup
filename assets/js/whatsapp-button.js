/**
 * KINAS GROUP — WhatsApp Customer Communication
 *
 * Adds:
 * 1. Global floating WhatsApp contact button.
 * 2. Product detail WhatsApp enquiry button.
 * 3. WhatsApp quick-contact icon on listing cards.
 *
 * This file expects window.SITE_CONSTANTS.WHATSAPP_NUMBER to be printed
 * by the PHP header. A fallback number is included so the feature still
 * works if the constant is not exposed yet.
 */

(function () {
    'use strict';

    // ------------------------------------------------------------
    // CONFIG
    // ------------------------------------------------------------

    const WHATSAPP_NUMBER = String(
        (window.SITE_CONSTANTS && window.SITE_CONSTANTS.WHATSAPP_NUMBER) ||
        '2349137175523'
    ).replace(/[^0-9]/g, '');

    if (!WHATSAPP_NUMBER) {
        return;
    }

    const COMPANY_NAME = 'KINAS GROUP';

    const WHATSAPP_ICON = `
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
        </svg>
    `;

    const SELECTORS = {
        global: '.kinas-whatsapp-float',
        productButton: '.kinas-whatsapp-product-btn',
        cardButton: '.kinas-whatsapp-card-btn',
        detailWrappers: '.je-detail-wrap, .detail-container, .je-spec-panel, .detail-info',
        detailTitle: '.je-spec-title, h1',
        detailPrice: '.je-spec-price, .price',
        ctaRows: '.je-cta-row, .action-buttons, .detail-info .action-buttons',
        listingCards: '.je-card, .listing-card, .product-card, .marketplace-item',
        cardImageWrap: '.je-card-img, .card-image, .product-image, .item-image',
        cardTitle: '.je-card-title, .listing-title, .product-title, .item-title, h3, h4'
    };

    let observerTimer = null;

    // ------------------------------------------------------------
    // HELPERS
    // ------------------------------------------------------------

    function isRestrictedPath() {
        const path = window.location.pathname || '/';

        return (
            path.indexOf('/admin') === 0 ||
            path.indexOf('/agent') === 0 ||
            path.indexOf('/user') === 0 ||
            path.indexOf('/auth') === 0
        );
    }

    function buildWhatsAppLink(message) {
        return 'https://wa.me/' + WHATSAPP_NUMBER + '?text=' + encodeURIComponent(message);
    }

    function generalMessage() {
        return 'Hello ' + COMPANY_NAME + ',\n\nI would like to make an enquiry.';
    }

    function productMessage(title, url, price) {
        let message =
            'Hello ' + COMPANY_NAME + ',\n\n' +
            'I am interested in this product:\n\n' +
            'Product: ' + title + '\n' +
            'Link: ' + url;

        if (price) {
            message += '\nPrice: ' + price;
        }

        message += '\n\nPlease let me know more about availability and next steps.';

        return message;
    }

    function getCanonicalUrl() {
        const canonical = document.querySelector('link[rel="canonical"]');

        if (canonical && canonical.href) {
            return canonical.href;
        }

        return window.location.href;
    }

    function cleanText(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    // ------------------------------------------------------------
    // GLOBAL FLOATING BUTTON
    // ------------------------------------------------------------

    function createGlobalButton() {
        const link = document.createElement('a');

        link.className = 'kinas-whatsapp-float';
        link.href = buildWhatsAppLink(generalMessage());
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.setAttribute('aria-label', 'Chat with ' + COMPANY_NAME + ' on WhatsApp');
        link.setAttribute('data-kinas-whatsapp-global', '1');

        link.innerHTML =
            WHATSAPP_ICON +
            '<span class="kinas-whatsapp-tooltip">Chat with us on WhatsApp</span>';

        return link;
    }

    function addGlobalButton() {
        if (document.querySelector(SELECTORS.global)) {
            return;
        }

        document.body.appendChild(createGlobalButton());
    }

    // ------------------------------------------------------------
    // PRODUCT DETAIL BUTTON
    // ------------------------------------------------------------

    function createProductButton(title, url, price) {
        const link = document.createElement('a');

        link.className = 'kinas-whatsapp-product-btn';
        link.href = buildWhatsAppLink(productMessage(title, url, price));
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.setAttribute('aria-label', 'Enquire about this product on WhatsApp');

        link.innerHTML = WHATSAPP_ICON + '<span>Enquire on WhatsApp</span>';

        return link;
    }

    function decorateDetailPage() {
        const detailWrapper = document.querySelector(SELECTORS.detailWrappers);

        if (!detailWrapper) {
            return;
        }

        if (document.querySelector(SELECTORS.productButton)) {
            return;
        }

        const titleElement = document.querySelector(SELECTORS.detailTitle);

        if (!titleElement) {
            return;
        }

        const title = cleanText(titleElement.textContent) || document.title;
        const priceElement = document.querySelector(SELECTORS.detailPrice);
        const price = priceElement ? cleanText(priceElement.textContent) : '';
        const url = getCanonicalUrl();

        const button = createProductButton(title, url, price);

        const ctaRow = document.querySelector(SELECTORS.ctaRows);

        if (ctaRow) {
            ctaRow.appendChild(button);
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.style.marginTop = '16px';
        wrapper.appendChild(button);

        const target = titleElement.closest('.je-spec-panel, .detail-info') || titleElement.parentElement;

        if (target) {
            target.appendChild(wrapper);
        }
    }

    // ------------------------------------------------------------
    // LISTING CARD WHATSAPP ICON
    // ------------------------------------------------------------

    function createCardButton(title, url) {
        const link = document.createElement('a');

        link.className = 'kinas-whatsapp-card-btn';
        link.href = buildWhatsAppLink(productMessage(title, url, ''));
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.setAttribute('aria-label', 'Contact us about this listing on WhatsApp');
        link.setAttribute('title', 'Enquire on WhatsApp');

        link.innerHTML = WHATSAPP_ICON;

        // Prevent the parent listing card link from opening when the
        // WhatsApp icon is clicked.
        link.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            window.open(link.href, '_blank', 'noopener');
        });

        return link;
    }

    function decorateListingCards(area) {
        const root = area || document;

        const cards = root.querySelectorAll(SELECTORS.listingCards);

        cards.forEach(function (card) {
            if (card.dataset.kinasWhatsappCard === '1') {
                return;
            }

            const imageWrap = card.querySelector(SELECTORS.cardImageWrap);

            if (!imageWrap) {
                return;
            }

            const titleElement = card.querySelector(SELECTORS.cardTitle);
            const imageElement = card.querySelector('img');

            const title =
                cleanText(titleElement ? titleElement.textContent : '') ||
                cleanText(imageElement ? imageElement.alt : '') ||
                document.title;

            const url =
                card.href ||
                (card.querySelector('a[href]') ? card.querySelector('a[href]').href : '') ||
                window.location.href;

            const button = createCardButton(title, url);

            imageWrap.appendChild(button);
            card.dataset.kinasWhatsappCard = '1';
        });
    }

    // ------------------------------------------------------------
    // RUN / OBSERVE
    // ------------------------------------------------------------

    function decorateAll() {
        if (!document.body) {
            return;
        }

        if (isRestrictedPath()) {
            return;
        }

        addGlobalButton();
        decorateDetailPage();
        decorateListingCards(document);
    }

    function observeDom() {
        if (!window.MutationObserver || !document.body) {
            return;
        }

        const observer = new MutationObserver(function () {
            if (observerTimer) {
                return;
            }

            observerTimer = window.setTimeout(function () {
                observerTimer = null;
                decorateAll();
            }, 250);
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    function init() {
        decorateAll();
        observeDom();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Backward compatibility with the old function name.
    window.addWhatsAppButtons = decorateAll;

})();
