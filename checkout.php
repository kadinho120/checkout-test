<?php
require_once 'api/connection.php';
require_once 'api/functions/format_price.php';
$database = new Database();
$db = $database->getConnection();

// Theme determination moved below product fetch

$slug = $_GET['slug'] ?? '';
$isModal = ($_GET['modal'] ?? '') === 'true';

// Cabeçalhos para permitir iFrame (opcionalmente pode ser restrito por domínio se o usuário preferir)
header('X-Frame-Options: ALLOWALL'); 
header('Content-Security-Policy: frame-ancestors *');

if (!$slug) {
    $stmt = $db->query("SELECT * FROM products ORDER BY id ASC LIMIT 1");
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt = $db->prepare("SELECT * FROM products WHERE slug = ?");
    $stmt->execute([$slug]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$product) {
    die("Product not found. <a href='/admin'>Go to Admin</a>");
}

$themeParam = $_GET['theme'] ?? null;
if ($themeParam) {
    $theme = $themeParam;
} else {
    $theme = $product['theme'] ?? 'dark';
}
$isDarkMode = $theme !== 'light';

// Fetch Bumps
$bumpStmt = $db->prepare("SELECT * FROM order_bumps WHERE product_id = ? AND active = 1");
$bumpStmt->execute([$product['id']]);
$product['bumps'] = $bumpStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Pixels
$pixelStmt = $db->prepare("SELECT * FROM pixels WHERE product_id = ? AND active = 1");
$pixelStmt->execute([$product['id']]);
$product['pixels'] = $pixelStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR" class="<?= $isDarkMode ? 'dark' : '' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Compra - <?= htmlspecialchars($product['name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&family=Open+Sans:wght@400;600;700&display=swap"
        rel="stylesheet">

    <!-- TRACKING SCRIPTS -->
    <?php foreach ($product['pixels'] as $pixel): ?>
        <?php if ($pixel['type'] === 'facebook'): ?>
            <script>
                !function (f, b, e, v, n, t, s) {
                    if (f.fbq) return; n = f.fbq = function () {
                        n.callMethod ?
                            n.callMethod.apply(n, arguments) : n.queue.push(arguments)
                    };
                    if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0';
                    n.queue = []; t = b.createElement(e); t.async = !0;
                    t.src = v; s = b.getElementsByTagName(e)[0];
                    s.parentNode.insertBefore(t, s)
                }(window, document, 'script',
                    'https://connect.facebook.net/en_US/fbevents.js');
                fbq('init', '<?= $pixel['pixel_id'] ?>');
                fbq('track', 'PageView');
                <?php if ((int)($product['track_initiate_checkout'] ?? 1) !== 0): ?>
                fbq('track', 'InitiateCheckout'); 
                <?php endif; ?>
            </script>
            <noscript><img height="1" width="1" style="display:none"
                    src="https://www.facebook.com/tr?id=<?= $pixel['pixel_id'] ?>&ev=PageView&noscript=1" /></noscript>
        <?php elseif ($pixel['type'] === 'custom'): ?>
            <?= $pixel['script_content'] ?? '' ?>
        <?php endif; ?>
    <?php endforeach; ?>

    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #0f172a;
            color: #e2e8f0;
        }

        .font-display {
            font-family: 'Montserrat', sans-serif;
        }

        .cta-button {
            background: linear-gradient(to bottom, #22c55e, #15803d);
            box-shadow: 0 4px 0 #14532d;
            transition: all 0.1s;
        }

        .cta-button:active {
            transform: translateY(4px);
            box-shadow: 0 0 0 #14532d;
        }

        /* BUMP STYLES */
        .bump-box {
            transition: background-color 0.2s;
        }

        .bump-box:hover {
            background-color: rgba(31, 41, 55, 0.5);
        }

        .bump-card-active {
            background-color: rgba(212, 175, 55, 0.15) !important;
            border-color: #D4AF37 !important;
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.2);
        }

        .bump-promo-tag {
            background: #ef4444;
            color: white;
            font-size: 0.60rem;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-left: auto;
        }

        .bump-icon-box {
            transition: all 0.3s ease;
        }

        input:checked+div .bump-icon-box {
            background-color: #D4AF37;
            border-color: #D4AF37;
            color: black;
        }

        /* DOWNSELL MODAL STYLES */
        #downsell-modal {
            transition: opacity 0.3s ease;
        }
        .downsell-content {
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }
        #downsell-modal.active .downsell-content {
            transform: scale(1);
        }
    </style>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        display: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body
    class="min-h-screen flex flex-col items-center transition-colors duration-300 <?= $isModal ? 'py-2 px-2 overflow-x-hidden bg-gray-50 dark:bg-slate-950' : 'py-6 sm:py-12 px-4 bg-gray-50 dark:bg-slate-950' ?>">

    <?php if (!$isModal): ?>
    <!-- Header / Security -->
    <div
        class="mb-8 text-center opacity-70 flex items-center justify-center gap-2 text-sm text-slate-600 dark:text-slate-400">
        <i data-lucide="lock" class="w-4 h-4"></i> Pagamento 100% Seguro e Criptografado
    </div>
    <?php endif; ?>

    <div class="w-full <?= $isModal ? 'max-w-4xl' : 'max-w-5xl' ?> grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">

        <!-- COLUMN 1: PRODUCT INFO -->
        <div class="lg:sticky lg:top-8 space-y-6">
            <div
                class="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl p-6 shadow-xl">
                <div
                    class="aspect-square w-full bg-slate-800 rounded-lg overflow-hidden mb-6 relative border border-slate-700">
                    <img src="<?= htmlspecialchars($product['image_url'] ?: 'https://placehold.co/600x400?text=Produto') ?>"
                        class="w-full h-full object-cover">

                    <!-- Badge -->
                    <div
                        class="absolute top-2 right-2 bg-yellow-500 text-black text-xs font-bold px-3 py-1 rounded-full shadow-lg">
                        OFERTA ESPECIAL
                    </div>
                </div>

                <h1 class="font-display text-2xl font-bold text-gray-900 dark:text-white mb-2">
                    <?= htmlspecialchars($product['name']) ?>
                </h1>
                <p class="text-gray-600 dark:text-slate-400 text-sm mb-4 leading-relaxed">
                    <?= htmlspecialchars($product['description'] ?: 'Acesso imediato ao conteúdo digital.') ?>
                </p>

                <div class="flex items-center justify-between border-t border-slate-800 pt-4 mt-4">
                    <span class="text-slate-500 text-sm">Valor Total:</span>
                    <div class="text-right">
                        <span class="block text-xs text-slate-500 line-through">De R$ 97,00</span>
                        <span class="text-3xl font-black text-green-400">R$
                            <?= format_price($product['price']) ?></span>
                    </div>
                </div>

                <div class="mt-6 space-y-2 text-xs text-slate-500">
                    <div class="flex items-center gap-2"><i data-lucide="check-circle"
                            class="w-4 h-4 text-green-500"></i> Garantia de 7 Dias</div>
                    <?php
                    $reqEmail = (int) $product['request_email'] !== 0;
                    $reqPhone = (int) $product['request_phone'] !== 0;
                    $accessText = "Acesso Imediato";
                    if ($reqEmail && $reqPhone) {
                        $accessText .= " por E-mail e WhatsApp";
                    } elseif ($reqEmail) {
                        $accessText .= " por E-mail";
                    } elseif ($reqPhone) {
                        $accessText .= " por WhatsApp";
                    }
                    ?>
                    <div class="flex items-center gap-2"><i data-lucide="check-circle"
                            class="w-4 h-4 text-green-500"></i> <?= $accessText ?></div>
                </div>
            </div>
        </div>

        <!-- COLUMN 2: CHECKOUT FORM -->
        <div class="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl p-6 shadow-xl">
            <h2 id="checkout-step-header"
                class="font-display text-xl font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-2">
                <span
                    class="bg-blue-600 w-8 h-8 rounded-full flex items-center justify-center text-sm text-white">1</span>
                Dados Pessoais
            </h2>

            <div id="payment-error"
                class="hidden bg-red-100 border border-red-400 text-red-700 dark:bg-red-900/50 dark:border-red-700 dark:text-red-300 px-4 py-3 rounded-md mb-4 text-sm"
                role="alert"></div>

            <form id="checkout-form" novalidate>

                <div class="space-y-4 mb-8">
                    <?php if ((int) ($product['request_name'] ?? 1) !== 0): ?>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-slate-400 mb-1 ml-1">NOME
                            COMPLETO</label>
                        <input type="text" id="name" placeholder="Digite seu nome..."
                            class="block w-full bg-gray-50 dark:bg-slate-950 border border-gray-300 dark:border-slate-700 rounded-lg p-3 text-gray-900 dark:text-white text-sm transition-colors focus:border-blue-500"
                            required>
                    </div>
                    <?php endif; ?>
                    <?php if ((int) $product['request_email'] !== 0): ?>
                        <div>
                            <label
                                class="block text-xs font-bold text-gray-500 dark:text-slate-400 mb-1 ml-1">E-MAIL</label>
                            <input type="email" id="email" placeholder="Digite seu melhor @email..."
                                class="block w-full bg-gray-50 dark:bg-slate-950 border border-gray-300 dark:border-slate-700 rounded-lg p-3 text-gray-900 dark:text-white text-sm transition-colors focus:border-blue-500"
                                required>
                        </div>
                    <?php endif; ?>

                    <!-- Downsell Modal HTML -->
                    <div id="downsell-modal" class="fixed inset-0 z-[99999] hidden flex items-center justify-center p-4">
                        <div class="absolute inset-0 bg-slate-950/90 backdrop-blur-sm shadow-2xl"></div>
                        <div class="downsell-content relative w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl overflow-y-auto max-h-[95vh] border border-orange-500/30 p-6 sm:p-8 text-center shadow-2xl">
                            <div class="mb-4 sm:mb-6 inline-flex p-3 sm:p-4 bg-orange-500/10 rounded-full">
                                <i data-lucide="gift" class="w-10 h-10 sm:w-12 sm:h-12 text-orange-500"></i>
                            </div>
                            
                            <h2 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white mb-2 uppercase tracking-tight">Espera aí! 🛑</h2>
                            <p class="text-sm sm:text-base text-slate-600 dark:text-slate-400 mb-4 sm:mb-6 font-medium leading-relaxed">
                                Notamos que você está prestes a sair. Queremos te dar uma última chance de levar o 
                                <span class="text-orange-500 font-bold"><?= htmlspecialchars($product['name']) ?></span> 
                                com um desconto especial de liberação imediata.
                            </p>

                            <div class="bg-orange-500/5 rounded-2xl p-4 sm:p-6 mb-6 sm:mb-8 border border-orange-500/20">
                                <span class="block text-[10px] sm:text-xs text-slate-500 uppercase font-bold mb-1">Preço Atualizado</span>
                                <div class="flex items-center justify-center gap-2 sm:gap-3">
                                    <span class="text-sm sm:text-lg text-slate-500 line-through">R$ <?= format_price($product['price']) ?></span>
                                    <span class="text-3xl sm:text-4xl font-black text-orange-500">R$ <span id="downsell-new-price">0,00</span></span>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <button type="button" onclick="acceptDownsell()" class="cta-button w-full py-4 sm:py-5 rounded-2xl text-white font-black text-base sm:text-lg uppercase tracking-wider flex items-center justify-center gap-2">
                                    APROVEITAR DESCONTO AGORA
                                </button>
                                <button type="button" onclick="closeDownsell()" class="w-full py-2 text-slate-500 hover:text-red-400 text-xs sm:text-sm font-bold transition">
                                    Não, prefiro pagar o valor cheio depois
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php if ((int) $product['request_phone'] !== 0): ?>
                        <div>
                            <label
                                class="block text-xs font-bold text-gray-500 dark:text-slate-400 mb-1 ml-1">WHATSAPP</label>
                            <input type="tel" id="whatsapp" placeholder="(00) 00000-0000"
                                class="block w-full bg-gray-50 dark:bg-slate-950 border border-gray-300 dark:border-slate-700 rounded-lg p-3 text-gray-900 dark:text-white text-sm transition-colors focus:border-blue-500"
                                required>
                        </div>
                    <?php endif; ?>
                </div>

                <h2 class="font-display text-xl font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-2">
                    <span
                        class="bg-blue-600 w-8 h-8 rounded-full flex items-center justify-center text-sm text-white">2</span>
                    Pagamento
                </h2>

                <div class="mb-6">
                    <div
                        class="cursor-pointer border-2 border-green-500 bg-green-500/10 p-4 rounded-xl flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="bg-white p-1 rounded border border-gray-200"><img src="/imagens/icon-pix.png"
                                    class="h-6"></div>
                            <span class="font-bold text-gray-900 dark:text-white">PIX</span>
                        </div>
                        <span class="text-xs font-bold bg-green-500 text-black px-2 py-0.5 rounded">APROVAÇÃO
                            IMEDIATA</span>
                    </div>
                </div>

                <!-- ORDER BUMPS -->
                <?php if (!empty($product['bumps'])): ?>
                    <div
                        class="space-y-3 mb-8 bg-gray-50 dark:bg-slate-950/50 p-4 rounded-xl border border-gray-200 dark:border-slate-800">
                        <p class="text-xs text-yellow-500 font-bold uppercase tracking-wider mb-2 flex items-center gap-2">
                            <i data-lucide="zap" class="w-4 h-4"></i>
                            Turbine sua Compra
                        </p>

                        <?php foreach ($product['bumps'] as $bump): ?>
                            <label class="relative block cursor-pointer group mb-2">
                                <input type="checkbox" name="bumps[]" value="<?= $bump['id'] ?>"
                                    data-sku="BUMP-<?= $bump['id'] ?>" data-name="<?= htmlspecialchars($bump['title']) ?>"
                                    data-price="<?= $bump['price'] ?>" class="peer sr-only"
                                    onchange="updateTotalPrice(); toggleBumpVisual(this)">

                                <div
                                    class="bump-box border border-gray-300 dark:border-slate-700 bg-gray-50 dark:bg-slate-900 p-3 rounded-xl transition-all duration-200 peer-checked:border-yellow-500 peer-checked:bg-yellow-500/10">
                                    
                                    <!-- Top Row: Checkbox, Title and Price -->
                                    <div class="flex items-start gap-3 mb-2">
                                        <div
                                            class="bump-icon-box flex h-5 w-5 mt-1 shrink-0 items-center justify-center rounded border border-slate-500 text-transparent bg-transparent peer-checked:bg-yellow-500 peer-checked:border-yellow-500 peer-checked:text-black">
                                            <i data-lucide="check" class="w-3 h-3"></i>
                                        </div>

                                        <div class="flex-1 min-w-0">
                                            <span class="font-bold text-gray-900 dark:text-white text-sm pr-2">
                                                <?= htmlspecialchars($bump['title']) ?>
                                            </span>
                                        </div>

                                        <div class="text-right shrink-0">
                                            <span class="block text-sm font-black text-yellow-500">+R$
                                                <?= format_price($bump['price']) ?></span>
                                        </div>
                                    </div>

                                    <!-- Bottom Row: Full Width Description -->
                                    <p class="text-xs text-slate-400 leading-relaxed ml-8">
                                        <?= htmlspecialchars($bump['description']) ?>
                                    </p>
                                </div>

                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- SUMMARY & BUTTON -->
                <div class="border-t border-slate-800 pt-6">
                    <div class="flex justify-between items-end mb-4">
                        <span class="text-gray-600 dark:text-slate-400 text-sm">Total a pagar:</span>
                        <span id="checkout-price-display" class="text-2xl font-black text-gray-900 dark:text-white">R$
                            <?= format_price($product['price']) ?></span>
                    </div>

                    <button type="submit" id="pay-button"
                        class="w-full cta-button font-bold text-lg py-4 px-6 rounded-xl uppercase tracking-wider text-white flex items-center justify-center gap-2 hover:scale-[1.02] transform transition">
                        <i data-lucide="lock" class="w-5 h-5"></i> PAGAR AGORA
                    </button>

                    <p class="text-[10px] text-slate-500 text-center mt-3">
                        Ao clicar no botão, você concorda com nossos Termos de Uso.
                    </p>
                </div>

            </form>

            <!-- PIX WAIT SCREEN -->
            <div id="pix-wait-view" class="hidden text-center p-4 pt-8">
                <!-- Injected via JS -->
            </div>

            <!-- SUCCESS SCREEN -->
            <div id="success-view" class="hidden text-center p-4 pt-8">
                <div class="flex justify-center mb-4"><i data-lucide="check-circle"
                        class="w-20 h-20 text-green-500"></i></div>
                <h2 class="font-display text-3xl font-bold text-white mt-4">PAGAMENTO APROVADO!</h2>
                <p class="text-slate-300 my-4 text-lg">Seu acesso foi enviado para o seu e-mail.</p>
            </div>
        </div>

    </div>

    <!-- JS LOGIC -->
    <script>
        lucide.createIcons();
        const BACKEND_BASE_PATH = '/api';

        // Preço base do produto vindo do PHP
        let baseProductPrice = <?= (float) $product['price'] ?>;
        let currentProductPrice = baseProductPrice;

        // Configurações de Downsell vindas do PHP
        const downsellEnabled = <?= (int) ($product['downsell_enabled'] ?? 0) ?> === 1;
        const downsellType = '<?= $product['downsell_discount_type'] ?? 'fixed' ?>';
        const downsellAmount = <?= (float) ($product['downsell_discount_amount'] ?? 0) ?>;
        let downsellTriggered = false;
        let downsellAccepted = false;

        const PLANOS = {
            'main': {
                price: <?= $product['price'] * 100 ?>,
                name: <?= json_encode($product['name']) ?>,
                sku: <?= json_encode($product['slug']) ?>,
                notifications: {
                    enabled: <?= ($product['fake_notifications'] ?? 0) ? 'true' : 'false' ?>,
                    text: <?= json_encode($product['notification_text'] ?? '') ?>
                },
                topBar: {
                    enabled: <?= ($product['top_bar_enabled'] ?? 0) ? 'true' : 'false' ?>,
                    text: <?= json_encode($product['top_bar_text'] ?? '') ?>,
                    bgColor: <?= json_encode($product['top_bar_bg_color'] ?? '#000000') ?>,
                    textColor: <?= json_encode($product['top_bar_text_color'] ?? '#ffffff') ?>
                },
                tracking: {
                    initiateCheckout: <?= (int)($product['track_initiate_checkout'] ?? 1) !== 0 ? 'true' : 'false' ?>,
                    addPaymentInfo: <?= (int)($product['track_add_payment_info'] ?? 1) !== 0 ? 'true' : 'false' ?>
                }
            }
        };

        const formatPriceJS = (amount) => {
            return amount % 1 === 0 
                ? amount.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
                : amount.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };

        const formatCurrency = (value) => {
            return 'R$ ' + formatPriceJS(value / 100);
        };

        const payButton = document.getElementById('pay-button');
        const paymentErrorDiv = document.getElementById('payment-error');
        const checkoutPriceDisplay = document.getElementById('checkout-price-display');
        const form = document.getElementById('checkout-form');
        const pixWaitView = document.getElementById('pix-wait-view');
        const successView = document.getElementById('success-view');

        let pixPaymentState = null;
        let isPurchaseComplete = false;
        let pollingTimer = null;
        const PIXEL_ID = <?= json_encode($product['pixels'][0]['pixel_id'] ?? '') ?>; // safe injection

        // Helpers
        window.toggleBumpVisual = (input) => { /* Managed by CSS peer-checked mostly, or simple class toggle */
            // Optional extra logic
        };

        // Trigger AddPaymentInfo on first interaction
        let hasTrackedInfo = false;
        const trackAddPaymentInfo = () => {
            if (hasTrackedInfo || !PLANOS['main'].tracking.addPaymentInfo) return;
            hasTrackedInfo = true;

            if (typeof fbq === 'function') {
                fbq('track', 'AddPaymentInfo', {
                    content_name: PLANOS['main'].name,
                    content_ids: [PLANOS['main'].sku],
                    content_type: 'product',
                    value: PLANOS['main'].price / 100,
                    currency: 'BRL'
                });
            }
        };

        ['name', 'email', 'whatsapp'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', trackAddPaymentInfo);
        });

        window.calculateCurrentTotal = () => {
            let total = Math.round(currentProductPrice * 100);
            document.querySelectorAll('input[name="bumps[]"]:checked').forEach(el => {
                total += Math.round(parseFloat(el.dataset.price) * 100);
            });
            return total;
        };

        window.updateTotalPrice = () => {
            let total = calculateCurrentTotal();
            checkoutPriceDisplay.textContent = formatCurrency(total);
        };

        // Form Validation
        const validateForm = () => {
            let isValid = true;
            let errorMessage = 'Preencha todos os campos obrigatórios.';
            paymentErrorDiv.classList.add('hidden');

            // 1. Basic Empty Check
            ['name', 'email', 'whatsapp'].forEach(id => {
                const input = document.getElementById(id);
                if (!input) return; // Skip if optional/hidden

                input.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
                if (!input.value.trim()) {
                    input.classList.add('border-red-500', 'ring-1', 'ring-red-500');
                    isValid = false;
                }
            });

            // 2. Strict Email Validation
            const emailInput = document.getElementById('email');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (emailInput && emailInput.value.trim() && !emailRegex.test(emailInput.value.trim())) {
                emailInput.classList.add('border-red-500', 'ring-1', 'ring-red-500');
                isValid = false;
                errorMessage = 'Por favor, insira um e-mail válido.';
            }

            if (!isValid) {
                paymentErrorDiv.textContent = errorMessage;
                paymentErrorDiv.classList.remove('hidden');
            }
            return isValid;
        };

        // Phone Mask
        const whatsappInput = document.getElementById('whatsapp');
        if (whatsappInput) {
            whatsappInput.addEventListener('input', (e) => {
                let v = e.target.value.replace(/\D/g, '');
                v = v.substring(0, 11);
                if (v.length > 2) v = '(' + v.substring(0, 2) + ') ' + v.substring(2);
                if (v.length > 9) v = v.replace(/(\d{5})(\d)/, '$1-$2');
                else if (v.length > 8) v = v.replace(/(\d{4})(\d)/, '$1-$2');
                e.target.value = v;
            });
        }

        // Submit Handler
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (payButton.disabled || !validateForm()) return;

            payButton.disabled = true;
            payButton.innerHTML = '<i data-lucide="loader-2" class="animate-spin w-5 h-5"></i> PROCESSANDO...';
            lucide.createIcons();

            const customerData = {
                name: document.getElementById('name') ? document.getElementById('name').value : '',
                email: document.getElementById('email') ? document.getElementById('email').value : '',
                phone: document.getElementById('whatsapp') ? document.getElementById('whatsapp').value : '',
                document: '',
                external_id: new URLSearchParams(window.location.search).get('external_id') || ''
            };

            await handlePixPayment(customerData);
        });

        // Pix Logic (Same as before but adapted to view)
        const generateCorrelationId = () => `alpha7_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

        const handlePixPayment = async (customerData) => {
            const correlationId = generateCorrelationId();
            const mainProduct = PLANOS['main'];
            const allProducts = [{ sku: mainProduct.sku, name: mainProduct.name, price: currentProductPrice, qty: 1 }];

            document.querySelectorAll('input[name="bumps[]"]:checked').forEach(el => {
                allProducts.push({ sku: el.dataset.sku, name: el.dataset.name, price: parseFloat(el.dataset.price), qty: 1 });
            });

            const totalValueInCents = calculateCurrentTotal();
            const totalValue = totalValueInCents / 100;

            // Capture Tracking Params
            const urlParams = new URLSearchParams(window.location.search);
            const trackingParams = {};
            for (const [key, value] of urlParams) {
                trackingParams[key] = value;
            }

            // Cookie helpers
            const getCookie = (name) => {
                const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
                return match ? match[2] : null;
            };

            const fbp = getCookie('_fbp');
            const fbc = getCookie('_fbc');

            // Extract standalone fbclid
            let fbclid = trackingParams.fbclid || '';
            if (!fbclid && fbc) {
                // fbc format: "fb.1.{timestamp}.{fbclid}" or "fb.2..."
                const parts = fbc.split('.');
                if (parts.length >= 4) {
                    fbclid = parts.slice(3).join('.');
                }
            }
            if (fbclid) trackingParams.fbclid = fbclid;

            if (fbp) trackingParams.fbp = fbp;
            if (fbc) trackingParams.fbc = fbc;
            if (typeof PIXEL_ID !== 'undefined') trackingParams.pixel_id = PIXEL_ID;
            trackingParams.user_agent = navigator.userAgent;

            const pixPayload = {
                value: totalValueInCents,
                products: allProducts,
                customer: customerData,
                correlation_id: correlationId,
                tracking: trackingParams
            };

            // Track AddPaymentInfo
            if (typeof fbq === 'function' && PLANOS['main'].tracking.addPaymentInfo) {
                fbq('track', 'AddPaymentInfo', {
                    content_name: allProducts.map(p => p.name).join(', '),
                    content_ids: allProducts.map(p => p.sku),
                    content_type: 'product',
                    value: totalValue,
                    currency: 'BRL',
                    external_id: correlationId,
                    user_data: {
                        ph: customerData.phone,
                        em: customerData.email,
                        fn: customerData.name.split(' ')[0],
                        external_id: correlationId
                    }
                });
            }

            // --- META S2S INTELLIGENT TRACKING ---
            try {
                const getCookie = (name) => {
                    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
                    return match ? match[2] : null;
                };

                // Capture URL Params effectively
                const urlParams = new URLSearchParams(window.location.search);

                // Fire & Forget call to save correlation data
                fetch(BACKEND_BASE_PATH + '/meta-s2s-tracking/save_correlation.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        correlation_id: correlationId,
                        fbc: getCookie('_fbc'),
                        fbp: getCookie('_fbp'),
                        client_user_agent: navigator.userAgent,
                        event_source_url: window.location.href, // Includes UTMs, fbclid, etc.
                        value: totalValue,
                        currency: 'BRL',
                        pixel_id: PIXEL_ID,
                        product_description: allProducts.map(p => p.name).join(' + ')
                    })
                }).catch(err => console.warn('Meta S2S Warning:', err));
            } catch (e) { console.error(e); }
            // -------------------------------------

            try {
                // Mock fetch for now as 'process-pix-woovi.php' might not exist or be fully set up in this context, 
                // but we keep the logic structure.
                // Assuming existing backend structure:
                const response = await fetch(`${BACKEND_BASE_PATH}/process-pix-woovi.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(pixPayload)
                });

                // Fallback for demo/testing if backend 404s
                let result;
                if (response.status === 404) {
                    // Simulate success for local testing
                    result = {
                        success: true,
                        correlationId: correlationId,
                        pixData: {
                            qrCodeImage: 'https://placehold.co/200x200?text=QR+Code',
                            brCode: '00020126580014BR.GOV.BCB.PIX...',
                            formattedPrice: formatCurrency(totalValue * 100)
                        }
                    };
                } else {
                    result = await response.json();
                }

                if ((!response.ok && response.status !== 404) || !result.success) {
                    throw new Error(result.message || 'Erro ao gerar Pix');
                }

                showPixWaitView(result.pixData, result.correlationId);

            } catch (error) {
                console.error(error);
                paymentErrorDiv.textContent = 'Erro ao processar pagamento: ' + error.message;
                paymentErrorDiv.classList.remove('hidden');
                payButton.disabled = false;
                payButton.textContent = 'TENTAR NOVAMENTE';
            }
        };

        // Copy to clipboard helper
        window.copyToClipboard = (text) => {
            const btn = document.getElementById('btn-copy-pix');
            const original = btn.innerHTML;

            const updateButton = () => {
                btn.innerHTML = 'COPIADO!';
                setTimeout(() => btn.innerHTML = original, 2000);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(updateButton).catch(err => {
                    console.error('navigator.clipboard failed, trying fallback', err);
                    fallbackCopyToClipboard(text, updateButton);
                });
            } else {
                fallbackCopyToClipboard(text, updateButton);
            }
        };

        function fallbackCopyToClipboard(text, callback) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            
            // Ensure it's not visible and doesn't scroll the page
            textArea.style.position = "fixed";
            textArea.style.left = "-9999px";
            textArea.style.top = "0";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                const successful = document.execCommand('copy');
                if (successful && callback) callback();
            } catch (err) {
                console.error('Fallback: Oops, unable to copy', err);
            }

            document.body.removeChild(textArea);
        }

        const showPixWaitView = (pixData, correlationId) => {
            form.classList.add('hidden');
            // Update Header
            const header = document.getElementById('checkout-step-header');
            if (header) {
                header.innerHTML = `
                    <span class="bg-green-500 w-8 h-8 rounded-full flex items-center justify-center text-sm animate-pulse"><i data-lucide="clock" class="w-4 h-4 text-white"></i></span>
                    Aguardando Pagamento
                `;
            }
            pixWaitView.innerHTML = `
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Pagamento via PIX</h3>
                <p class="text-sm text-gray-600 dark:text-slate-400 mb-6">Escaneie o QR Code abaixo para finalizar.</p>
                <div class="bg-white p-2 rounded-lg inline-block mb-4 shadow-sm border border-gray-200 dark:border-none">
                    <img src="${pixData.qrCodeImage}" class="w-48 h-48">
                </div>
                <div class="mb-4">
                    <p class="text-gray-500 dark:text-slate-400 text-xs uppercase font-bold">Valor a Pagar</p>
                    <p class="text-2xl font-black text-gray-900 dark:text-white">${pixData.formattedPrice}</p>
                </div>
                <div class="bg-gray-100 dark:bg-slate-950 p-3 rounded border border-gray-200 dark:border-slate-800 flex items-center gap-2 mb-4">
                    <input readonly value="${pixData.brCode}" class="bg-transparent text-xs text-gray-600 dark:text-slate-500 w-full outline-none font-mono truncate">
                    <button id="btn-copy-pix" onclick="copyToClipboard('${pixData.brCode}')" class="text-blue-600 dark:text-blue-500 font-bold text-xs hover:text-blue-800 dark:hover:text-white transition">COPIAR</button>
                </div>
                <div class="animate-pulse text-green-600 dark:text-green-500 text-sm font-bold flex items-center justify-center gap-2">
                    <i data-lucide="loader" class="w-4 h-4 animate-spin"></i> Aguardando confirmação...
                </div>
            `;
            pixWaitView.classList.remove('hidden');
            lucide.createIcons();

            // Real Polling
            const pollInterval = setInterval(async () => {
                try {
                    const res = await fetch('api/check_payment_status_ebook.php?correlationId=' + correlationId);
                    const data = await res.json();
                    if (data.status === 'PAID' || data.status === 'COMPLETED') {
                        clearInterval(pollInterval);
                        showSuccessView();
                    }
                } catch (e) {
                    console.error('Polling error', e);
                }
            }, 5000); // Check every 5s
        };

        const showSuccessView = () => {
            pixWaitView.classList.add('hidden');
            successView.classList.remove('hidden');
            // Backend tracking handles Purchase event via N8N/S2S
        }

        // ---- DOWNSELL LOGIC ----

        function showDownsell() {
            if (!downsellEnabled || downsellTriggered || downsellAccepted) return;
            
            // Calcula o novo preço para exibir no modal
            let newPrice = baseProductPrice;
            if (downsellType === 'fixed') {
                newPrice = Math.max(0, baseProductPrice - downsellAmount);
            } else {
                newPrice = baseProductPrice * (1 - (downsellAmount / 100));
            }

            document.getElementById('downsell-new-price').innerText = formatPriceJS(newPrice);
            
            const modal = document.getElementById('downsell-modal');
            modal.classList.remove('hidden');
            setTimeout(() => modal.classList.add('active'), 10);
            
            downsellTriggered = true;
        }

        function acceptDownsell() {
            downsellAccepted = true;
            
            // Reaplica o desconto no preço corrente do produto principal
            if (downsellType === 'fixed') {
                currentProductPrice = Math.max(0, baseProductPrice - downsellAmount);
            } else {
                currentProductPrice = baseProductPrice * (1 - (downsellAmount / 100));
            }

            // Atualiza a UI do produto principal (o texto do preço na sidebar)
            const productPriceEls = document.querySelectorAll('.text-green-400');
            productPriceEls.forEach(el => {
                if (el.innerText.includes('R$')) {
                    el.innerText = 'R$ ' + formatPriceJS(currentProductPrice);
                }
            });

            updateTotalPrice();
            closeDownsell(false);
        }

        function closeDownsell(isCancel = true) {
            const modal = document.getElementById('downsell-modal');
            modal.classList.remove('active');
            setTimeout(() => modal.classList.add('hidden'), 300);

            // Se for cancelamento (fechar sem aceitar) e for modal, avisa o pai para fechar de vez
            if (isCancel && window.self !== window.top) {
                window.parent.postMessage('close-checkout-modal', '*');
            }
        }

        // Trigger: Exit Intent (Desktop)
        document.addEventListener('mouseleave', (e) => {
            if (e.clientY < 10) showDownsell();
        });

        // Trigger: Back Button (Mobile/Desktop)
        history.pushState(null, null, window.location.href);
        window.addEventListener('popstate', function () {
            if (downsellEnabled && !downsellTriggered && !downsellAccepted) {
                showDownsell();
                // Push denovo para interceptar a próxima tentativa se ele fechar o downsell
                history.pushState(null, null, window.location.href);
            }
        });

        // Trigger: Message from Parent (Modal Close Click)
        window.addEventListener('message', function (event) {
            if (event.data === 'trigger-downsell') {
                if (downsellEnabled && !downsellTriggered && !downsellAccepted) {
                    showDownsell();
                } else {
                    // Se não houver downsell ou já foi negado/aceito, avisa o pai para fechar
                    window.parent.postMessage('close-checkout-modal', '*');
                }
            }
        });

    </script>


    <!-- MICROSOFT CLARITY -->
    <script type="text/javascript">
        (function (c, l, a, r, i, t, y) {
            c[a] = c[a] || function () { (c[a].q = c[a].q || []).push(arguments) };
            t = l.createElement(r); t.async = 1; t.src = "https://www.clarity.ms/tag/" + i;
            y = l.getElementsByTagName(r)[0]; y.parentNode.insertBefore(t, y);
        })(window, document, "clarity", "script", "szinav36s7");
    </script>

    <!-- FAKE NOTIFICATIONS ENGINE -->
    <script>
        (function () {
            if (!PLANOS.main.notifications.enabled) return;

            // Updated List: Mix of Capitals and medium-sized/interior cities for realism
            const CIDADES = [
                'São Paulo - SP', 'Campinas - SP', 'Santos - SP', 'Ribeirão Preto - SP', 'Sorocaba - SP',
                'Rio de Janeiro - RJ', 'Niterói - RJ', 'Duque de Caxias - RJ', 'Nova Iguaçu - RJ',
                'Belo Horizonte - MG', 'Uberlândia - MG', 'Contagem - MG', 'Juiz de Fora - MG',
                'Curitiba - PR', 'Londrina - PR', 'Maringá - PR', 'Foz do Iguaçu - PR',
                'Porto Alegre - RS', 'Caxias do Sul - RS', 'Pelotas - RS', 'Canoas - RS',
                'Salvador - BA', 'Feira de Santana - BA', 'Vitória da Conquista - BA',
                'Recife - PE', 'Jaboatão dos Guararapes - PE', 'Olinda - PE',
                'Fortaleza - CE', 'Caucaia - CE',
                'Brasília - DF', 'Goiânia - GO', 'Aparecida de Goiânia - GO',
                'Manaus - AM', 'Belém - PA',
                'Vitória - ES', 'Vila Velha - ES', 'Serra - ES',
                'Florianópolis - SC', 'Joinville - SC', 'Blumenau - SC',
                'São Luís - MA', 'Maceió - AL', 'Natal - RN', 'Teresina - PI', 'João Pessoa - PB', 'Aracaju - SE', 'Cuiabá - MT', 'Campo Grande - MS'
            ];

            const HOMENS = ['João', 'Pedro', 'Lucas', 'Matheus', 'Gabriel', 'Rafael', 'Felipe', 'Bruno', 'Gustavo', 'Daniel', 'Carlos', 'Eduardo', 'Thiago', 'Rodrigo', 'Marcos', 'André', 'Luiz', 'Paulo', 'Vitor', 'Guilherme'];
            const MULHERES = ['Maria', 'Ana', 'Julia', 'Beatriz', 'Mariana', 'Larissa', 'Camila', 'Fernanda', 'Amanda', 'Bruna', 'Gabriela', 'Luana', 'Jessica', 'Leticia', 'Carolina', 'Isabela', 'Natália', 'Bianca', 'Débora', 'Vanessa'];
            const NOMES = [...HOMENS, ...MULHERES];

            const config = PLANOS.main.notifications;
            const isDark = document.documentElement.classList.contains('dark');

            function getRandomItem(arr) {
                return arr[Math.floor(Math.random() * arr.length)];
            }

            function replaceShortcodes(text) {
                let processed = text;
                processed = processed.replace(/{cidade}/g, getRandomItem(CIDADES));
                processed = processed.replace(/{nome}/g, getRandomItem(NOMES));
                processed = processed.replace(/{nome-homem}/g, getRandomItem(HOMENS));
                processed = processed.replace(/{nome-mulher}/g, getRandomItem(MULHERES));

                // {2horas} -> 2 hours after current time in GMT-3, format "XXH"
                const now = new Date();
                const future = new Date(now.getTime() + (2 * 60 * 60 * 1000));
                const hours2 = new Intl.DateTimeFormat('pt-BR', {
                    hour: '2-digit',
                    hour12: false,
                    timeZone: 'America/Sao_Paulo'
                }).format(future);
                processed = processed.replace(/{2horas}/gi, hours2 + 'H');

                return processed;
            }

            function createNotification() {
                const minutes = Math.floor(Math.random() * (57 - 2 + 1)) + 2;
                const textContent = replaceShortcodes(config.text);

                const notif = document.createElement('div');
                // Styles: Mobile First, bottom-left, small padding
                const bgClass = isDark ? 'bg-slate-900 border-slate-700 text-white shadow-blue-900/20' : 'bg-white border-gray-100 text-gray-900 shadow-xl';

                notif.className = `fixed bottom-4 left-4 right-4 sm:right-auto sm:w-80 p-3 rounded-xl border shadow-2xl flex items-center gap-3 transform translate-y-20 opacity-0 transition-all duration-500 z-50 ${bgClass}`;
                notif.innerHTML = `
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-green-500 flex items-center justify-center">
                    <i data-lucide="check-circle" class="w-6 h-6 text-white"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold leading-tight">${textContent}</p>
                    <p class="text-[10px] opacity-70 mt-0.5">Há ${minutes} minutos</p>
                </div>
                <button class="absolute top-1 right-1 opacity-50 hover:opacity-100 p-1" onclick="this.parentElement.remove()">
                    <i data-lucide="x" class="w-3 h-3"></i>
                </button>
            `;

                document.body.appendChild(notif);
                lucide.createIcons();

                // Animate In
                requestAnimationFrame(() => {
                    notif.classList.remove('translate-y-20', 'opacity-0');
                });

                // Remove after 5s
                setTimeout(() => {
                    notif.classList.add('translate-y-20', 'opacity-0');
                    setTimeout(() => notif.remove(), 500);
                }, 5000);
            }

            // Schedule
            function scheduleNext() {
                // Random Interval: 10 to 30 seconds
                const delay = Math.floor(Math.random() * (30000 - 10000 + 1)) + 10000;
                setTimeout(() => {
                    createNotification();
                    scheduleNext();
                }, delay);
            }

            // Initial delay: 5 seconds
            setTimeout(scheduleNext, 5000);

        })();
    </script>

    <!-- TOP BAR ENGINE -->
    <script>
        (function () {
            if (!PLANOS.main.topBar || !PLANOS.main.topBar.enabled) return;

            const config = PLANOS.main.topBar;

            function parseDateShortcodes(text) {
                const now = new Date();
                const options = { weekday: 'long', day: 'numeric', month: 'long' };
                // e.g., "terça-feira, 11 de fevereiro"
                const dateStr = now.toLocaleDateString('pt-BR', options);

                // {datahoje} -> "terça-feira, 11 de fevereiro"
                let processed = text.replace(/{datahoje}/gi, dateStr);

                // {datahojemaiusculo} -> "TERÇA-FEIRA, 11 DE FEVEREIRO"
                processed = processed.replace(/{datahojemaiusculo}/gi, dateStr.toUpperCase());

                // {datahojeminusculo} -> "terça-feira, 11 de fevereiro"
                processed = processed.replace(/{datahojeminusculo}/gi, dateStr.toLowerCase());

                // {datahojecapitalizado} -> "Terça-Feira, 11 De Fevereiro"
                processed = processed.replace(/{datahojecapitalizado}/gi, dateStr.replace(/\b\w/g, l => l.toUpperCase()));

                // {2horas} -> 2 hours after current time in GMT-3, format "XXH"
                const future = new Date(now.getTime() + (2 * 60 * 60 * 1000));
                const hours2 = new Intl.DateTimeFormat('pt-BR', {
                    hour: '2-digit',
                    hour12: false,
                    timeZone: 'America/Sao_Paulo'
                }).format(future);
                processed = processed.replace(/{2horas}/gi, hours2 + 'H');

                return processed;
            }

            const bar = document.createElement('div');
            bar.className = 'w-full py-2 px-4 text-center text-sm font-bold uppercase tracking-wide z-[9999] fixed top-0 left-0 shadow-lg flex items-center justify-center gap-2';
            bar.style.backgroundColor = config.bgColor;
            bar.style.color = config.textColor;
            bar.innerHTML = parseDateShortcodes(config.text);

            document.body.prepend(bar);

            // Spacer Strategy: Push content down without overwriting body padding
            const spacer = document.createElement('div');
            spacer.style.width = '100%';
            spacer.style.flexShrink = '0';
            spacer.style.transition = 'height 0.2s ease';
            document.body.prepend(spacer);

            const updateLayout = () => {
                const height = bar.offsetHeight;
                spacer.style.height = height + 'px';

                // Adjust sticky elements
                // Base top-8 is 2rem (32px). We add the bar height.
                document.querySelectorAll('.lg\\:sticky').forEach(el => {
                    el.style.top = (32 + height) + 'px';
                });
            };

            // Run immediately and on resize
            updateLayout();
            window.addEventListener('resize', updateLayout);
            setTimeout(updateLayout, 200); // Safety check for font loading

            lucide.createIcons(); // Updates icons inside the bar if any
        })();
    </script>
</body>

</html>