/**
 * Checkout Embed iFrame Script - Failsafe & Meta Ads Optimized
 * Author: Antigravity AI
 * Usage: Add this script to your landing page and add a container div:
 *        <div class="checkout-embed" data-slug="product-slug"></div>
 */

(function () {
    if (window.__CHECKOUT_EMBED_INITIALIZED__) return;
    window.__CHECKOUT_EMBED_INITIALIZED__ = true;

    function getParentCookie(name) {
        try {
            const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
            return match ? match[2] : null;
        } catch (e) {
            return null;
        }
    }

    function init() {
        const embeds = document.querySelectorAll('.checkout-embed');
        embeds.forEach(container => {
            if (container.dataset.loaded) return;
            container.dataset.loaded = "true";

            const slug = container.getAttribute('data-slug');
            const bump = container.getAttribute('data-bump') || container.getAttribute('data-bump-id');
            const theme = container.getAttribute('data-theme') || '';
            const style = container.getAttribute('data-style') || '';
            
            // Detect Base URL dynamically (supports root domain as well as subfolders)
            let baseUrl = '';
            const scripts = document.getElementsByTagName('script');
            for (let i = 0; i < scripts.length; i++) {
                if (scripts[i].src && scripts[i].src.indexOf('checkout-embed.js') !== -1) {
                    const src = scripts[i].src;
                    const idx = src.indexOf('/js/checkout-embed.js');
                    if (idx !== -1) {
                        baseUrl = src.substring(0, idx);
                    } else {
                        try {
                            baseUrl = new URL(src).origin;
                        } catch (e) {}
                    }
                    break;
                }
            }
            if (!baseUrl) {
                baseUrl = window.location.origin;
            }

            const url = new URL((baseUrl || window.location.origin) + '/checkout.php');
            if (slug) url.searchParams.set('slug', slug);
            if (bump) url.searchParams.set('bump', bump);
            if (theme) url.searchParams.set('theme', theme);
            if (style) url.searchParams.set('style', style);
            url.searchParams.set('embed', 'true');
            
            // Forward parent window URL parameters (like UTMs, fbclid, etc.)
            try {
                const parentParams = new URLSearchParams(window.location.search);
                parentParams.forEach((value, key) => {
                    url.searchParams.set(key, value);
                });
            } catch (e) {}

            // Forward current parent URL for Meta CAPI / S2S attribution
            if (!url.searchParams.has('parent_url')) {
                try {
                    url.searchParams.set('parent_url', window.location.href);
                } catch (e) {}
            }

            // Forward first-party Meta cookies (_fbp, _fbc) from parent window
            const fbp = getParentCookie('_fbp');
            const fbc = getParentCookie('_fbc');
            if (fbp && !url.searchParams.has('parent_fbp')) url.searchParams.set('parent_fbp', fbp);
            if (fbc && !url.searchParams.has('parent_fbc')) url.searchParams.set('parent_fbc', fbc);

            if (document.referrer && !url.searchParams.has('parent_referrer')) {
                try {
                    url.searchParams.set('parent_referrer', encodeURIComponent(document.referrer));
                } catch (e) {}
            }

            // Create iframe
            const iframe = document.createElement('iframe');
            iframe.src = url.toString();
            iframe.className = 'checkout-embed-iframe';
            iframe.setAttribute('allow', 'clipboard-write; clipboard-read; payment; accelerometer; gyroscope; camera');
            iframe.setAttribute('scrolling', 'no');
            iframe.style.width = '100%';
            iframe.style.display = 'block';
            iframe.style.border = 'none';
            iframe.style.overflow = 'hidden';
            iframe.style.height = '500px'; // Initial fallback height
            iframe.style.transition = 'height 0.15s ease-out';
            iframe.style.background = 'transparent';
            
            container.appendChild(iframe);
        });

        // Listen for messages from the iframe (resize, scroll-top, etc.)
        window.addEventListener('message', function (event) {
            if (!event.data) return;

            // Height resize
            if (event.data.type === 'checkout-resize') {
                const iframes = document.querySelectorAll('.checkout-embed-iframe');
                for (let i = 0; i < iframes.length; i++) {
                    if (iframes[i].contentWindow === event.source) {
                        const newHeight = parseInt(event.data.height, 10);
                        if (!isNaN(newHeight) && newHeight > 0) {
                            iframes[i].style.height = newHeight + 'px';
                        }
                        break;
                    }
                }
            }

            // Scroll checkout container into view on step transitions (e.g. PIX generated)
            if (event.data.type === 'checkout-scroll-top') {
                const iframes = document.querySelectorAll('.checkout-embed-iframe');
                for (let i = 0; i < iframes.length; i++) {
                    if (iframes[i].contentWindow === event.source) {
                        try {
                            iframes[i].scrollIntoView({ behavior: 'smooth', block: 'start' });
                        } catch (e) {}
                        break;
                    }
                }
            }
        });
    }

    // Expose init globally for dynamic page builders / SPAs
    window.initCheckoutEmbed = init;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
