/**
 * Checkout Modal iFrame Script - Failsafe & Meta Ads Optimized
 * Author: Antigravity AI
 * Usage: Add this script to your landing page and add 'data-checkout-modal' to your buttons.
 */

(function () {
    // Singleton guard to prevent duplicate initialization
    if (window.__CHECKOUT_MODAL_INITIALIZED__) return;
    window.__CHECKOUT_MODAL_INITIALIZED__ = true;

    // Detect Host URL from script source or current origin
    let scriptHost = '';
    const scripts = document.getElementsByTagName('script');
    for (let i = 0; i < scripts.length; i++) {
        if (scripts[i].src && scripts[i].src.indexOf('checkout-modal.js') !== -1) {
            try {
                scriptHost = new URL(scripts[i].src).origin;
            } catch (e) {}
            break;
        }
    }
    if (!scriptHost) {
        scriptHost = window.location.origin;
    }

    const STYLES = `
        .checkout-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100vw;
            height: 100vh;
            height: 100dvh;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            z-index: 2147483647;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
            pointer-events: none;
            box-sizing: border-box;
            padding: 0;
            margin: 0;
        }
        .checkout-modal-overlay.active {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }
        .checkout-modal-container {
            width: 95%;
            max-width: 1100px;
            height: 90vh;
            max-height: 90dvh;
            background: #0f172a;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            position: relative;
            overflow: hidden;
            transform: translateY(20px) scale(0.98);
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            display: flex;
            flex-direction: column;
        }
        .checkout-modal-overlay.active .checkout-modal-container {
            transform: translateY(0) scale(1);
        }
        .checkout-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            background: #0f172a;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            flex-shrink: 0;
            z-index: 20;
            box-sizing: border-box;
        }
        .checkout-modal-badge {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 12px;
            font-weight: 600;
            color: #94a3b8;
            letter-spacing: 0.02em;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            user-select: none;
        }
        .checkout-modal-badge svg {
            color: #10b981;
            flex-shrink: 0;
        }
        .checkout-modal-close {
            width: 30px;
            height: 30px;
            background: rgba(255, 255, 255, 0.06);
            color: #94a3b8;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            line-height: 1;
            transition: all 0.2s ease;
            -webkit-tap-highlight-color: transparent;
            user-select: none;
            box-sizing: border-box;
        }
        .checkout-modal-close:hover {
            transform: scale(1.05);
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.35);
        }
        .checkout-modal-close:active {
            transform: scale(0.95);
        }
        .checkout-modal-close svg {
            pointer-events: none;
            display: block;
        }
        .checkout-modal-iframe {
            width: 100% !important;
            height: 100% !important;
            border: none !important;
            flex: 1;
            background: transparent;
            -webkit-overflow-scrolling: touch;
            display: block;
        }
        .checkout-modal-loader {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 14px;
            color: #94a3b8;
            z-index: 10;
            text-align: center;
            padding: 24px;
            width: 85%;
            max-width: 380px;
            background: rgba(15, 23, 42, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .checkout-spinner {
            width: 42px;
            height: 42px;
            border: 4px solid rgba(255, 255, 255, 0.15);
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            animation: checkout-spin 0.9s linear infinite;
        }
        .checkout-modal-fallback-btn {
            display: none;
            margin-top: 6px;
            padding: 10px 18px;
            background: #2563eb;
            color: #ffffff !important;
            font-size: 13px;
            font-weight: 700;
            border-radius: 8px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s ease;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
        }
        .checkout-modal-fallback-btn:hover {
            background: #1d4ed8;
        }
        @keyframes checkout-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @media (max-width: 768px) {
            .checkout-modal-container {
                width: 100%;
                height: 100%;
                height: 100dvh;
                max-height: 100dvh;
                border-radius: 0;
                border: none;
                margin: 0;
                padding-bottom: env(safe-area-inset-bottom, 0);
            }
            .checkout-modal-header {
                padding: 10px 14px;
                padding-top: max(10px, env(safe-area-inset-top, 10px));
            }
            .checkout-modal-close {
                width: 32px;
                height: 32px;
            }
        }
    `;

    // Cookie helper to capture first-party Meta tracking cookies from Landing Page
    function getParentCookie(name) {
        try {
            const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
            return match ? match[2] : null;
        } catch (e) {
            return null;
        }
    }

    // Resolves and formats target checkout URL with all UTMs, Meta parameters, and parent cookies
    function resolveCheckoutUrl(element) {
        const slug = element.getAttribute('data-slug');
        const dataUrl = element.getAttribute('data-url');
        const href = element.getAttribute('href');

        let rawUrl = '';
        if (dataUrl && dataUrl !== '#' && !dataUrl.startsWith('javascript:')) {
            rawUrl = dataUrl;
        } else if (href && href !== '#' && !href.startsWith('javascript:') && !href.startsWith('#')) {
            rawUrl = href;
        } else if (slug) {
            rawUrl = scriptHost + '/checkout.php?slug=' + encodeURIComponent(slug);
        } else {
            rawUrl = scriptHost + '/checkout.php';
        }

        let targetUrl;
        try {
            targetUrl = new URL(rawUrl, window.location.href);
        } catch (e) {
            targetUrl = new URL(scriptHost + '/checkout.php');
            if (slug) targetUrl.searchParams.set('slug', slug);
        }

        // Always set modal=true
        targetUrl.searchParams.set('modal', 'true');

        // Forward all URL search params from the parent page (UTMs, fbclid, gclid, ttclid, src, sck, etc.)
        try {
            const parentParams = new URLSearchParams(window.location.search);
            parentParams.forEach((val, key) => {
                targetUrl.searchParams.set(key, val);
            });
        } catch (e) {}

        // Forward first-party Meta cookies (_fbp, _fbc) from parent window to overcome third-party cookie blocking
        const fbp = getParentCookie('_fbp');
        const fbc = getParentCookie('_fbc');
        if (fbp && !targetUrl.searchParams.has('parent_fbp')) targetUrl.searchParams.set('parent_fbp', fbp);
        if (fbc && !targetUrl.searchParams.has('parent_fbc')) targetUrl.searchParams.set('parent_fbc', fbc);

        // Forward document referrer if available
        if (document.referrer && !targetUrl.searchParams.has('parent_referrer')) {
            try {
                targetUrl.searchParams.set('parent_referrer', encodeURIComponent(document.referrer));
            } catch (e) {}
        }

        return targetUrl.toString();
    }

    function init() {
        // Inject styles
        const styleTag = document.createElement('style');
        styleTag.textContent = STYLES;
        document.head.appendChild(styleTag);

        // Create Modal Structure
        const overlay = document.createElement('div');
        overlay.className = 'checkout-modal-overlay';
        overlay.innerHTML = `
            <div class="checkout-modal-container">
                <div class="checkout-modal-header">
                    <div class="checkout-modal-badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <span>Ambiente Seguro</span>
                    </div>
                    <button class="checkout-modal-close" title="Fechar" aria-label="Fechar checkout">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <div class="checkout-modal-loader">
                    <div class="checkout-spinner"></div>
                    <span style="font-size: 14px; font-weight: 600; color: #f1f5f9;">Carregando Checkout Seguro...</span>
                    <button class="checkout-modal-fallback-btn">Demorando? Clique para abrir</button>
                </div>
                <iframe class="checkout-modal-iframe" src="about:blank" allow="clipboard-write; clipboard-read; payment; accelerometer; gyroscope; camera"></iframe>
            </div>
        `;
        document.body.appendChild(overlay);

        const iframe = overlay.querySelector('.checkout-modal-iframe');
        const loader = overlay.querySelector('.checkout-modal-loader');
        const fallbackBtn = overlay.querySelector('.checkout-modal-fallback-btn');
        const closeBtn = overlay.querySelector('.checkout-modal-close');

        let canClose = true;
        let downsellPending = false;
        let downsellTimeout = null;
        let loadTimeout = null;
        let historyPushed = false;
        let currentCheckoutUrl = '';

        function lockScroll() {
            document.documentElement.style.overflow = 'hidden';
            document.body.style.overflow = 'hidden';
        }

        function unlockScroll() {
            document.documentElement.style.overflow = '';
            document.body.style.overflow = '';
        }

        function openModal(url) {
            canClose = true;
            downsellPending = false;
            currentCheckoutUrl = url;
            closeBtn.style.display = 'flex';
            
            // Push history state so the Android/iOS Back gesture closes modal without leaving the landing page
            if (!historyPushed && window.history && window.history.pushState) {
                try {
                    window.history.pushState({ checkoutModalOpen: true }, '', window.location.href);
                    historyPushed = true;
                } catch (e) {}
            }

            // Show loader and set iframe src
            loader.style.display = 'flex';
            fallbackBtn.style.display = 'none';
            iframe.src = url;
            overlay.classList.add('active');
            lockScroll();

            // Safety timeout: if iframe takes > 6s, show direct fallback button
            if (loadTimeout) clearTimeout(loadTimeout);
            loadTimeout = setTimeout(() => {
                if (loader.style.display !== 'none') {
                    fallbackBtn.style.display = 'inline-block';
                    fallbackBtn.onclick = function (e) {
                        e.preventDefault();
                        window.location.href = currentCheckoutUrl;
                    };
                }
            }, 6000);
        }

        function closeModal(force = false) {
            if (!canClose && !force) return;
            if (loadTimeout) clearTimeout(loadTimeout);
            if (downsellTimeout) clearTimeout(downsellTimeout);

            downsellPending = false;
            overlay.classList.remove('active');
            unlockScroll();

            setTimeout(() => {
                iframe.src = 'about:blank';
                loader.style.display = 'flex';
                fallbackBtn.style.display = 'none';
            }, 300);
        }

        function handleCloseRequest() {
            // If the checkout is still loading or iframe window is not accessible, close immediately
            if (loader.style.display !== 'none' || !iframe.contentWindow) {
                closeModal(true);
                return;
            }

            // If user clicked close a second time while waiting for downsell, force close
            if (downsellPending) {
                closeModal(true);
                return;
            }

            downsellPending = true;
            try {
                iframe.contentWindow.postMessage('trigger-downsell', '*');
            } catch (e) {
                closeModal(true);
                return;
            }

            // Safety fallback: if iframe doesn't reply within 800ms, close anyway to never trap the user
            downsellTimeout = setTimeout(() => {
                if (downsellPending) {
                    closeModal(true);
                }
            }, 800);
        }

        // Listen for messages from the iframe (e.g. close request after downsell or configuration)
        window.addEventListener('message', function (event) {
            if (event.data === 'close-checkout-modal') {
                if (downsellTimeout) clearTimeout(downsellTimeout);
                downsellPending = false;
                closeModal(true);
            } else if (event.data === 'downsell-opened') {
                // Downsell foi aberto com sucesso no checkout: cancela o fechamento
                if (downsellTimeout) clearTimeout(downsellTimeout);
                downsellPending = false;
            } else if (event.data && event.data.type === 'checkout-config') {
                if (event.data.showCloseButton === false) {
                    closeBtn.style.display = 'none';
                    canClose = false;
                } else {
                    closeBtn.style.display = 'flex';
                    canClose = true;
                }
            }
        });

        iframe.onload = function () {
            if (iframe.src && iframe.src !== 'about:blank') {
                loader.style.display = 'none';
                if (loadTimeout) clearTimeout(loadTimeout);
            }
        };

        iframe.onerror = function () {
            // If iframe fails to load (network drop, blocked), show direct fallback button immediately
            loader.style.display = 'flex';
            fallbackBtn.style.display = 'inline-block';
            fallbackBtn.onclick = function (e) {
                e.preventDefault();
                window.location.href = currentCheckoutUrl;
            };
        };

        closeBtn.onclick = function (e) {
            e.preventDefault();
            handleCloseRequest();
        };

        overlay.onclick = function (e) {
            if (e.target === overlay) {
                handleCloseRequest();
            }
        };

        // Esc key to close
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('active')) {
                handleCloseRequest();
            }
        });

        // Mobile Back gesture / Popstate listener
        window.addEventListener('popstate', function (e) {
            if (overlay.classList.contains('active')) {
                historyPushed = false;
                closeModal(true);
            }
        });

        // Intercept button clicks using capture phase to prevent LP scripts from stopping propagation
        document.addEventListener('click', function (e) {
            const target = e.target.closest('[data-checkout-modal], .checkout-modal-btn');
            if (target) {
                e.preventDefault();
                const url = resolveCheckoutUrl(target);
                if (url) openModal(url);
            }
        }, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
