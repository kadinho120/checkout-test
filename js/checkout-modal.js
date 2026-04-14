/**
 * Checkout Modal iFrame Script
 * Author: Antigravity AI
 * Usage: Add this script to your landing page and add the attribute 'data-checkout-modal' to your buttons.
 */

(function () {
    const STYLES = `
        .checkout-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(5px);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        .checkout-modal-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        .checkout-modal-container {
            width: 95%;
            max-width: 1100px;
            height: 90vh;
            background: transparent;
            border-radius: 16px;
            position: relative;
            overflow: hidden;
            transform: translateY(20px);
            transition: transform 0.3s ease;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .checkout-modal-overlay.active .checkout-modal-container {
            transform: translateY(0);
        }
        .checkout-modal-iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        .checkout-modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 32px;
            height: 32px;
            background: #1e293b;
            color: #fff;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            z-index: 10;
            transition: transform 0.2s ease;
        }
        .checkout-modal-close:hover {
            transform: scale(1.1);
            background: #ef4444;
        }
        .checkout-modal-loader {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            color: #64748b;
        }
        .checkout-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            animation: checkout-spin 1s linear infinite;
        }
        @keyframes checkout-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @media (max-width: 768px) {
            .checkout-modal-container {
                width: 100%;
                height: 100%;
                border-radius: 0;
            }
        }
    `;

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
                <button class="checkout-modal-close" title="Fechar">&times;</button>
                <div class="checkout-modal-loader">
                    <div class="checkout-spinner"></div>
                    <span style="font-family: sans-serif; font-size: 14px; font-weight: 600;">Carregando Checkout Seguro...</span>
                </div>
                <iframe class="checkout-modal-iframe" src="about:blank"></iframe>
            </div>
        `;
        document.body.appendChild(overlay);

        const iframe = overlay.querySelector('.checkout-modal-iframe');
        const loader = overlay.querySelector('.checkout-modal-loader');
        const closeBtn = overlay.querySelector('.checkout-modal-close');

        function openModal(url) {
            // Add modal=true parameter
            const separator = url.indexOf('?') > -1 ? '&' : '?';
            const modalUrl = url + separator + 'modal=true';

            iframe.src = modalUrl;
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
            loader.style.display = 'flex';
        }

        function closeModal() {
            overlay.classList.remove('active');
            document.body.style.overflow = '';
            setTimeout(() => {
                iframe.src = 'about:blank';
            }, 300);
        }

        iframe.onload = function () {
            loader.style.display = 'none';
        };

        closeBtn.onclick = closeModal;
        overlay.onclick = function (e) {
            if (e.target === overlay) closeModal();
        };

        // Esc key to close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && overlay.classList.contains('active')) closeModal();
        });

        // Intercept clicks
        document.addEventListener('click', function (e) {
            const target = e.target.closest('[data-checkout-modal]');
            if (target) {
                e.preventDefault();
                const url = target.getAttribute('href') || target.getAttribute('data-url');
                if (url) openModal(url);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
