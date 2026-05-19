/**
 * Checkout Embed iFrame Script
 * Author: Antigravity AI
 * Usage: Add this script to your landing page and add a container div:
 *        <div class="checkout-embed" data-slug="product-slug"></div>
 */

(function () {
    function init() {
        const embeds = document.querySelectorAll('.checkout-embed');
        embeds.forEach(container => {
            if (container.dataset.loaded) return;
            container.dataset.loaded = "true";

            const slug = container.getAttribute('data-slug');
            const theme = container.getAttribute('data-theme') || '';
            const style = container.getAttribute('data-style') || '';
            
            // Get host URL from the script tag source to make it dynamic
            let scriptSrc = '';
            const scripts = document.getElementsByTagName('script');
            for (let i = 0; i < scripts.length; i++) {
                if (scripts[i].src && scripts[i].src.indexOf('checkout-embed.js') !== -1) {
                    scriptSrc = scripts[i].src;
                    break;
                }
            }
            const hostUrl = scriptSrc ? new URL(scriptSrc).origin : window.location.origin;

            const url = new URL(hostUrl + '/checkout.php');
            if (slug) url.searchParams.set('slug', slug);
            if (theme) url.searchParams.set('theme', theme);
            if (style) url.searchParams.set('style', style);
            url.searchParams.set('embed', 'true');
            
            // Forward parent window URL parameters (like UTMs, fbclid, etc.)
            const parentParams = new URLSearchParams(window.location.search);
            parentParams.forEach((value, key) => {
                url.searchParams.set(key, value);
            });

            // Create iframe
            const iframe = document.createElement('iframe');
            iframe.src = url.toString();
            iframe.className = 'checkout-embed-iframe';
            iframe.setAttribute('allow', 'clipboard-write');
            iframe.style.width = '100%';
            iframe.style.border = 'none';
            iframe.style.overflow = 'hidden';
            iframe.style.height = '450px'; // Initial sensible fallback height
            iframe.style.transition = 'height 0.15s ease-out';
            iframe.style.background = 'transparent';
            
            container.appendChild(iframe);
        });

        // Listen for height updates from the iframe
        window.addEventListener('message', function (event) {
            if (event.data && event.data.type === 'checkout-resize') {
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
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
