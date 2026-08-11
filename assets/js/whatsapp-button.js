/**
 * KINAS GROUP — WhatsApp Customer Communication
 *
 * Adds:
 * 1. Global floating WhatsApp contact button.
 * 2. Product detail page WhatsApp enquiry button.
 * 3. WhatsApp quick-contact icon on listing cards.
 *
 * Works with:
 * - window.SITE_CONSTANTS.WHATSAPP_NUMBER
 * - meta[name="whatsapp-number"]
 * - body[data-whatsapp-number]
 * - fallback public WhatsApp number
 */

(function () {
    'use strict';

    // Prevent duplicate execution if the script is included more than once.
    if (window.__kinasWhatsAppJsLoaded) {
        return;
    }

    window.__kinasWhatsAppJsLoaded = true;

    // ------------------------------------------------------------
    // CONFIGURATION
    // ------------------------------------------------------------

    const COMPANY_NAME = 'KINAS GROUP';

    /**
     * Public fallback number.
     *
     * This should normally come from window.SITE_CONSTANTS.WHATSAPP_NUMBER,
     * which should be printed by PHP from includes/constants.php.
     */
    const FALLBACK_WHATSAPP_NUMBER = '2349137175523';

    const WHATSAPP_ICON_SVG = `
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
        </svg>
    `;

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

        if (!number) {
            return '';
        }

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

        if (price) {
            message += '\nPrice: ' + price;
        }

        if (url) {
            message += '\nLink: ' + url;
        }

        message += '\n\nPlease let me know more about availability and next steps.';

        return message;
    }

    function cleanText(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    // ------------------------------------------------------------
    // INLINE FALLBACK STYLES
    //
    // These are used if assets/css/whatsapp-button.css is missing
    // or has not been loaded yet.
    // ------------------------------------------------------------

    function injectFallbackStyles() {
        if (document.getElementById('kinas-whatsapp-inline-styles')) {
            return;
        }

        const style = document.createElement('style');
        style.id = 'kinas-whatsapp-inline-styles';

        style.textContent = `
            .kinas-whatsapp-float {
                position: fixed;
                bottom: 24px;
                right: 24px;
                z-index: 99999;
                width: 56px;
                height: 56px;
                border-radius: 50%;
                background: #25D366;
                color: #ffffff;
                display: flex;
                align-items: center;
                justify-content: center;
                text-decoration: none;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
                transition: transform 0.2s ease, background 0.2s ease;
            }

            .kinas-whatsapp-float:hover {
                background: #128C7E;
                transform: translateY(-2px);
            }

            .kinas-whatsapp-float svg {
                width: 28px;
                height: 28px;
                fill: currentColor;
                display: block;
            }

            .kinas-whatsapp-tooltip {
                position: absolute;
                right: 66px;
                top: 50%;
                transform: translateY(-50%);
                background: #0A0A0A;
                color: #ffffff;
                padding: 8px 12px;
                border-radius: 8px;
                font-size: 12px;
                font-weight: 600;
                white-space: nowrap;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.2s ease;
            }

            .kinas-whatsapp-float:hover .kinas-whatsapp-tooltip {
                opacity: 1;
            }

            .kinas-whatsapp-product-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                background: #25D366;
                color: #ffffff !important;
                padding: 12px 20px;
                border-radius: 8px;
                text-decoration: none !important;
                font-weight: 700;
                font-size: 14px;
                transition: background 0.2s ease;
                border: none;
                cursor: pointer;
            }

            .kinas-whatsapp-product-btn:hover {
                background: #128C7E;
            }

            .kinas-whatsapp-product-btn svg {
                width: 18px;
                height: 18px;
                fill: currentColor;
                flex-shrink: 0;
            }

            .kinas-whatsapp-card-btn {
                position: absolute;
                top: 10px;
                right: 10px;
                z-index: 6;
                width: 36px;
                height: 36px;
                border-radius: 50%;
                background: rgba(37, 211, 102, 0.96);
                color: #ffffff;
                display: flex;
                align-items: center;
                justify-content: center;
                border: none;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18);
                transition: transform 0.2s ease, background 0.2s ease;
            }

            .kinas-whatsapp-card-btn:hover {
                background: #128C7E;
                transform: scale(1.08);
            }

            .kinas-whatsapp-card-btn svg {
                width: 18px;
                height: 18px;
                fill: currentColor;
                display: block;
            }

            .kinas-whatsapp-detail-wrap {
                margin-top: 14px;
            }

            @media (max-width: 768px) {
                .kinas-whatsapp-float {
                    bottom: 18px;
                    right: 18px;
                    width: 52px;
                    height: 52px;
                }

                .kinas-whatsapp-float svg {
                    width: 25px;
                    height: 25px;
                }

                .kinas-whatsapp-tooltip {
                    display: none;
                }

                .kinas-whatsapp-product-btn {
                    width: 100%;
                }
            }
        `;

        document.head.appendChild(style);
    }

    // ------------------------------------------------------------
    // GLOBAL FLOATING WHATSAPP BUTTON
    // ------------------------------------------------------------

    function addGlobalWhatsAppButton() {
        if (isRestrictedPath()) {
            return;
        }

        if (document.getElementById('kinas-whatsapp-float')) {
            return;
        }

        const link = buildWhatsAppLink(generalMessage());

        if (!link) {
            return;
        }

        const button = document.createElement('a');

        button.id = 'kinas-whatsapp-float';
        button.className = 'kinas-whatsapp-float';
        button.href = link;
        button.target = '_blank';
        button.rel = 'noopener noreferrer';
        button.setAttribute('aria-label', 'Chat with ' + COMPANY_NAME + ' on WhatsApp');

        button.innerHTML =
            WHATSAPP_ICON_SVG +
            '<span class="kinas-whatsapp-tooltip">Chat with us on WhatsApp</span>';

        document.body.appendChild(button);
    }

    // ------------------------------------------------------------
    // PRODUCT DETAIL PAGE WHATSAPP BUTTON
    // ------------------------------------------------------------

    function getProductDetails() {
        const titleElement = document.querySelector('.je-spec-title, h1');
        const priceElement = document.querySelector('.je-spec-price, .price');
        const canonicalElement = document.querySelector('link[rel="canonical"]');

        const title = cleanText(titleElement ? titleElement.textContent : document.title);
        const price = cleanText(priceElement ? priceElement.textContent : '');
        const url = canonicalElement && canonicalElement.href
            ? canonicalElement.href
            : window.location.href;

        return {
            title: title,
            price: price,
            url: url
        };
    }

    function createProductWhatsAppButton() {
        const product = getProductDetails();

        if (!product.title) {
            return null;
        }

        const link = buildWhatsAppLink(productMessage(product.title, product.url, product.price));

        if (!link) {
            return null;
        }

        const button = document.createElement('a');

        button.className = 'kinas-whatsapp-product-btn';
        button.href = link;
        button.target = '_blank';
        button.rel = 'noopener noreferrer';
        button.setAttribute('aria-label', 'Enquire about this product on WhatsApp');

        button.innerHTML = WHATSAPP_ICON_SVG + '<span>Enquire on WhatsApp</span>';

        return button;
    }

    function addDetailPageWhatsAppButton() {
        if (isRestrictedPath()) {
            return;
        }

        if (document.querySelector('.kinas-whatsapp-product-btn')) {
            return;
        }

        const detailWrapper = document.querySelector(
            '.je-detail-wrap, .detail-container, .je-spec-panel, .detail-info'
        );

        if (!detailWrapper) {
            return;
        }

        const button = createProductWhatsAppButton();

        if (!button) {
            return;
        }

        // Preferred placement: inside the existing call-to-action row.
        const ctaRow = document.querySelector('.je-cta-row, .action-buttons');

        if (ctaRow) {
            ctaRow.appendChild(button);
            return;
        }

        // Secondary placement: directly below the product price.
        const priceElement = document.querySelector('.je-spec-price, .price');

        if (priceElement && priceElement.parentElement) {
            const wrapper = document.createElement('div');
            wrapper.className = 'kinas-whatsapp-detail-wrap';
            wrapper.appendChild(button);

            priceElement.insertAdjacentElement('afterend', wrapper);
            return;
        }

        // Final fallback: append to the detail wrapper.
        detailWrapper.appendChild(button);
    }

    // ------------------------------------------------------------
    // LISTING CARD WHATSAPP ICON
    // ------------------------------------------------------------

    function createCardWhatsAppButton(title, url, price) {
        const button = document.createElement('button');

        button.type = 'button';
        button.className = 'kinas-whatsapp-card-btn';
        button.setAttribute('aria-label', 'Enquire about this listing on WhatsApp');
        button.setAttribute('title', 'Enquire on WhatsApp');

        button.innerHTML = WHATSAPP_ICON_SVG;

        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            const link = buildWhatsAppLink(productMessage(title, url, price));

            if (link) {
                window.open(link, '_blank', 'noopener');
            }
        });

        return button;
    }

    function addListingCardWhatsAppButtons(root) {
        const scope = root instanceof Element ? root : document;

        const cards = scope.querySelectorAll(
            '.je-card, .listing-card, .product-card, .marketplace-item'
        );

        cards.forEach(function (card) {
            if (card.dataset.kinasWhatsappCard === '1') {
                return;
            }

            const titleElement = card.querySelector(
                '.je-card-title, .listing-title, .product-title, .item-title, h3, h4'
            );

            const imageElement = card.querySelector('img');

            const title = cleanText(
                titleElement
                    ? titleElement.textContent
                    : (imageElement && imageElement.alt ? imageElement.alt : document.title)
            );

            const url =
                card.href ||
                (card.querySelector('a[href]') ? card.querySelector('a[href]').href : '') ||
                window.location.href;

            const priceElement = card.querySelector(
                '.je-card-price, .price, .product-price, .item-price'
            );

            const price = cleanText(priceElement ? priceElement.textContent : '');

            const target =
                card.querySelector('.je-card-img, .card-image, .product-image, .item-image') ||
                card;

            if (window.getComputedStyle(target).position === 'static') {
                target.style.position = 'relative';
            }

            const button = createCardWhatsAppButton(title, url, price);

            target.appendChild(button);
            card.dataset.kinasWhatsappCard = '1';
        });
    }

    // ------------------------------------------------------------
    // INITIALISATION + DYNAMIC CONTENT SUPPORT
    // ------------------------------------------------------------

    function initialiseWhatsAppButtons() {
        const number = getWhatsAppNumber();

        if (!number) {
            return;
        }

        injectFallbackStyles();
        addGlobalWhatsAppButton();
        addDetailPageWhatsAppButton();
        addListingCardWhatsAppButtons(document);
        observeDynamicContent();
    }

    function observeDynamicContent() {
        if (!window.MutationObserver || !document.body) {
            return;
        }

        let debounceTimer = null;

        const observer = new MutationObserver(function () {
            if (debounceTimer) {
                return;
            }

            debounceTimer = window.setTimeout(function () {
                debounceTimer = null;

                addGlobalWhatsAppButton();
                addDetailPageWhatsAppButton();
                addListingCardWhatsAppButtons(document);
            }, 250);
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiseWhatsAppButtons);
    } else {
        initialiseWhatsAppButtons();
    }

})();
