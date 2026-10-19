<?php
require_once 'api/connection.php';
require_once 'api/functions/format_price.php';
$database = new Database();
$db = $database->getConnection();

$slug = $_GET['slug'] ?? '';
$isModal = ($_GET['modal'] ?? '') === 'true';
$isEmbed = ($_GET['embed'] ?? '') === 'true';

// Headers para permitir iFrame
@header_remove('X-Frame-Options');
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

$productGateway = $product['payment_gateway'] ?? 'woovi';
$supportsCreditCard = in_array($productGateway, ['appmax', 'appmax_card_woovi_pix']);
$pixGateway = ($productGateway === 'appmax_card_woovi_pix') ? 'woovi' : (($productGateway === 'appmax') ? 'appmax' : $productGateway);
?>
<!DOCTYPE html>
<html lang="pt-BR" class="<?= $isDarkMode ? 'dark' : '' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Checkout Seguro - <?= htmlspecialchars($product['name']) ?></title>
    
    <!-- GOOGLE FONTS (Plus Jakarta Sans & Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- TAILWIND CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        heading: ['"Plus Jakarta Sans"', 'Inter', 'sans-serif'],
                        display: ['"Plus Jakarta Sans"', 'Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                        }
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>

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
        :root {
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .font-heading {
            font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
        }

        /* Prevenir zoom indesejado no iOS Safari */
        @media screen and (max-width: 768px) {
            input, select, textarea {
                font-size: 16px !important;
            }
        }

        /* BOTÃO CTA DE ALTÍSSIMA CONVERSÃO COM SHIMMER */
        .cta-button {
            background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%);
            box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.4), 0 8px 10px -6px rgba(16, 185, 129, 0.2);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .cta-button:hover {
            background: linear-gradient(135deg, #34d399 0%, #10b981 50%, #059669 100%);
            box-shadow: 0 14px 28px -4px rgba(16, 185, 129, 0.5), 0 10px 10px -5px rgba(16, 185, 129, 0.3);
            transform: translateY(-1px);
        }

        .cta-button:active {
            transform: scale(0.98);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .shimmer-sweep {
            position: absolute;
            top: 0;
            left: -120%;
            width: 70%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.32), transparent);
            transform: skewX(-20deg);
            animation: shimmer-sweep 3s infinite ease-in-out;
            pointer-events: none;
        }

        @keyframes shimmer-sweep {
            0% { left: -120%; }
            35%, 100% { left: 180%; }
        }

        /* RADAR LIVE PULSE */
        @keyframes radar-ping {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.1); opacity: 1; }
            100% { transform: scale(0.95); opacity: 0.8; }
        }
        .live-radar {
            animation: radar-ping 2s infinite ease-in-out;
        }

        /* ORDER BUMP CHECKBOX EFFECTS */
        .bump-box {
            transition: all 0.2s ease;
        }
        .bump-card-active {
            border-color: #10b981 !important;
            border-style: solid !important;
            background-color: rgba(16, 185, 129, 0.08) !important;
            box-shadow: 0 4px 20px -4px rgba(16, 185, 129, 0.2);
        }
        .bump-icon-box {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .bump-icon-box svg {
            opacity: 0;
            transition: opacity 0.15s ease, transform 0.15s ease;
            transform: scale(0.6);
        }
        .bump-card-active .bump-icon-box,
        input[name="bumps[]"]:checked ~ .bump-box .bump-icon-box {
            background-color: #10b981 !important;
            border-color: #10b981 !important;
            color: #ffffff !important;
        }
        .bump-card-active .bump-icon-box svg,
        input[name="bumps[]"]:checked ~ .bump-box .bump-icon-box svg {
            opacity: 1 !important;
            transform: scale(1) !important;
            stroke: #ffffff !important;
        }
        .bump-card-active .bump-tag,
        input[name="bumps[]"]:checked ~ .bump-box .bump-tag {
            background-color: rgba(16, 185, 129, 0.2) !important;
            border-color: rgba(16, 185, 129, 0.4) !important;
            color: #047857 !important;
        }
        .dark .bump-card-active .bump-tag,
        .dark input[name="bumps[]"]:checked ~ .bump-box .bump-tag {
            color: #6ee7b7 !important;
        }
        .bump-card-active .bump-price,
        input[name="bumps[]"]:checked ~ .bump-box .bump-price {
            color: #059669 !important;
        }
        .dark .bump-card-active .bump-price,
        .dark input[name="bumps[]"]:checked ~ .bump-box .bump-price {
            color: #34d399 !important;
        }

        /* DOWNSELL MODAL STYLES */
        #downsell-modal {
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .downsell-content {
            transform: scale(0.92);
            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        #downsell-modal.active .downsell-content {
            transform: scale(1);
        }

        /* TOP BAR SCARCITY TIMER & BADGE STYLES */
        .top-bar-container svg, 
        .top-bar-container i {
            display: inline-block !important;
            vertical-align: middle !important;
            margin-top: -2px !important;
            margin-right: 4px;
            margin-left: 4px;
        }

        #top-bar-timer-countdown {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-variant-numeric: tabular-nums;
            font-weight: 900;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            padding: 0.15rem 0.5rem;
            margin: 0 0.35rem;
            border-radius: 0.375rem;
            line-height: 1.25;
            vertical-align: middle;
            background-color: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            animation: pulse-timer 1.8s infinite ease-in-out;
        }

        @keyframes pulse-timer {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        /* Custom Scrollbar for sleek desktop feel */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.3);
            border-radius: 9999px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(148, 163, 184, 0.5);
        }
    </style>
</head>

<body
    class="min-h-screen flex flex-col items-center justify-start transition-colors duration-300 <?= $isEmbed ? 'p-0 bg-transparent overflow-x-hidden' : ($isModal ? 'py-2 px-2 overflow-x-hidden bg-slate-50 dark:bg-[#0B0F19] text-gray-900 dark:text-slate-100' : 'py-3 sm:py-8 px-3 sm:px-4 bg-slate-50 dark:bg-[#0B0F19] text-gray-900 dark:text-slate-100') ?>">

    <?php if (!$isModal && !$isEmbed): ?>
    <!-- TOP TRUST & SECURITY HEADER -->
    <header class="w-full max-w-5xl mb-4 sm:mb-6 flex items-center justify-between px-2 text-xs text-slate-500 dark:text-slate-400">
        <div class="flex items-center gap-2 font-semibold">
            <span class="flex h-2 w-2 relative">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            <span class="text-gray-700 dark:text-slate-300 flex items-center gap-1.5">
                <i data-lucide="shield-check" class="w-3.5 h-3.5 text-emerald-500 dark:text-emerald-400"></i>
                Ambiente Criptografado & Seguro
            </span>
        </div>
        <div class="hidden sm:flex items-center gap-4 text-[11px] text-slate-500 dark:text-slate-400 font-medium">
            <span class="flex items-center gap-1"><i data-lucide="lock" class="w-3 h-3 text-slate-400"></i> SSL 256-Bit</span>
            <span class="flex items-center gap-1"><i data-lucide="zap" class="w-3 h-3 text-emerald-500 dark:text-emerald-400"></i> Acesso Imediato</span>
        </div>
    </header>
    <?php endif; ?>

    <?php 
    $isMinimalist = ($product['checkout_style'] ?? 'default') === 'minimalist';
    $reqName = (int) ($product['request_name'] ?? 1) !== 0;
    $reqEmail = (int) ($product['request_email'] ?? 1) !== 0;
    $reqPhone = (int) ($product['request_phone'] ?? 1) !== 0;
    $hasPersonalData = $reqName || $reqEmail || $reqPhone || $supportsCreditCard || ($pixGateway === 'appmax');
    $isPhysical = ($product['product_type'] ?? 'digital') === 'physical';
    
    if ($isPhysical) {
        $accessText = "Receba em até 7 dias";
    } else {
        $accessText = "Acesso Imediato";
        if ($reqEmail && $reqPhone) {
            $accessText .= " por E-mail e WhatsApp";
        } elseif ($reqEmail) {
            $accessText .= " por E-mail";
        } elseif ($reqPhone) {
            $accessText .= " por WhatsApp";
        }
    }
    ?>

    <!-- MAIN CONTAINER -->
    <div class="w-full <?= $isMinimalist ? 'max-w-md mx-auto' : ($isEmbed ? 'max-w-5xl mx-auto' : ($isModal ? 'max-w-4xl' : 'max-w-5xl')) ?> <?= $isMinimalist ? '' : 'grid grid-cols-1 lg:grid-cols-12 gap-5 lg:gap-8' ?> items-start">

        <!-- MOBILE-FIRST ORDER BANNER (VISIBLE ONLY ON MOBILE IN DEFAULT STYLE) -->
        <?php if (!$isMinimalist): ?>
        <div class="lg:hidden col-span-1 w-full">
            <div class="bg-white dark:bg-slate-900/90 border border-gray-200 dark:border-slate-800 rounded-2xl p-3.5 shadow-lg backdrop-blur-md">
                <div class="flex items-center gap-3">
                    <div class="w-16 h-16 rounded-xl overflow-hidden bg-slate-100 dark:bg-slate-800 shrink-0 border border-gray-200 dark:border-slate-700/80 relative shadow-inner">
                        <img src="<?= htmlspecialchars($product['image_url'] ?: 'https://placehold.co/600x400?text=Produto') ?>"
                            class="w-full h-full object-cover" alt="<?= htmlspecialchars($product['name']) ?>">
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1 text-amber-500 dark:text-amber-400 text-[11px] mb-0.5 font-bold">
                            <span>★★★★★</span>
                            <span class="text-[10px] text-slate-500 dark:text-slate-400 font-semibold ml-0.5">4.9 (2.4k+)</span>
                        </div>
                        <h2 class="font-heading font-extrabold text-gray-900 dark:text-white text-sm leading-tight line-clamp-1">
                            <?= htmlspecialchars($product['name']) ?>
                        </h2>
                        <div class="flex items-baseline gap-2 mt-1">
                            <?php if (!empty($product['compare_at_price']) && (float)$product['compare_at_price'] > 0): ?>
                                <span class="text-xs text-slate-400 line-through">De R$ <?= format_price($product['compare_at_price']) ?></span>
                            <?php endif; ?>
                            <span class="text-base font-black text-emerald-600 dark:text-emerald-400">
                                R$ <?= format_price($product['price']) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <?php if (!empty($product['description'])): ?>
                <details class="group mt-2.5 pt-2.5 border-t border-gray-100 dark:border-slate-800/80 text-xs">
                    <summary class="cursor-pointer list-none flex items-center justify-between text-slate-500 dark:text-slate-400 font-medium hover:text-emerald-500 transition">
                        <span>Ver detalhes da oferta</span>
                        <i data-lucide="chevron-down" class="w-3.5 h-3.5 transition group-open:rotate-180"></i>
                    </summary>
                    <p class="mt-2 text-gray-600 dark:text-slate-300 leading-relaxed text-xs">
                        <?= htmlspecialchars($product['description']) ?>
                    </p>
                </details>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- DESKTOP COLUMN 1: PRODUCT SHOWCASE (STICKY) -->
        <?php if (!$isMinimalist): ?>
        <div class="hidden lg:block lg:col-span-5 lg:sticky lg:top-8 space-y-5">
            <div class="bg-white dark:bg-slate-900/90 border border-gray-200 dark:border-slate-800 rounded-3xl p-6 shadow-2xl backdrop-blur-xl">
                <div class="aspect-square w-full bg-slate-100 dark:bg-slate-950 rounded-2xl overflow-hidden mb-5 relative border border-gray-200 dark:border-slate-800 shadow-lg">
                    <img src="<?= htmlspecialchars($product['image_url'] ?: 'https://placehold.co/600x400?text=Produto') ?>"
                        class="w-full h-full object-cover" alt="<?= htmlspecialchars($product['name']) ?>">

                    <!-- Badge Promocional -->
                    <div class="absolute top-3 right-3 bg-gradient-to-r from-amber-500 to-yellow-500 text-slate-950 text-[11px] font-black px-3 py-1 rounded-full shadow-lg flex items-center gap-1.5 uppercase tracking-wider">
                        <i data-lucide="sparkles" class="w-3.5 h-3.5"></i> Oferta Especial
                    </div>
                </div>

                <div class="flex items-center gap-1.5 text-amber-500 dark:text-amber-400 text-sm mb-2 font-bold">
                    <span>★★★★★</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-semibold ml-1">4.9/5 • Mais de 2.400 clientes satisfeitos</span>
                </div>

                <h1 class="font-heading text-2xl font-black text-gray-900 dark:text-white mb-2 leading-tight">
                    <?= htmlspecialchars($product['name']) ?>
                </h1>
                
                <p class="text-gray-600 dark:text-slate-300 text-sm mb-5 leading-relaxed">
                    <?= htmlspecialchars($product['description'] ?: 'Acesso imediato ao conteúdo digital com liberação automática.') ?>
                </p>

                <div class="flex items-center justify-between border-t border-b border-gray-100 dark:border-slate-800/90 py-4 my-4">
                    <span class="text-slate-500 dark:text-slate-400 text-sm font-semibold">Valor Total:</span>
                    <div class="text-right">
                        <?php if (!empty($product['compare_at_price']) && (float)$product['compare_at_price'] > 0): ?>
                            <span class="block text-xs text-slate-400 line-through font-medium">De R$ <?= format_price($product['compare_at_price']) ?></span>
                        <?php endif; ?>
                        <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400">R$ <?= format_price($product['price']) ?></span>
                    </div>
                </div>

                <!-- Box de Garantia & Entrega -->
                <div class="bg-gray-50 dark:bg-slate-950/60 rounded-2xl p-4 border border-gray-200 dark:border-slate-800 space-y-3 text-xs text-gray-700 dark:text-slate-300">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-500/20">
                            <i data-lucide="shield-check" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <strong class="block font-bold text-gray-900 dark:text-white text-xs">Garantia Blindada de 7 Dias</strong>
                            <span class="text-slate-500 dark:text-slate-400 text-[11px]">Risco zero. Satisfação total ou seu dinheiro de volta.</span>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3 pt-2.5 border-t border-gray-200 dark:border-slate-800/80">
                        <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-500/20">
                            <i data-lucide="zap" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <strong class="block font-bold text-gray-900 dark:text-white text-xs"><?= $accessText ?></strong>
                            <span class="text-slate-500 dark:text-slate-400 text-[11px]">Liberação automática assim que o pagamento for concluído.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- CHECKOUT FORM COLUMN (COLUMN 2 OR FULL WIDTH IF MINIMALIST) -->
        <div class="<?= $isMinimalist ? 'w-full' : 'col-span-1 lg:col-span-7 w-full' ?>">
            
            <div class="<?= $isMinimalist ? 'bg-white dark:bg-slate-900/95 border border-gray-200 dark:border-slate-800 rounded-3xl p-4 sm:p-7 shadow-xl dark:shadow-2xl backdrop-blur-xl' : 'bg-white dark:bg-slate-900/95 border border-gray-200 dark:border-slate-800 rounded-3xl p-4 sm:p-7 shadow-xl dark:shadow-2xl backdrop-blur-xl' ?>">
                
                <!-- MINIMALIST HEADER -->
                <?php if ($isMinimalist): ?>
                <div class="flex items-center gap-3.5 mb-5 pb-4 border-b border-gray-100 dark:border-slate-800">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-xl overflow-hidden bg-slate-100 dark:bg-slate-800 shrink-0 border border-gray-200 dark:border-slate-700 relative shadow-inner">
                        <img src="<?= htmlspecialchars($product['image_url'] ?: 'https://placehold.co/600x400?text=Produto') ?>"
                            class="w-full h-full object-cover" alt="<?= htmlspecialchars($product['name']) ?>">
                    </div>
                    <div class="flex-1 min-w-0 text-left">
                        <div class="flex items-center gap-1 text-amber-500 dark:text-amber-400 text-[11px] mb-0.5 font-bold">
                            <span>★★★★★</span>
                            <span class="text-[10px] text-slate-500 dark:text-slate-400 font-semibold">4.9/5</span>
                        </div>
                        <h1 class="font-heading font-extrabold text-sm sm:text-base text-gray-900 dark:text-white truncate">
                            <?= htmlspecialchars($product['name']) ?>
                        </h1>
                        <div class="flex items-baseline gap-2 mt-0.5">
                            <?php if (!empty($product['compare_at_price']) && (float)$product['compare_at_price'] > 0): ?>
                                <span class="text-xs text-slate-400 line-through">De R$ <?= format_price($product['compare_at_price']) ?></span>
                            <?php endif; ?>
                            <span class="text-base sm:text-lg font-black text-emerald-600 dark:text-emerald-400">R$ <?= format_price($product['price']) ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- STEP 1 HEADER -->
                <?php if ($hasPersonalData): ?>
                <div id="checkout-step-header" class="mb-5 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-heading font-black text-sm shrink-0 shadow-xs">
                        1
                    </div>
                    <div>
                        <h2 class="font-heading text-base sm:text-lg font-black text-gray-900 dark:text-white leading-tight">
                            Dados de Acesso
                        </h2>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            Informe para onde enviaremos a confirmação e seu acesso
                        </p>
                    </div>
                </div>
                <?php else: ?>
                <div id="checkout-step-header" class="hidden"></div>
                <?php endif; ?>

                <!-- ERROR ALERT DIV -->
                <div id="payment-error"
                    class="hidden bg-red-50 dark:bg-red-950/60 border border-red-300 dark:border-red-500/50 text-red-700 dark:text-red-300 px-4 py-3 rounded-2xl mb-4 text-xs sm:text-sm font-medium flex items-center gap-2"
                    role="alert">
                </div>

                <form id="checkout-form" novalidate>

                    <!-- DOWNSELL MODAL -->
                    <div id="downsell-modal" class="fixed inset-0 z-[99999] hidden flex items-center justify-center p-3 sm:p-4">
                        <div class="absolute inset-0 bg-slate-950/85 backdrop-blur-md shadow-2xl transition-opacity" onclick="closeDownsell()"></div>
                        <div class="downsell-content relative w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl overflow-y-auto max-h-[92vh] border-2 border-amber-500/40 p-5 sm:p-7 text-center shadow-2xl">
                            
                            <div class="mb-3.5 inline-flex p-3 bg-amber-500/10 rounded-2xl border border-amber-500/20 text-amber-500">
                                <i data-lucide="gift" class="w-8 h-8 sm:w-10 sm:h-10"></i>
                            </div>
                            
                            <div class="inline-block px-3 py-1 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-700 dark:text-amber-300 text-[10px] font-black uppercase tracking-wider mb-2">
                                🛑 Oportunidade Única de Saída
                            </div>

                            <h2 class="font-heading text-xl sm:text-2xl font-black text-gray-900 dark:text-white mb-2 tracking-tight">
                                ESPERA! NÃO VÁ EMBORA
                            </h2>
                            
                            <p class="text-xs sm:text-sm text-gray-600 dark:text-slate-300 mb-4 leading-relaxed">
                                Notamos que você está prestes a sair. Queremos te dar uma última chance de levar o 
                                <span class="font-bold text-amber-600 dark:text-amber-400"><?= htmlspecialchars($product['name']) ?></span> 
                                com um desconto especial de liberação imediata:
                            </p>

                            <div class="bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-transparent rounded-2xl p-4 mb-5 border border-amber-500/30">
                                <span class="block text-[10px] text-slate-500 dark:text-slate-400 uppercase font-black tracking-wider mb-1">
                                    Preço Promocional Exclusivo
                                </span>
                                <div class="flex items-center justify-center gap-3">
                                    <span class="text-sm sm:text-base text-slate-400 line-through">R$ <?= format_price($product['price']) ?></span>
                                    <span class="text-3xl sm:text-4xl font-black text-emerald-600 dark:text-emerald-400">R$ <span id="downsell-new-price">0,00</span></span>
                                </div>
                                <span class="inline-block mt-1 text-[10px] font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-500/10 px-2.5 py-0.5 rounded-full border border-emerald-500/20">
                                    Economia aplicada instantaneamente
                                </span>
                            </div>

                            <div class="space-y-2.5">
                                <button type="button" onclick="acceptDownsell()"
                                    class="cta-button relative overflow-hidden w-full py-3.5 sm:py-4 rounded-2xl text-white font-heading font-black text-sm sm:text-base uppercase tracking-wider flex items-center justify-center gap-2 shadow-xl hover:shadow-emerald-500/30 active:scale-[0.98] transition">
                                    <span class="shimmer-sweep"></span>
                                    <i data-lucide="check" class="w-5 h-5"></i>
                                    <span>APROVEITAR DESCONTO AGORA</span>
                                </button>
                                <button type="button" onclick="closeDownsell()"
                                    class="w-full py-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-xs font-semibold transition">
                                    Não, prefiro abrir mão deste desconto e pagar o valor cheio
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- PERSONAL DATA FIELDS -->
                    <?php if ($hasPersonalData || $isPhysical): ?>
                    <div class="space-y-3.5 mb-7">
                        <?php if ($reqName): ?>
                        <div>
                            <label for="name" class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1.5 ml-1 flex items-center justify-between">
                                <span>NOME COMPLETO</span>
                                <span class="text-[10px] text-slate-400 font-normal">Obrigatório</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i data-lucide="user" class="w-4 h-4"></i>
                                </div>
                                <input type="text" id="name" placeholder="Digite seu nome completo..."
                                    class="pl-10 block w-full bg-white dark:bg-slate-950/80 border border-gray-300 dark:border-slate-700/80 rounded-xl py-3 px-4 text-gray-900 dark:text-white text-base sm:text-sm font-medium transition-all duration-200 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                                    required autocomplete="name">
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($reqEmail): ?>
                        <div>
                            <label for="email" class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1.5 ml-1 flex items-center justify-between">
                                <span>SEU MELHOR E-MAIL</span>
                                <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-medium">Acesso enviado aqui</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i data-lucide="mail" class="w-4 h-4"></i>
                                </div>
                                <input type="email" id="email" placeholder="seuemail@exemplo.com"
                                    class="pl-10 block w-full bg-white dark:bg-slate-950/80 border border-gray-300 dark:border-slate-700/80 rounded-xl py-3 px-4 text-gray-900 dark:text-white text-base sm:text-sm font-medium transition-all duration-200 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                                    required autocomplete="email" inputmode="email">
                            </div>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 ml-1 flex items-center gap-1">
                                <i data-lucide="shield-check" class="w-3 h-3 text-emerald-500 dark:text-emerald-400"></i> Seus dados estão protegidos. Não enviamos spam.
                            </p>
                        </div>
                        <?php endif; ?>

                        <?php if ($reqPhone): ?>
                        <div>
                            <label for="whatsapp" class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1.5 ml-1 flex items-center justify-between">
                                <span>WHATSAPP COM DDD</span>
                                <span class="text-[10px] text-slate-400 font-normal">Para suporte e avisos</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i data-lucide="phone" class="w-4 h-4"></i>
                                </div>
                                <input type="tel" id="whatsapp" placeholder="(00) 00000-0000"
                                    class="pl-10 block w-full bg-white dark:bg-slate-950/80 border border-gray-300 dark:border-slate-700/80 rounded-xl py-3 px-4 text-gray-900 dark:text-white text-base sm:text-sm font-medium transition-all duration-200 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                                    required autocomplete="tel" inputmode="numeric">
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- CPF (Obrigatório para Cartão e Pix Appmax) -->
                        <div id="cpf-field-wrapper" class="<?= ($pixGateway === 'appmax') ? '' : 'hidden' ?>">
                            <label for="cpf" class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1.5 ml-1 flex items-center justify-between">
                                <span>CPF DO TITULAR <span class="text-red-500">*</span></span>
                                <span class="text-[10px] text-slate-400 font-normal">Exigência Bancária</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i data-lucide="file-text" class="w-4 h-4"></i>
                                </div>
                                <input type="tel" id="cpf" placeholder="000.000.000-00" maxlength="14"
                                    class="pl-10 block w-full bg-white dark:bg-slate-950/80 border border-gray-300 dark:border-slate-700/80 rounded-xl py-3 px-4 text-gray-900 dark:text-white text-base sm:text-sm font-medium transition-all duration-200 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                                    inputmode="numeric">
                            </div>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 ml-1 flex items-center gap-1">
                                <i data-lucide="lock" class="w-3 h-3 text-emerald-500 dark:text-emerald-400"></i> Necessário para emissão e segurança do pagamento.
                            </p>
                        </div>

                        <!-- CAMPOS DE ENTREGA FÍSICA -->
                        <?php if ($isPhysical): ?>
                        <div class="grid grid-cols-6 gap-3 pt-3 border-t border-gray-100 dark:border-slate-800">
                            <div class="col-span-6">
                                <h3 class="text-xs font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-widest mb-1 flex items-center gap-1.5">
                                    <i data-lucide="truck" class="w-4 h-4"></i> Dados de Entrega
                                </h3>
                            </div>
                            
                            <div class="col-span-6 sm:col-span-3">
                                <label for="cep" class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1 ml-1">CEP</label>
                                <input type="text" id="cep" placeholder="00000-000" maxlength="9"
                                    class="block w-full bg-white dark:bg-slate-950/80 border border-gray-300 dark:border-slate-700/80 rounded-xl p-3 text-gray-900 dark:text-white text-base sm:text-sm font-medium transition-all duration-200 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                                    required inputmode="numeric">
                            </div>

                            <div class="col-span-6 sm:col-span-6">
                                <label for="address" class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1 ml-1">ENDEREÇO</label>
                                <input type="text" id="address" placeholder="Rua, Avenida..."
                                    class="block w-full bg-white dark:bg-slate-950/80 border border-gray-300 dark:border-slate-700/80 rounded-xl p-3 text-gray-900 dark:text-white text-base sm:text-sm font-medium transition-all duration-200 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                                    required>
                            </div>

                            <div class="col-span-3 sm:col-span-2">
                                <label for="address_number" class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1 ml-1">NÚMERO</label>
                                <input type="text" id="address_number" placeholder="123"
                                    class="block w-full bg-white dark:bg-slate-950/80 border border-gray-300 dark:border-slate-700/80 rounded-xl p-3 text-gray-900 dark:text-white text-base sm:text-sm font-medium transition-all duration-200 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                                    required>
                            </div>

                            <div class="col-span-3 sm:col-span-4">
                                <label for="complement" class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1 ml-1">COMPLEMENTO</label>
                                <input type="text" id="complement" placeholder="Apto, Bloco..."
                                    class="block w-full bg-white dark:bg-slate-950/80 border border-gray-300 dark:border-slate-700/80 rounded-xl p-3 text-gray-900 dark:text-white text-base sm:text-sm font-medium transition-all duration-200 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20">
                            </div>

                            <div class="col-span-6 sm:col-span-6">
                                <label for="neighborhood" class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1 ml-1">BAIRRO</label>
                                <input type="text" id="neighborhood" placeholder="Seu bairro..."
                                    class="block w-full bg-white dark:bg-slate-950/80 border border-gray-300 dark:border-slate-700/80 rounded-xl p-3 text-gray-900 dark:text-white text-base sm:text-sm font-medium transition-all duration-200 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                                    required>
                            </div>

                            <div class="col-span-4 sm:col-span-4">
                                <label for="city" class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1 ml-1">CIDADE</label>
                                <input type="text" id="city" placeholder="Sua cidade..."
                                    class="block w-full bg-white dark:bg-slate-950/80 border border-gray-300 dark:border-slate-700/80 rounded-xl p-3 text-gray-900 dark:text-white text-base sm:text-sm font-medium transition-all duration-200 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                                    required>
                            </div>

                            <div class="col-span-2 sm:col-span-2">
                                <label for="state" class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1 ml-1">ESTADO</label>
                                <input type="text" id="state" placeholder="UF" maxlength="2"
                                    class="block w-full bg-white dark:bg-slate-950/80 border border-gray-300 dark:border-slate-700/80 rounded-xl p-3 text-gray-900 dark:text-white text-base sm:text-sm font-medium uppercase transition-all duration-200 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                                    required>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- STEP 2 HEADER -->
                    <div class="mb-5 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-heading font-black text-sm shrink-0 shadow-xs">
                            <?= $hasPersonalData ? '2' : '1' ?>
                        </div>
                        <div>
                            <h2 class="font-heading text-base sm:text-lg font-black text-gray-900 dark:text-white leading-tight">
                                Forma de Pagamento
                            </h2>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                Escolha como deseja pagar de forma segura
                            </p>
                        </div>
                    </div>

                    <!-- PAYMENT METHODS SELECTOR -->
                    <?php if ($supportsCreditCard): ?>
                    <div class="mb-6 space-y-3.5">
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" id="tab-payment-pix" onclick="selectPaymentMethod('pix')"
                                class="p-3.5 rounded-2xl border-2 flex items-center justify-center gap-2.5 font-bold text-sm transition-all duration-200 cursor-pointer border-emerald-500 bg-emerald-500/10 text-gray-900 dark:text-white shadow-md shadow-emerald-500/10 ring-1 ring-emerald-500/30">
                                <div class="bg-white p-0.5 rounded border border-gray-200 shrink-0"><img src="/imagens/icon-pix.png" class="h-4 w-4 object-contain"></div>
                                <span class="tracking-wide">PIX</span>
                            </button>

                            <button type="button" id="tab-payment-card" onclick="selectPaymentMethod('card')"
                                class="p-3.5 rounded-2xl border-2 flex items-center justify-center gap-2.5 font-bold text-sm transition-all duration-200 cursor-pointer border-gray-200 dark:border-slate-800 bg-gray-50 dark:bg-slate-950/60 text-slate-500 dark:text-slate-400 hover:border-gray-300 dark:hover:border-slate-700">
                                <i data-lucide="credit-card" class="w-4 h-4 text-slate-400"></i>
                                <span class="tracking-wide">Cartão</span>
                            </button>
                        </div>

                        <!-- INFORMAÇÃO PIX -->
                        <div id="pix-payment-info">
                            <div class="border border-emerald-500/30 bg-emerald-500/[0.08] p-3.5 sm:p-4 rounded-2xl flex items-center justify-between gap-3 shadow-xs">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-emerald-500/20 shrink-0 flex items-center justify-center shadow-xs">
                                        <img src="/imagens/icon-pix.png" class="h-5 w-5 object-contain" alt="Pix">
                                    </div>
                                    <div class="min-w-0">
                                        <span class="font-heading font-extrabold text-gray-900 dark:text-white text-sm block leading-tight">PIX Oficial</span>
                                        <span class="text-[11px] text-slate-500 dark:text-slate-400 block leading-tight">Aprovação imediata em menos de 10s</span>
                                    </div>
                                </div>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border border-emerald-500/40 whitespace-nowrap shrink-0">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Liberação Imediata
                                </span>
                            </div>
                        </div>

                        <!-- FORMULÁRIO CARTÃO DE CRÉDITO -->
                        <div id="card-payment-fields" class="space-y-3.5 bg-gray-50/80 dark:bg-slate-950/60 p-4 sm:p-5 rounded-2xl border border-gray-200 dark:border-slate-800 hidden">
                            <div>
                                <label for="card_number" class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1.5 ml-1">NÚMERO DO CARTÃO</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                        <i data-lucide="credit-card" class="w-4 h-4"></i>
                                    </div>
                                    <input type="tel" id="card_number" placeholder="0000 0000 0000 0000" maxlength="19"
                                        class="pl-10 pr-16 block w-full bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-700/80 rounded-xl py-3 px-4 text-gray-900 dark:text-white text-base sm:text-sm font-medium transition-all duration-200 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                                        inputmode="numeric">
                                    <div id="card-brand-icon" class="absolute right-3.5 top-1/2 -translate-y-1/2 flex items-center pointer-events-none">
                                        <i data-lucide="credit-card" class="w-5 h-5 text-slate-400"></i>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label for="card_holder" class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1.5 ml-1">NOME IMPRESSO NO CARTÃO</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                        <i data-lucide="user" class="w-4 h-4"></i>
                                    </div>
                                    <input type="text" id="card_holder" placeholder="COMO IMPRESSO NO CARTÃO"
                                        class="pl-10 block w-full bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-700/80 rounded-xl py-3 px-4 text-gray-900 dark:text-white text-base sm:text-sm uppercase font-medium transition-all duration-200 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="card_expiry" class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1.5 ml-1">VALIDADE (MM/AA)</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                            <i data-lucide="calendar" class="w-4 h-4"></i>
                                        </div>
                                        <input type="tel" id="card_expiry" placeholder="MM/AA" maxlength="5"
                                            class="pl-10 block w-full bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-700/80 rounded-xl py-3 px-4 text-gray-900 dark:text-white text-base sm:text-sm font-medium transition-all duration-200 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                                            inputmode="numeric">
                                    </div>
                                </div>

                                <div>
                                    <label for="card_cvv" class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1.5 ml-1">CVV</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                            <i data-lucide="lock" class="w-4 h-4"></i>
                                        </div>
                                        <input type="tel" id="card_cvv" placeholder="123" maxlength="4"
                                            class="pl-10 block w-full bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-700/80 rounded-xl py-3 px-4 text-gray-900 dark:text-white text-base sm:text-sm font-medium transition-all duration-200 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                                            inputmode="numeric">
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label for="card_installments" class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1.5 ml-1">PARCELAS</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                        <i data-lucide="layers" class="w-4 h-4"></i>
                                    </div>
                                    <select id="card_installments"
                                        class="pl-10 pr-8 block w-full bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-700/80 rounded-xl py-3 px-4 text-gray-900 dark:text-white text-base sm:text-sm font-medium transition-all duration-200 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 appearance-none outline-none">
                                    </select>
                                    <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-slate-400">
                                        <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="mb-6">
                        <div class="border border-emerald-500/30 bg-emerald-500/[0.08] p-3.5 sm:p-4 rounded-2xl flex items-center justify-between gap-3 shadow-xs">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-emerald-500/20 shrink-0 flex items-center justify-center shadow-xs">
                                    <img src="/imagens/icon-pix.png" class="h-5 w-5 object-contain" alt="Pix">
                                </div>
                                <div class="min-w-0">
                                    <span class="font-heading font-extrabold text-gray-900 dark:text-white text-sm block leading-tight">PIX Oficial</span>
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400 block leading-tight">Aprovação imediata em menos de 10s</span>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border border-emerald-500/40 whitespace-nowrap shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Liberação Imediata
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ORDER BUMPS -->
                    <?php if (!empty($product['bumps'])): ?>
                    <div class="space-y-3 mb-7 bg-gray-50/80 dark:bg-slate-950/60 p-4 rounded-2xl border border-gray-200 dark:border-slate-800/90">
                        <p class="text-xs font-black text-amber-600 dark:text-amber-400 uppercase tracking-wider mb-2 flex items-center gap-2">
                            <i data-lucide="zap" class="w-4 h-4 text-amber-500"></i>
                            Turbine seu Pedido (Oferta Única)
                        </p>

                        <?php foreach ($product['bumps'] as $bump): ?>
                        <label class="relative block cursor-pointer group mb-2.5 select-none">
                            <input type="checkbox" name="bumps[]" value="<?= $bump['id'] ?>"
                                data-sku="BUMP-<?= $bump['id'] ?>" data-name="<?= htmlspecialchars($bump['title']) ?>"
                                data-price="<?= $bump['price'] ?>" class="peer sr-only"
                                onchange="updateTotalPrice(); toggleBumpVisual(this)">

                            <div class="bump-box relative border-2 border-dashed border-amber-400/60 dark:border-amber-500/40 bg-amber-500/[0.04] p-3.5 sm:p-4 rounded-2xl transition-all duration-200 hover:border-amber-500 peer-checked:border-solid peer-checked:border-emerald-500 peer-checked:bg-emerald-500/[0.08] peer-checked:shadow-lg peer-checked:shadow-emerald-500/10">
                                
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <span class="bump-tag inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-500/20 text-amber-800 dark:text-amber-300 border border-amber-500/30 transition-all duration-200">
                                        <i data-lucide="sparkles" class="w-3 h-3"></i> ADICIONAR COM DESCONTO
                                    </span>
                                    <span class="bump-price text-sm font-black text-amber-600 dark:text-amber-400 transition-colors duration-200">
                                        + R$ <?= format_price($bump['price']) ?>
                                    </span>
                                </div>

                                <div class="flex items-start gap-3">
                                    <div class="bump-icon-box flex h-5 w-5 mt-0.5 shrink-0 items-center justify-center rounded-lg border-2 border-gray-400 dark:border-slate-600 bg-white dark:bg-slate-800 text-white shadow-xs">
                                        <i data-lucide="check" class="w-3.5 h-3.5 stroke-[3]"></i>
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-heading font-bold text-gray-900 dark:text-white text-sm leading-snug">
                                            <?= htmlspecialchars($bump['title']) ?>
                                        </h4>
                                        <p class="text-xs text-gray-600 dark:text-slate-400 leading-relaxed mt-1">
                                            <?= htmlspecialchars($bump['description']) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- SUMMARY & CTA BUTTON -->
                    <div class="border-t border-gray-200 dark:border-slate-800 pt-5">
                        
                        <!-- RESUMO TRANSPARENTE DE VALORES -->
                        <div class="bg-gray-50/90 dark:bg-slate-950/70 rounded-2xl p-4 border border-gray-200/90 dark:border-slate-800/80 mb-5 space-y-2 text-xs">
                            <div class="flex justify-between items-center text-slate-500 dark:text-slate-400">
                                <span>Produto Principal</span>
                                <span id="summary-subtotal" class="font-semibold text-gray-800 dark:text-slate-200">R$ <?= format_price($product['price']) ?></span>
                            </div>
                            <div id="summary-bumps-row" class="hidden justify-between items-center text-slate-500 dark:text-slate-400">
                                <span>Ofertas Adicionais</span>
                                <span id="summary-bumps-total" class="text-emerald-600 dark:text-emerald-400 font-bold">+ R$ 0,00</span>
                            </div>
                            <div class="border-t border-gray-200 dark:border-slate-800/80 pt-2 flex justify-between items-baseline">
                                <span class="text-sm font-bold text-gray-900 dark:text-white">Total a pagar:</span>
                                <span id="checkout-price-display" class="font-heading text-2xl sm:text-3xl font-black text-emerald-600 dark:text-emerald-400 tracking-tight">
                                    R$ <?= format_price($product['price']) ?>
                                </span>
                            </div>
                        </div>

                        <!-- BOTÃO CTA DE ALTA CONVERSÃO -->
                        <button type="submit" id="pay-button"
                            class="cta-button relative overflow-hidden w-full font-heading font-black text-base sm:text-lg py-4 px-6 rounded-2xl uppercase tracking-wider text-white flex items-center justify-center gap-2.5 shadow-2xl active:scale-[0.98] transform transition-all duration-150 cursor-pointer">
                            <span class="shimmer-sweep"></span>
                            <i data-lucide="lock" class="w-5 h-5 shrink-0"></i>
                            <span class="btn-text truncate"><?= htmlspecialchars(!empty($product['checkout_cta_text']) ? $product['checkout_cta_text'] : 'FINALIZAR COMPRA AGORA') ?></span>
                        </button>

                        <p class="text-[11px] text-slate-500 dark:text-slate-400 text-center mt-3 flex items-center justify-center gap-1.5">
                            <i data-lucide="shield-check" class="w-3.5 h-3.5 text-emerald-500"></i>
                            Ao clicar no botão, você concorda com nossos Termos de Uso.
                        </p>

                        <!-- SELOS DE SEGURANÇA E CONFIANÇA -->
                        <div class="mt-5 pt-4 border-t border-gray-100 dark:border-slate-800/80 flex flex-wrap items-center justify-center gap-y-2.5 gap-x-5 text-[11px] text-slate-500 dark:text-slate-400 font-medium">
                            <div class="flex items-center gap-1.5">
                                <i data-lucide="shield-check" class="w-3.5 h-3.5 text-emerald-500"></i>
                                <span>Compra 100% Segura</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i data-lucide="lock" class="w-3.5 h-3.5 text-emerald-500"></i>
                                <span>SSL 256-Bit</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i data-lucide="award" class="w-3.5 h-3.5 text-emerald-500"></i>
                                <span>Garantia de 7 Dias</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i data-lucide="zap" class="w-3.5 h-3.5 text-emerald-500"></i>
                                <span>Acesso Imediato</span>
                            </div>
                        </div>

                    </div>

                </form>

                <!-- PIX WAIT SCREEN (MOBILE FIRST) -->
                <div id="pix-wait-view" class="hidden text-center p-2 sm:p-4">
                    <!-- Injected via JS -->
                </div>

                <!-- SUCCESS SCREEN -->
                <div id="success-view" class="hidden text-center p-3 sm:p-6">
                    <!-- Injected via JS -->
                </div>

            </div>

        </div>

    </div>

    <!-- JAVASCRIPT LOGIC -->
    <script>
        lucide.createIcons();
        const BACKEND_BASE_PATH = '/api';

        // Safe Storage Helper to prevent SecurityError in cross-origin WebViews/iFrames
        const safeStorage = {
            getItem: (key, isSession = false) => {
                try {
                    return isSession ? sessionStorage.getItem(key) : localStorage.getItem(key);
                } catch (e) {
                    return null;
                }
            },
            setItem: (key, val, isSession = false) => {
                try {
                    if (isSession) sessionStorage.setItem(key, val);
                    else localStorage.setItem(key, val);
                    return true;
                } catch (e) {
                    return false;
                }
            }
        };

        // Tell the parent window (host page modal) about the close button setting
        if (window.self !== window.top) {
            window.parent.postMessage({
                type: 'checkout-config',
                showCloseButton: <?= isset($product['show_close_button']) ? ((int)$product['show_close_button'] !== 0 ? 'true' : 'false') : 'true' ?>
            }, '*');
        }

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
                style: <?= json_encode($product['checkout_style'] ?? 'default') ?>,
                showCloseButton: <?= isset($product['show_close_button']) ? ((int)$product['show_close_button'] !== 0 ? 'true' : 'false') : 'true' ?>,
                notifications: {
                    enabled: <?= ($product['fake_notifications'] ?? 0) ? 'true' : 'false' ?>,
                    text: <?= json_encode($product['notification_text'] ?? '') ?>
                },
                topBar: {
                    enabled: <?= ($product['top_bar_enabled'] ?? 0) ? 'true' : 'false' ?>,
                    text: <?= json_encode($product['top_bar_text'] ?? '') ?>,
                    bgColor: <?= json_encode($product['top_bar_bg_color'] ?? '#000000') ?>,
                    textColor: <?= json_encode($product['top_bar_text_color'] ?? '#ffffff') ?>,
                    timer: <?= json_encode($product['top_bar_timer'] ?? '') ?>
                },
                tracking: {
                    initiateCheckout: <?= (int)($product['track_initiate_checkout'] ?? 1) !== 0 ? 'true' : 'false' ?>,
                    addPaymentInfo: <?= (int)($product['track_add_payment_info'] ?? 1) !== 0 ? 'true' : 'false' ?>,
                    purchaseOnPixCopy: <?= (int)($product['track_purchase_on_pix_copy'] ?? 0) !== 0 ? 'true' : 'false' ?>,
                    purchaseOnWhatsAppClick: <?= (int)($product['track_purchase_on_whatsapp_click'] ?? 0) !== 0 ? 'true' : 'false' ?>
                },
                gateway: <?= json_encode($productGateway) ?>,
                supportsCreditCard: <?= $supportsCreditCard ? 'true' : 'false' ?>,
                pixGateway: <?= json_encode($pixGateway) ?>,
                checkout_cta_text: <?= json_encode($product['checkout_cta_text'] ?? '') ?>,
                product_type: <?= json_encode($product['product_type'] ?? 'digital') ?>,
                request_email: <?= (int)($product['request_email'] ?? 1) !== 0 ? 'true' : 'false' ?>,
                pix_instruction_type: <?= json_encode($product['pix_instruction_type'] ?? 'name') ?>,
                pix_whatsapp_button_text: <?= json_encode($product['pix_whatsapp_button_text'] ?? '') ?>,
                pix_whatsapp_number: <?= json_encode($product['pix_whatsapp_number'] ?? '') ?>,
                pix_whatsapp_message: <?= json_encode($product['pix_whatsapp_message'] ?? '') ?>
            }
        };

        const escapeHtml = (text) => {
            if (!text) return '';
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
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

        // Cookie helper
        const getCookie = (name) => {
            try {
                const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
                return match ? match[2] : null;
            } catch (e) {
                return null;
            }
        };

        // Validação de CPF Brasileiro
        const isValidCPF = (cpf) => {
            cpf = (cpf || '').replace(/\D/g, '');
            if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
            let sum = 0, rest;
            for (let i = 1; i <= 9; i++) sum += parseInt(cpf.substring(i - 1, i)) * (11 - i);
            rest = (sum * 10) % 11;
            if (rest === 10 || rest === 11) rest = 0;
            if (rest !== parseInt(cpf.substring(9, 10))) return false;
            sum = 0;
            for (let i = 1; i <= 10; i++) sum += parseInt(cpf.substring(i - 1, i)) * (12 - i);
            rest = (sum * 10) % 11;
            if (rest === 10 || rest === 11) rest = 0;
            if (rest !== parseInt(cpf.substring(10, 11))) return false;
            return true;
        };

        // Método de Pagamento Selecionado (PIX como padrão)
        let currentPaymentMethod = 'pix';

        // Taxas de parcelamento lojista Appmax (PP)
        const merchantRates = {
            1: 0,
            2: 5.98,
            3: 8.97,
            4: 11.96,
            5: 14.95,
            6: 17.94,
            7: 20.93,
            8: 23.92,
            9: 26.91,
            10: 29.90,
            11: 32.89,
            12: 35.88
        };

        const updateInstallmentsList = (totalCents) => {
            const select = document.getElementById('card_installments');
            if (!select) return;

            const baseVal = totalCents / 100;
            const currentSelected = parseInt(select.value, 10) || 1;
            let html = '';

            for (let i = 1; i <= 12; i++) {
                const rate = merchantRates[i] || 0;
                const totalWithInterest = baseVal * (1 + (rate / 100));
                const parcelVal = totalWithInterest / i;
                const selectedAttr = (i === currentSelected) ? 'selected' : '';

                const text = (i === 1)
                    ? `1x de R$ ${formatPriceJS(parcelVal)} (sem juros)`
                    : `${i}x de R$ ${formatPriceJS(parcelVal)} (total R$ ${formatPriceJS(totalWithInterest)})`;

                html += `<option value="${i}" ${selectedAttr}>${text}</option>`;
            }

            select.innerHTML = html;
        };

        const updatePayButtonLabel = () => {
            if (!payButton || payButton.disabled) return;
            const total = calculateCurrentTotal();
            const formatted = formatCurrency(total);
            const customCta = PLANOS['main'].checkout_cta_text;

            if (customCta && customCta.trim() !== '') {
                payButton.innerHTML = `<span class="shimmer-sweep"></span><i data-lucide="lock" class="w-5 h-5 shrink-0"></i> <span class="btn-text truncate">${escapeHtml(customCta)}</span>`;
            } else if (currentPaymentMethod === 'card') {
                payButton.innerHTML = `<span class="shimmer-sweep"></span><i data-lucide="lock" class="w-5 h-5 shrink-0"></i> <span class="btn-text truncate">PAGAR COM CARTÃO ${formatted}</span>`;
            } else {
                payButton.innerHTML = `<span class="shimmer-sweep"></span><i data-lucide="lock" class="w-5 h-5 shrink-0"></i> <span class="btn-text truncate">PAGAR VIA PIX ${formatted}</span>`;
            }
            if (window.lucide) lucide.createIcons();
        };

        window.selectPaymentMethod = (method) => {
            currentPaymentMethod = method;
            const tabCard = document.getElementById('tab-payment-card');
            const tabPix = document.getElementById('tab-payment-pix');
            const cardFields = document.getElementById('card-payment-fields');
            const pixInfo = document.getElementById('pix-payment-info');
            const cpfWrapper = document.getElementById('cpf-field-wrapper');
            const cpfInput = document.getElementById('cpf');

            if (cpfInput) {
                cpfInput.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
            }
            if (paymentErrorDiv) {
                paymentErrorDiv.classList.add('hidden');
            }

            if (method === 'card') {
                if (tabCard) {
                    tabCard.className = 'p-3.5 rounded-2xl border-2 flex items-center justify-center gap-2.5 font-bold text-sm transition-all duration-200 cursor-pointer border-emerald-500 bg-emerald-500/10 text-gray-900 dark:text-white shadow-md shadow-emerald-500/10 ring-1 ring-emerald-500/30';
                }
                if (tabPix) {
                    tabPix.className = 'p-3.5 rounded-2xl border-2 flex items-center justify-center gap-2.5 font-bold text-sm transition-all duration-200 cursor-pointer border-gray-200 dark:border-slate-800 bg-gray-50 dark:bg-slate-950/60 text-slate-500 dark:text-slate-400 hover:border-gray-300 dark:hover:border-slate-700';
                }
                if (cardFields) cardFields.classList.remove('hidden');
                if (pixInfo) pixInfo.classList.add('hidden');
                if (cpfWrapper) cpfWrapper.classList.remove('hidden');
                if (cpfInput) cpfInput.required = true;
            } else {
                if (tabCard) {
                    tabCard.className = 'p-3.5 rounded-2xl border-2 flex items-center justify-center gap-2.5 font-bold text-sm transition-all duration-200 cursor-pointer border-gray-200 dark:border-slate-800 bg-gray-50 dark:bg-slate-950/60 text-slate-500 dark:text-slate-400 hover:border-gray-300 dark:hover:border-slate-700';
                }
                if (tabPix) {
                    tabPix.className = 'p-3.5 rounded-2xl border-2 flex items-center justify-center gap-2.5 font-bold text-sm transition-all duration-200 cursor-pointer border-emerald-500 bg-emerald-500/10 text-gray-900 dark:text-white shadow-md shadow-emerald-500/10 ring-1 ring-emerald-500/30';
                }
                if (cardFields) cardFields.classList.add('hidden');
                if (pixInfo) pixInfo.classList.remove('hidden');

                // Para Pix: se for Appmax exige CPF, se for Woovi não exige CPF
                if (PLANOS['main'].pixGateway === 'appmax') {
                    if (cpfWrapper) cpfWrapper.classList.remove('hidden');
                    if (cpfInput) cpfInput.required = true;
                } else {
                    if (cpfWrapper) cpfWrapper.classList.add('hidden');
                    if (cpfInput) cpfInput.required = false;
                }
            }
            updatePayButtonLabel();
            if (window.lucide) lucide.createIcons();
        };

        // Detecção de Bandeira do Cartão
        const detectCardBrand = (number) => {
            const clean = number.replace(/\D/g, '');
            if (/^4/.test(clean)) return 'visa';
            if (/^(5[1-5]|222[1-9]|22[3-9]|2[3-6]|27[01]|2720)/.test(clean)) return 'mastercard';
            if (/^(4011|4312|4389|4514|4576|5041|5066|5090|6277|6362|6363|6504|6550)/.test(clean)) return 'elo';
            if (/^3[47]/.test(clean)) return 'amex';
            if (/^(606282|3841)/.test(clean)) return 'hipercard';
            return 'generic';
        };

        const updateCardBrandIcon = (brand) => {
            const iconContainer = document.getElementById('card-brand-icon');
            if (!iconContainer) return;

            const brandsMap = {
                visa: '<span class="font-black text-[11px] bg-blue-600 text-white px-2 py-0.5 rounded tracking-wider shadow-sm">VISA</span>',
                mastercard: '<span class="font-black text-[11px] bg-red-600 text-white px-2 py-0.5 rounded tracking-wider shadow-sm">MC</span>',
                elo: '<span class="font-black text-[11px] bg-amber-500 text-black px-2 py-0.5 rounded tracking-wider shadow-sm">ELO</span>',
                amex: '<span class="font-black text-[11px] bg-sky-700 text-white px-2 py-0.5 rounded tracking-wider shadow-sm">AMEX</span>',
                hipercard: '<span class="font-black text-[11px] bg-rose-700 text-white px-2 py-0.5 rounded tracking-wider shadow-sm">HIPER</span>',
                generic: '<i data-lucide="credit-card" class="w-5 h-5 text-slate-500"></i>'
            };

            iconContainer.innerHTML = brandsMap[brand] || brandsMap.generic;
            if (window.lucide) lucide.createIcons();
        };

        // Helpers
        window.toggleBumpVisual = (input) => {
            const bumpBox = input.closest('label')?.querySelector('.bump-box');
            if (!bumpBox) return;
            if (input.checked) {
                bumpBox.classList.add('bump-card-active');
            } else {
                bumpBox.classList.remove('bump-card-active');
            }
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

        ['name', 'email', 'whatsapp', 'cpf', 'card_number'].forEach(id => {
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
            if (checkoutPriceDisplay) checkoutPriceDisplay.textContent = formatCurrency(total);
            updatePayButtonLabel();
            updateInstallmentsList(total);

            // Detalhamento do resumo de valores
            const bumpsRow = document.getElementById('summary-bumps-row');
            const bumpsTotalEl = document.getElementById('summary-bumps-total');
            if (bumpsRow && bumpsTotalEl) {
                let bumpsTotal = 0;
                document.querySelectorAll('input[name="bumps[]"]:checked').forEach(el => {
                    bumpsTotal += Math.round(parseFloat(el.dataset.price) * 100);
                });
                if (bumpsTotal > 0) {
                    bumpsRow.classList.remove('hidden');
                    bumpsRow.classList.add('flex');
                    bumpsTotalEl.textContent = '+ ' + formatCurrency(bumpsTotal);
                } else {
                    bumpsRow.classList.add('hidden');
                    bumpsRow.classList.remove('flex');
                }
            }
        };

        // Inicialização de preço, parcelas e método ativo no carregamento (PIX como padrão)
        document.querySelectorAll('input[name="bumps[]"]').forEach(toggleBumpVisual);
        updateTotalPrice();
        selectPaymentMethod('pix');

        // Form Validation
        const validateForm = () => {
            let isValid = true;
            let errorMessage = 'Preencha todos os campos obrigatórios.';
            paymentErrorDiv.classList.add('hidden');

            // 1. Basic Empty Check
            ['name', 'email', 'whatsapp'].forEach(id => {
                const input = document.getElementById(id);
                if (!input) return;

                input.classList.remove('border-red-500', 'ring-2', 'ring-red-500/20');
                if (!input.value.trim()) {
                    input.classList.add('border-red-500', 'ring-2', 'ring-red-500/20');
                    isValid = false;
                }
            });

            // 2. Strict Email Validation
            const emailInput = document.getElementById('email');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (emailInput && emailInput.value.trim() && !emailRegex.test(emailInput.value.trim())) {
                emailInput.classList.add('border-red-500', 'ring-2', 'ring-red-500/20');
                isValid = false;
                errorMessage = 'Por favor, insira um e-mail válido.';
            }

            // 3. CPF Validation (obrigatório para Cartão ou Pix Appmax)
            const needsCpf = (currentPaymentMethod === 'card') || (currentPaymentMethod === 'pix' && PLANOS['main'].pixGateway === 'appmax');
            const cpfEl = document.getElementById('cpf');
            if (needsCpf && cpfEl) {
                cpfEl.classList.remove('border-red-500', 'ring-2', 'ring-red-500/20');
                if (!isValidCPF(cpfEl.value)) {
                    cpfEl.classList.add('border-red-500', 'ring-2', 'ring-red-500/20');
                    isValid = false;
                    errorMessage = 'Por favor, informe um CPF válido (obrigatório para processamento bancário).';
                }
            }

            // 4. Cartão de Crédito Validation
            if (currentPaymentMethod === 'card') {
                const numEl = document.getElementById('card_number');
                const holderEl = document.getElementById('card_holder');
                const expEl = document.getElementById('card_expiry');
                const cvvEl = document.getElementById('card_cvv');

                [numEl, holderEl, expEl, cvvEl].forEach(el => {
                    if (el) el.classList.remove('border-red-500', 'ring-2', 'ring-red-500/20');
                });

                const cleanNum = (numEl?.value || '').replace(/\D/g, '');
                if (cleanNum.length < 13 || cleanNum.length > 19) {
                    numEl?.classList.add('border-red-500', 'ring-2', 'ring-red-500/20');
                    isValid = false;
                    errorMessage = 'Informe um número de cartão de crédito válido.';
                }

                if (!holderEl || !holderEl.value.trim()) {
                    holderEl?.classList.add('border-red-500', 'ring-2', 'ring-red-500/20');
                    isValid = false;
                    errorMessage = 'Informe o nome impresso no cartão.';
                }

                const expVal = (expEl?.value || '').trim();
                const [expM, expY] = expVal.split('/');
                const monthInt = parseInt(expM, 10);
                if (!expM || !expY || monthInt < 1 || monthInt > 12) {
                    expEl?.classList.add('border-red-500', 'ring-2', 'ring-red-500/20');
                    isValid = false;
                    errorMessage = 'Informe uma data de validade válida (MM/AA).';
                }

                const cleanCvv = (cvvEl?.value || '').replace(/\D/g, '');
                if (cleanCvv.length < 3 || cleanCvv.length > 4) {
                    cvvEl?.classList.add('border-red-500', 'ring-2', 'ring-red-500/20');
                    isValid = false;
                    errorMessage = 'Informe um código de segurança (CVV) válido.';
                }
            }

            // 5. Address Validation (If physical)
            if (PLANOS['main'].product_type === 'physical') {
                ['cep', 'address', 'address_number', 'neighborhood', 'city', 'state'].forEach(id => {
                    const input = document.getElementById(id);
                    if (!input) return;
                    input.classList.remove('border-red-500', 'ring-2', 'ring-red-500/20');
                    if (!input.value.trim()) {
                        input.classList.add('border-red-500', 'ring-2', 'ring-red-500/20');
                        isValid = false;
                    }
                });
            }

            if (!isValid) {
                paymentErrorDiv.textContent = errorMessage;
                paymentErrorDiv.classList.remove('hidden');
                paymentErrorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
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

        // CPF Mask
        const cpfInputField = document.getElementById('cpf');
        if (cpfInputField) {
            cpfInputField.addEventListener('input', (e) => {
                let v = e.target.value.replace(/\D/g, '').substring(0, 11);
                if (v.length > 9) v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
                else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
                else if (v.length > 3) v = v.replace(/(\d{3})(\d{1,3})/, '$1.$2');
                e.target.value = v;
            });
        }

        // Card Masks
        const cardNumberInput = document.getElementById('card_number');
        if (cardNumberInput) {
            cardNumberInput.addEventListener('input', (e) => {
                let v = e.target.value.replace(/\D/g, '').substring(0, 16);
                const brand = detectCardBrand(v);
                updateCardBrandIcon(brand);
                v = v.replace(/(\d{4})(?=\d)/g, '$1 ');
                e.target.value = v;
            });
        }

        const cardExpiryInput = document.getElementById('card_expiry');
        if (cardExpiryInput) {
            cardExpiryInput.addEventListener('input', (e) => {
                let v = e.target.value.replace(/\D/g, '').substring(0, 4);
                if (v.length > 2) v = v.substring(0, 2) + '/' + v.substring(2);
                e.target.value = v;
            });
        }

        const cardCvvInput = document.getElementById('card_cvv');
        if (cardCvvInput) {
            cardCvvInput.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
            });
        }

        // CEP Mask & Lookup
        const cepInput = document.getElementById('cep');
        if (cepInput) {
            cepInput.addEventListener('input', (e) => {
                let v = e.target.value.replace(/\D/g, '');
                v = v.substring(0, 8);
                if (v.length > 5) v = v.substring(0, 5) + '-' + v.substring(5);
                e.target.value = v;

                if (v.replace('-', '').length === 8) {
                    fetch(`https://viacep.com.br/ws/${v.replace('-', '')}/json/`)
                        .then(res => res.json())
                        .then(data => {
                            if (!data.erro) {
                                if (document.getElementById('address')) document.getElementById('address').value = data.logradouro;
                                if (document.getElementById('neighborhood')) document.getElementById('neighborhood').value = data.bairro;
                                if (document.getElementById('city')) document.getElementById('city').value = data.localidade;
                                if (document.getElementById('state')) document.getElementById('state').value = data.uf;
                                if (document.getElementById('address_number')) document.getElementById('address_number').focus();
                            }
                        });
                }
            });
        }

        // Submit Handler
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (payButton.disabled || !validateForm()) return;

            payButton.disabled = true;
            payButton.innerHTML = '<i data-lucide="loader-2" class="animate-spin w-5 h-5"></i> PROCESSANDO COM SEGURANÇA...';
            lucide.createIcons();

            const customerData = {
                name: document.getElementById('name') ? document.getElementById('name').value : '',
                email: document.getElementById('email') ? document.getElementById('email').value : '',
                phone: document.getElementById('whatsapp') ? document.getElementById('whatsapp').value : '',
                document: document.getElementById('cpf') ? document.getElementById('cpf').value.replace(/\D/g, '') : '',
                external_id: new URLSearchParams(window.location.search).get('external_id') || '',
                cep: document.getElementById('cep') ? document.getElementById('cep').value : '',
                address: document.getElementById('address') ? document.getElementById('address').value : '',
                address_number: document.getElementById('address_number') ? document.getElementById('address_number').value : '',
                complement: document.getElementById('complement') ? document.getElementById('complement').value : '',
                neighborhood: document.getElementById('neighborhood') ? document.getElementById('neighborhood').value : '',
                city: document.getElementById('city') ? document.getElementById('city').value : '',
                state: document.getElementById('state') ? document.getElementById('state').value : ''
            };

            if (currentPaymentMethod === 'card') {
                await handleCardPayment(customerData);
            } else {
                await handlePixPayment(customerData);
            }
        });

        // Card Payment Handler (Appmax)
        const handleCardPayment = async (customerData) => {
            const correlationId = generateCorrelationId();
            const mainProduct = PLANOS['main'];
            const allProducts = [{ sku: mainProduct.sku, name: mainProduct.name, price: currentProductPrice, qty: 1 }];

            document.querySelectorAll('input[name="bumps[]"]:checked').forEach(el => {
                allProducts.push({ sku: el.dataset.sku, name: el.dataset.name, price: parseFloat(el.dataset.price), qty: 1 });
            });

            const totalValueInCents = calculateCurrentTotal();
            const totalValue = totalValueInCents / 100;

            const urlParams = new URLSearchParams(window.location.search);
            const trackingParams = {};
            for (const [key, value] of urlParams) {
                trackingParams[key] = value;
            }

            const fbp = getCookie('_fbp') || urlParams.get('parent_fbp') || urlParams.get('fbp') || null;
            const fbc = getCookie('_fbc') || urlParams.get('parent_fbc') || urlParams.get('fbc') || null;
            let fbclid = trackingParams.fbclid || '';
            if (!fbclid && fbc) {
                const parts = fbc.split('.');
                if (parts.length >= 4) fbclid = parts.slice(3).join('.');
            }
            if (fbclid) trackingParams.fbclid = fbclid;
            if (fbp) trackingParams.fbp = fbp;
            if (fbc) trackingParams.fbc = fbc;
            if (typeof PIXEL_ID !== 'undefined') trackingParams.pixel_id = PIXEL_ID;
            trackingParams.user_agent = navigator.userAgent;

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

            const expVal = (document.getElementById('card_expiry')?.value || '').trim();
            const [expMonth, expYear] = expVal.split('/');

            const cardPayload = {
                value: totalValueInCents,
                products: allProducts,
                customer: customerData,
                card: {
                    number: (document.getElementById('card_number')?.value || '').replace(/\D/g, ''),
                    holder_name: (document.getElementById('card_holder')?.value || '').trim(),
                    exp_month: expMonth,
                    exp_year: expYear,
                    cvv: (document.getElementById('card_cvv')?.value || '').replace(/\D/g, ''),
                    installments: parseInt(document.getElementById('card_installments')?.value, 10) || 1
                },
                correlation_id: correlationId,
                gateway: 'appmax',
                tracking: trackingParams
            };

            try {
                const response = await fetch(`${BACKEND_BASE_PATH}/process-card.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(cardPayload)
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    if (typeof fbq === 'function') {
                        fbq('track', 'Purchase', {
                            content_name: allProducts.map(p => p.name).join(', '),
                            content_ids: allProducts.map(p => p.sku),
                            content_type: 'product',
                            value: totalValue,
                            currency: 'BRL',
                            num_items: allProducts.length
                        }, {
                            eventID: correlationId
                        });
                        console.log('[Meta Pixel] Purchase disparado com sucesso após aprovação do cartão!');
                    }

                    form.classList.add('hidden');
                    const downsellModal = document.getElementById('downsell-modal');
                    if (downsellModal) downsellModal.classList.add('hidden');

                    showSuccessView({
                        is_card: true,
                        formattedPrice: formatCurrency(totalValueInCents),
                        order_id: result.order_id
                    });
                } else {
                    paymentErrorDiv.textContent = result.message || 'Transação não autorizada. Verifique os dados do cartão.';
                    paymentErrorDiv.classList.remove('hidden');
                    payButton.disabled = false;
                    updatePayButtonLabel();
                }
            } catch (err) {
                console.error(err);
                paymentErrorDiv.textContent = 'Erro ao processar pagamento com cartão: ' + err.message;
                paymentErrorDiv.classList.remove('hidden');
                payButton.disabled = false;
                updatePayButtonLabel();
            }
        };

        // Pix Logic
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

            const urlParams = new URLSearchParams(window.location.search);
            const trackingParams = {};
            for (const [key, value] of urlParams) {
                trackingParams[key] = value;
            }

            const fbp = getCookie('_fbp') || urlParams.get('parent_fbp') || urlParams.get('fbp') || null;
            const fbc = getCookie('_fbc') || urlParams.get('parent_fbc') || urlParams.get('fbc') || null;

            let fbclid = trackingParams.fbclid || '';
            if (!fbclid && fbc) {
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
                gateway: PLANOS['main'].pixGateway || PLANOS['main'].gateway || 'woovi',
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
                fetch(BACKEND_BASE_PATH + '/meta-s2s-tracking/save_correlation.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        correlation_id: correlationId,
                        fbc: fbc,
                        fbp: fbp,
                        client_user_agent: navigator.userAgent,
                        event_source_url: window.location.href,
                        value: totalValue,
                        currency: 'BRL',
                        pixel_id: PIXEL_ID,
                        product_description: allProducts.map(p => p.name).join(' + ')
                    })
                }).catch(err => console.warn('Meta S2S Warning:', err));
            } catch (e) { console.error(e); }

            try {
                const response = await fetch(`${BACKEND_BASE_PATH}/process-pix.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(pixPayload)
                });

                let result;
                if (response.status === 404) {
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

                pixPaymentState = {
                    correlationId: result.correlationId,
                    is_manual: !!(result.is_manual || (result.pixData && result.pixData.is_manual) || PLANOS['main'].gateway === 'manual_pix'),
                    pixData: result.pixData,
                    customer: customerData
                };

                showPixWaitView(result.pixData, result.correlationId, customerData);

            } catch (error) {
                console.error(error);
                paymentErrorDiv.textContent = 'Erro ao processar pagamento: ' + error.message;
                paymentErrorDiv.classList.remove('hidden');
                payButton.disabled = false;
                payButton.textContent = 'TENTAR NOVAMENTE';
            }
        };

        // Disparo centralizado de Purchase com deduplicação de 24h
        const firePurchaseTracking = (triggerSource) => {
            const sku = PLANOS['main'].sku || 'default';
            const now = Date.now();
            const twentyFourHoursMs = 24 * 60 * 60 * 1000;
            const correlationId = (pixPaymentState && pixPaymentState.correlationId) ? pixPaymentState.correlationId : ('pix_' + now);

            const skuStorageKey = 'pix_purchase_fired_' + sku;
            const corrStorageKey = 'pix_purchase_fired_' + correlationId;
            const legacyKey = 'pix_copy_purchase_' + sku;

            const lastTimestamp = safeStorage.getItem(skuStorageKey) || safeStorage.getItem(legacyKey) || safeStorage.getItem(corrStorageKey);

            if (lastTimestamp && (now - parseInt(lastTimestamp, 10)) < twentyFourHoursMs) {
                console.log(`[Meta Pixel] Purchase (${triggerSource}) já disparado nas últimas 24h.`);
                return;
            }

            safeStorage.setItem(skuStorageKey, now.toString());
            safeStorage.setItem(corrStorageKey, now.toString());
            safeStorage.setItem(legacyKey, now.toString());

            const totalValueInCents = calculateCurrentTotal();
            const totalValue = totalValueInCents / 100;
            const mainProduct = PLANOS['main'];
            const allProducts = [{ sku: mainProduct.sku, name: mainProduct.name, price: currentProductPrice, qty: 1 }];

            document.querySelectorAll('input[name="bumps[]"]:checked').forEach(el => {
                allProducts.push({ sku: el.dataset.sku, name: el.dataset.name, price: parseFloat(el.dataset.price), qty: 1 });
            });

            if (typeof fbq === 'function') {
                fbq('track', 'Purchase', {
                    content_name: allProducts.map(p => p.name).join(', '),
                    content_ids: allProducts.map(p => p.sku),
                    content_type: 'product',
                    value: totalValue,
                    currency: 'BRL',
                    num_items: allProducts.length
                }, {
                    eventID: correlationId
                });
                console.log(`[Meta Pixel] Evento Purchase disparado com sucesso (${triggerSource})!`);
            }

            try {
                const urlParams = new URLSearchParams(window.location.search);
                const fbcVal = getCookie('_fbc') || urlParams.get('parent_fbc') || urlParams.get('fbc') || null;
                const fbpVal = getCookie('_fbp') || urlParams.get('parent_fbp') || urlParams.get('fbp') || null;

                fetch(BACKEND_BASE_PATH + '/meta-s2s-tracking/save_correlation.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        correlation_id: correlationId,
                        event_name: 'Purchase',
                        fbc: fbcVal,
                        fbp: fbpVal,
                        client_user_agent: navigator.userAgent,
                        event_source_url: window.location.href,
                        value: totalValue,
                        currency: 'BRL',
                        pixel_id: PIXEL_ID,
                        product_description: allProducts.map(p => p.name).join(' + ')
                    })
                }).catch(err => console.warn('Meta S2S Warning:', err));
            } catch (e) { console.error(e); }
        };

        const trackPixCopyPurchase = () => {
            if (!PLANOS['main'].tracking || !PLANOS['main'].tracking.purchaseOnPixCopy) return;
            firePurchaseTracking('ao copiar Pix');
        };

        const trackWhatsAppReceiptPurchase = () => {
            if (!PLANOS['main'].tracking || !PLANOS['main'].tracking.purchaseOnWhatsAppClick) return;
            firePurchaseTracking('ao clicar no WhatsApp');
        };
        window.trackWhatsAppReceiptClick = trackWhatsAppReceiptPurchase;

        const recordPixCopyEvent = () => {
            if (pixPaymentState && pixPaymentState.correlationId) {
                try {
                    fetch('api/track_pix_copy.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            correlation_id: pixPaymentState.correlationId
                        })
                    }).then(res => res.json()).then(data => {
                        console.log('[Pix Copy] Status registrado no backend:', data);
                    }).catch(err => {
                        console.warn('[Pix Copy] Aviso:', err);
                    });
                } catch (e) {
                    console.warn('[Pix Copy] Exceção:', e);
                }
            }
        };
        window.recordPixCopyEvent = recordPixCopyEvent;

        // Copy to clipboard helper with haptic feedback
        window.copyToClipboard = (text, element) => {
            recordPixCopyEvent();

            if (PLANOS['main'].tracking && PLANOS['main'].tracking.purchaseOnPixCopy) {
                trackPixCopyPurchase();
            }

            // Haptic vibration feedback on mobile
            if (typeof navigator !== 'undefined' && navigator.vibrate) {
                try { navigator.vibrate([40, 30, 40]); } catch (e) {}
            }

            const btn = element || document.getElementById('btn-copy-pix') || document.getElementById('btn-copy-pix-main');
            if (!btn) return;
            const original = btn.innerHTML;

            const updateButton = () => {
                const isMainBtn = btn.id === 'btn-copy-pix-main';
                if (isMainBtn) {
                    btn.innerHTML = '<span class="shimmer-sweep"></span><i data-lucide="check-circle-2" class="w-5 h-5 text-white"></i> <span>PIX COPIADO COM SUCESSO!</span>';
                    btn.classList.add('bg-emerald-600', 'ring-2', 'ring-white/40');
                } else {
                    btn.innerHTML = '<i data-lucide="check" class="w-3.5 h-3.5 inline"></i> COPIADO!';
                }
                if (typeof lucide !== 'undefined') lucide.createIcons();

                setTimeout(() => {
                    btn.innerHTML = original;
                    if (isMainBtn) {
                        btn.classList.remove('bg-emerald-600', 'ring-2', 'ring-white/40');
                    }
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }, 2800);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(updateButton).catch(err => {
                    console.error('navigator.clipboard failed, fallback used', err);
                    fallbackCopyToClipboard(text, updateButton);
                });
            } else {
                fallbackCopyToClipboard(text, updateButton);
            }
        };

        function fallbackCopyToClipboard(text, callback) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
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
                console.error('Fallback error', err);
            }

            document.body.removeChild(textArea);
        }

        // REDESIGNED PIX WAIT VIEW (MOBILE-FIRST HIGH CONVERTING)
        const showPixWaitView = (pixData, correlationId, customerData) => {
            form.classList.add('hidden');
            
            // Header Update
            const header = document.getElementById('checkout-step-header');
            if (header) {
                header.classList.remove('hidden');
                header.innerHTML = `
                    <div class="w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center font-heading font-black text-sm shrink-0 shadow-sm animate-pulse">
                        <i data-lucide="clock" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h2 class="font-heading text-base sm:text-lg font-black text-gray-900 dark:text-white leading-tight">
                            Aguardando Pagamento
                        </h2>
                        <p class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">
                            Conclua seu Pix para liberar o acesso imediatamente
                        </p>
                    </div>
                `;
            }

            const isManualPix = !!(
                pixData.is_manual || 
                pixData.whatsapp_url || 
                (pixPaymentState && pixPaymentState.is_manual) || 
                (PLANOS['main'] && (PLANOS['main'].gateway === 'manual_pix' || PLANOS['main'].gateway === 'pix_manual' || PLANOS['main'].gateway === 'direct_pix'))
            );
            const isEmailRequired = !!(
                (PLANOS['main'] && PLANOS['main'].request_email) || 
                document.getElementById('email')
            );
            const instructionType = pixData.pix_instruction_type || (PLANOS['main'] && PLANOS['main'].pix_instruction_type) || 'name';

            let whatsappInstructionStep = '';
            let whatsappBtnText = '';

            if (instructionType === 'comprovante') {
                whatsappInstructionStep = 'Clique no botão abaixo para <strong>enviar o comprovante no WhatsApp</strong> e liberar seu produto!';
                whatsappBtnText = 'ENVIAR COMPROVANTE NO WHATSAPP';
            } else {
                whatsappInstructionStep = 'Clique no botão abaixo para <strong>informar seu nome completo no WhatsApp</strong> e liberar seu produto!';
                whatsappBtnText = 'INFORMAR NOME NO WHATSAPP';
            }

            if (pixData.whatsapp_button_text && pixData.whatsapp_button_text.trim() !== '') {
                whatsappBtnText = pixData.whatsapp_button_text.trim();
            } else if (PLANOS['main'] && PLANOS['main'].pix_whatsapp_button_text && PLANOS['main'].pix_whatsapp_button_text.trim() !== '') {
                whatsappBtnText = PLANOS['main'].pix_whatsapp_button_text.trim();
            }

            const whatsappSectionHtml = (isManualPix && pixData.whatsapp_url) ? `
                <div class="mt-4 p-4 bg-emerald-50/90 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-500/40 rounded-2xl text-left shadow-xs">
                    <div class="flex items-center gap-2 mb-2 text-emerald-800 dark:text-emerald-300 font-heading font-bold text-xs uppercase tracking-wide">
                        <i data-lucide="shield-check" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
                        <span>Instruções para Liberação do Acesso:</span>
                    </div>
                    <ol class="text-xs text-slate-700 dark:text-slate-300 space-y-1.5 list-decimal list-inside pl-0.5 leading-relaxed">
                        <li>Copie o código Pix ou escaneie o QR Code no seu banco.</li>
                        <li>Efetue o pagamento de <strong class="text-slate-900 dark:text-white font-bold">${pixData.formattedPrice}</strong>.</li>
                        <li>${whatsappInstructionStep}</li>
                    </ol>
                </div>
                <a href="${pixData.whatsapp_url}" target="_blank" rel="noopener" onclick="window.trackWhatsAppReceiptClick()"
                    class="mt-3.5 w-full bg-[#25D366] hover:bg-[#1EBE5D] text-white font-heading font-black py-4 px-5 rounded-2xl text-sm sm:text-base transition flex items-center justify-center gap-2.5 shadow-xl hover:shadow-emerald-500/25 active:scale-[0.98]">
                    <i data-lucide="message-circle" class="w-5 h-5"></i>
                    <span>${whatsappBtnText}</span>
                </a>
            ` : '';

            let emailInstructionHtml = '';
            if (!isManualPix && isEmailRequired) {
                const currentEmail = (customerData && customerData.email) 
                    || (document.getElementById('email') && document.getElementById('email').value.trim()) 
                    || (pixPaymentState && pixPaymentState.customer && pixPaymentState.customer.email) 
                    || '';
                const emailDisplay = (currentEmail && !currentEmail.includes('@naoinformado.com'))
                    ? ` no e-mail <strong class="text-blue-900 dark:text-blue-200 font-bold underline decoration-blue-300/50">${escapeHtml(currentEmail)}</strong>`
                    : ' no seu e-mail cadastrado';

                emailInstructionHtml = `
                    <div class="w-full mt-4 p-4 bg-blue-50/90 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-500/30 rounded-2xl text-left shadow-xs">
                        <div class="flex items-center gap-2 mb-1.5 text-blue-800 dark:text-blue-300 font-heading font-bold text-xs uppercase tracking-wide">
                            <i data-lucide="mail" class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0"></i>
                            <span>Envio Automático por E-mail</span>
                        </div>
                        <p class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
                            Assim que o pagamento do Pix for confirmado, você receberá seus dados de acesso diretamente${emailDisplay}.
                        </p>
                        <div class="mt-2.5 pt-2.5 border-t border-blue-200/80 dark:border-blue-900/50 flex items-start gap-1.5 text-[11px] text-slate-600 dark:text-slate-400">
                            <i data-lucide="info" class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5"></i>
                            <span>Caso não localize em alguns minutos, verifique sua pasta de <strong class="text-slate-800 dark:text-slate-200">Spam</strong> ou <strong class="text-slate-800 dark:text-slate-200">Lixo Eletrônico</strong>.</span>
                        </div>
                    </div>
                `;
            }

            pixWaitView.innerHTML = `
                <div class="text-center py-1 sm:py-2">
                    <!-- Status Badge -->
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-700 dark:text-emerald-400 text-xs font-bold uppercase tracking-wider mb-3">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                        Pedido Gerado com Sucesso
                    </div>

                    <h3 class="font-heading text-xl sm:text-2xl font-black text-gray-900 dark:text-white mb-1 tracking-tight">
                        Falta apenas 1 passo!
                    </h3>
                    <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 mb-4 max-w-sm mx-auto leading-relaxed">
                        Conclua seu Pix abaixo para liberar seu acesso imediatamente.
                    </p>

                    <!-- Urgency Countdown Card -->
                    <div class="bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 rounded-xl p-2.5 mb-4 max-w-md mx-auto flex items-center justify-center gap-2 text-xs font-bold text-amber-800 dark:text-amber-300">
                        <i data-lucide="clock" class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0"></i>
                        <span>Seu código Pix expira em: <strong id="pix-timer-countdown" class="font-mono text-sm font-black text-amber-700 dark:text-amber-400">14:59</strong></span>
                    </div>

                    <!-- HERO COPY BUTTON (MOBILE FIRST SUPREMACY) -->
                    <div class="space-y-2 mb-4 max-w-md mx-auto">
                        <button id="btn-copy-pix-main" onclick="copyToClipboard('${pixData.brCode}', this)"
                            class="cta-button relative overflow-hidden w-full py-4 px-5 rounded-2xl text-white font-heading font-black text-base uppercase tracking-wider flex items-center justify-center gap-2.5 shadow-2xl active:scale-[0.98] transition">
                            <span class="shimmer-sweep"></span>
                            <i data-lucide="copy" class="w-5 h-5 shrink-0"></i>
                            <span>COPIAR CÓDIGO PIX</span>
                        </button>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            Toque no botão acima para copiar o código e colar no app do seu banco.
                        </p>
                    </div>

                    <!-- Valor & Campo de Código Reduzido -->
                    <div class="bg-white dark:bg-slate-950/80 rounded-2xl p-4 border border-gray-200 dark:border-slate-800 mb-4 max-w-md mx-auto text-left shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Valor a Pagar</span>
                            <span class="font-heading text-xl font-black text-emerald-600 dark:text-emerald-400">${pixData.formattedPrice}</span>
                        </div>
                        <div class="flex items-center gap-2 bg-gray-50 dark:bg-slate-900 p-2.5 rounded-xl border border-gray-200 dark:border-slate-800">
                            <input readonly value="${pixData.brCode}" oncopy="window.recordPixCopyEvent()" onclick="this.select()"
                                class="bg-transparent text-xs text-gray-700 dark:text-slate-300 w-full outline-none font-mono truncate cursor-pointer"
                                title="Clique para selecionar">
                            <button id="btn-copy-pix" onclick="copyToClipboard('${pixData.brCode}', this)"
                                class="px-3 py-1.5 rounded-lg bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 font-bold text-xs hover:bg-emerald-500/25 transition uppercase shrink-0">
                                Copiar
                            </button>
                        </div>
                    </div>

                    <!-- Passo a Passo 3 Etapas -->
                    <div class="bg-gray-50/80 dark:bg-slate-950/50 rounded-2xl p-4 border border-gray-200 dark:border-slate-800 mb-4 max-w-md mx-auto text-left">
                        <h4 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-3 flex items-center gap-2">
                            <i data-lucide="help-circle" class="w-4 h-4 text-emerald-500 dark:text-emerald-400"></i> Como pagar com Pix:
                        </h4>
                        <div class="space-y-2.5 text-xs text-gray-600 dark:text-slate-300">
                            <div class="flex items-start gap-2.5">
                                <span class="w-5 h-5 rounded-full bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 flex items-center justify-center font-bold text-[11px] shrink-0 border border-emerald-500/30">1</span>
                                <span>Copie o <strong>Código Pix</strong> no botão acima ou aponte a câmera para o QR Code abaixo.</span>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <span class="w-5 h-5 rounded-full bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 flex items-center justify-center font-bold text-[11px] shrink-0 border border-emerald-500/30">2</span>
                                <span>Abra o app do seu <strong>Banco</strong> e escolha a opção <strong>Pix Copia e Cola</strong> ou <strong>Ler QR Code</strong>.</span>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <span class="w-5 h-5 rounded-full bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 flex items-center justify-center font-bold text-[11px] shrink-0 border border-emerald-500/30">3</span>
                                <span>Confirme o valor e pronto! O acesso é liberado automaticamente.</span>
                            </div>
                        </div>
                    </div>

                    <!-- QR Code Pix (Sempre Visível em Todos os Dispositivos) -->
                    <div class="bg-gray-50/80 dark:bg-slate-950/50 rounded-2xl p-4 sm:p-5 border border-gray-200 dark:border-slate-800 mb-4 max-w-md mx-auto text-center shadow-xs">
                        <div class="flex items-center justify-center gap-2 mb-3 text-xs font-bold uppercase tracking-wider text-gray-800 dark:text-slate-200">
                            <i data-lucide="qr-code" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
                            <span>Pague com QR Code</span>
                        </div>
                        <div class="bg-white p-3 rounded-2xl inline-block shadow-md mb-2.5 border border-gray-200/80 dark:border-slate-700">
                            <img src="${pixData.qrCodeImage}" class="w-44 h-44 sm:w-48 sm:h-48 mx-auto object-contain block" alt="QR Code Pix">
                        </div>
                        <p class="text-xs text-gray-600 dark:text-slate-400 leading-relaxed max-w-xs mx-auto">
                            Abra o app do seu banco e aponte a câmera para o QR Code acima.
                        </p>
                    </div>

                    ${whatsappSectionHtml}
                    ${emailInstructionHtml}

                    <!-- LIVE RADAR POLLING INDICATOR -->
                    <div class="mt-5 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 max-w-md mx-auto flex items-center justify-center gap-2 text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                        </span>
                        <span>Aguardando identificação do pagamento em tempo real...</span>
                    </div>
                </div>
            `;

            pixWaitView.classList.remove('hidden');
            lucide.createIcons();

            // Start 15 min countdown for PIX
            (function() {
                let timeLeft = 15 * 60;
                const timerDisplay = document.getElementById('pix-timer-countdown');
                if (!timerDisplay) return;
                const interval = setInterval(() => {
                    timeLeft--;
                    if (timeLeft <= 0) {
                        clearInterval(interval);
                        timerDisplay.textContent = '00:00';
                        return;
                    }
                    const m = Math.floor(timeLeft / 60);
                    const s = timeLeft % 60;
                    timerDisplay.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                }, 1000);
            })();

            // Polling Real de Confirmação
            const pollInterval = setInterval(async () => {
                try {
                    const res = await fetch('api/check_payment_status_ebook.php?correlationId=' + correlationId);
                    const data = await res.json();
                    if (data.status === 'PAID' || data.status === 'COMPLETED') {
                        clearInterval(pollInterval);
                        showSuccessView(pixData);
                    }
                } catch (e) {
                    console.error('Polling error', e);
                }
            }, 5000);
        };

        // REDESIGNED SUCCESS VIEW
        const showSuccessView = (currentPixData) => {
            const dataToUse = currentPixData || (pixPaymentState && pixPaymentState.pixData) || {};
            const isWhatsAppDelivery = !!(dataToUse.whatsapp_delivery || (PLANOS['main'] && PLANOS['main'].gateway === 'woovi_whatsapp' && (dataToUse.whatsapp_url || PLANOS['main'].pix_whatsapp_number)));

            const header = document.getElementById('checkout-step-header');
            if (header) {
                header.classList.remove('hidden');
                header.innerHTML = `
                    <div class="w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center font-heading font-black text-sm shrink-0">
                        <i data-lucide="check" class="w-4 h-4 stroke-[3]"></i>
                    </div>
                    <div>
                        <h2 class="font-heading text-base sm:text-lg font-black text-gray-900 dark:text-white leading-tight">
                            Pagamento Aprovado!
                        </h2>
                        <p class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">
                            Seu pedido foi confirmado com sucesso
                        </p>
                    </div>
                `;
            }

            pixWaitView.classList.add('hidden');

            if (dataToUse.is_card) {
                successView.innerHTML = `
                    <div class="text-center py-4 sm:py-6 max-w-md mx-auto">
                        <div class="flex justify-center mb-4">
                            <div class="w-20 h-20 rounded-full bg-emerald-500/15 border-2 border-emerald-500/40 flex items-center justify-center shadow-xl shadow-emerald-500/20">
                                <i data-lucide="check-circle-2" class="w-12 h-12 text-emerald-500 dark:text-emerald-400"></i>
                            </div>
                        </div>
                        <h2 class="font-heading text-2xl sm:text-3xl font-black text-gray-900 dark:text-white mb-2">PAGAMENTO APROVADO!</h2>
                        <p class="text-gray-600 dark:text-slate-300 text-xs sm:text-sm mb-5 leading-relaxed">
                            Seu pagamento no cartão de crédito foi processado e autorizado com sucesso.
                        </p>

                        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-500/40 rounded-2xl text-left shadow-sm mb-4">
                            <div class="flex items-center gap-2 mb-2 text-emerald-800 dark:text-emerald-300 font-heading font-bold text-xs uppercase tracking-wide">
                                <i data-lucide="shield-check" class="w-4 h-4 text-emerald-500 dark:text-emerald-400"></i>
                                <span>Pedido Concluído:</span>
                            </div>
                            <p class="text-xs text-emerald-950 dark:text-slate-200 leading-relaxed">
                                ${PLANOS['main'].product_type === 'physical' 
                                    ? 'Seu pedido foi confirmado e a preparação do envio já foi iniciada.' 
                                    : 'Os detalhes de acesso ao seu produto foram enviados imediatamente para o seu e-mail cadastrado.'}
                            </p>
                        </div>
                    </div>
                `;
            } else if (isWhatsAppDelivery) {
                const instructionType = dataToUse.pix_instruction_type || (PLANOS['main'] && PLANOS['main'].pix_instruction_type) || 'name';
                let instructionStepText = '';
                let whatsappBtnText = '';

                if (instructionType === 'comprovante') {
                    instructionStepText = 'Clique no botão verde abaixo para <strong>enviar seu comprovante no WhatsApp</strong> e liberar seu produto imediatamente:';
                    whatsappBtnText = 'ENVIAR COMPROVANTE NO WHATSAPP';
                } else {
                    instructionStepText = 'Clique no botão verde abaixo para <strong>informar seu nome no WhatsApp</strong> e liberar seu acesso imediatamente:';
                    whatsappBtnText = 'RECEBER MEU ACESSO NO WHATSAPP';
                }

                if (dataToUse.whatsapp_button_text && dataToUse.whatsapp_button_text.trim() !== '') {
                    whatsappBtnText = dataToUse.whatsapp_button_text.trim();
                } else if (PLANOS['main'] && PLANOS['main'].pix_whatsapp_button_text && PLANOS['main'].pix_whatsapp_button_text.trim() !== '') {
                    whatsappBtnText = PLANOS['main'].pix_whatsapp_button_text.trim();
                }

                let targetWhatsappUrl = dataToUse.whatsapp_url || '';
                if (!targetWhatsappUrl && PLANOS['main'] && PLANOS['main'].pix_whatsapp_number) {
                    const cleanPhone = (PLANOS['main'].pix_whatsapp_number || '').replace(/\D/g, '');
                    const cleanWithDDI = (cleanPhone.length >= 10 && cleanPhone.length <= 11) ? ('55' + cleanPhone) : cleanPhone;
                    const defaultMsg = instructionType === 'comprovante' 
                        ? 'Olá! Meu pagamento foi aprovado. Segue meu comprovante:'
                        : 'Olá! Meu pagamento foi aprovado. Vim receber meu acesso!';
                    targetWhatsappUrl = `https://wa.me/${cleanWithDDI}?text=${encodeURIComponent(defaultMsg)}`;
                }

                successView.innerHTML = `
                    <div class="text-center py-4 sm:py-6 max-w-md mx-auto">
                        <div class="flex justify-center mb-4">
                            <div class="w-20 h-20 rounded-full bg-emerald-500/15 border-2 border-emerald-500/40 flex items-center justify-center shadow-xl shadow-emerald-500/20">
                                <i data-lucide="check-circle-2" class="w-12 h-12 text-emerald-500 dark:text-emerald-400"></i>
                            </div>
                        </div>
                        <h2 class="font-heading text-2xl sm:text-3xl font-black text-gray-900 dark:text-white mb-2">PAGAMENTO APROVADO!</h2>
                        <p class="text-gray-600 dark:text-slate-300 text-xs sm:text-sm mb-5 leading-relaxed">
                            Identificamos a confirmação do seu pagamento com sucesso.
                        </p>

                        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-500/40 rounded-2xl text-left shadow-sm mb-5">
                            <div class="flex items-center gap-2 mb-2 text-emerald-800 dark:text-emerald-300 font-heading font-bold text-xs uppercase tracking-wide">
                                <i data-lucide="gift" class="w-4 h-4 text-emerald-500 dark:text-emerald-400"></i>
                                <span>Liberar meu Acesso / Produto:</span>
                            </div>
                            <p class="text-xs text-emerald-950 dark:text-slate-200 mb-2 leading-relaxed">
                                ${instructionStepText}
                            </p>
                            <div class="text-[11px] text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5 pt-2 border-t border-emerald-200 dark:border-emerald-800/50">
                                <i data-lucide="clock" class="w-3.5 h-3.5 text-emerald-500 dark:text-emerald-400 shrink-0"></i>
                                <span>Nossa equipe está disponível para atender você agora mesmo.</span>
                            </div>
                        </div>

                        <a href="${targetWhatsappUrl}" target="_blank" rel="noopener" onclick="window.trackWhatsAppReceiptClick()"
                            class="w-full bg-[#25D366] hover:bg-[#1EBE5D] text-white font-heading font-black py-4 px-6 rounded-2xl text-base transition flex items-center justify-center gap-2.5 shadow-2xl hover:shadow-emerald-500/30 active:scale-[0.98]">
                            <i data-lucide="message-circle" class="w-6 h-6"></i>
                            <span>${whatsappBtnText}</span>
                        </a>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 text-center mt-3">
                            Caso a conversa não abra automaticamente, verifique se o WhatsApp está instalado.
                        </p>
                    </div>
                `;
            } else {
                successView.innerHTML = `
                    <div class="text-center py-4 sm:py-6 max-w-md mx-auto">
                        <div class="flex justify-center mb-4">
                            <div class="w-20 h-20 rounded-full bg-emerald-500/15 border-2 border-emerald-500/40 flex items-center justify-center shadow-xl shadow-emerald-500/20">
                                <i data-lucide="check-circle-2" class="w-12 h-12 text-emerald-500 dark:text-emerald-400"></i>
                            </div>
                        </div>
                        <h2 class="font-heading text-2xl sm:text-3xl font-black text-gray-900 dark:text-white mb-2">PAGAMENTO APROVADO!</h2>
                        <p class="text-gray-600 dark:text-slate-300 text-xs sm:text-sm mb-5 leading-relaxed">
                            <?= ($product['product_type'] ?? 'digital') === 'physical' ? 'Seu pedido foi confirmado e você receberá em até 7 dias.' : 'Seu acesso foi liberado e enviado para o seu e-mail.' ?>
                        </p>
                    </div>
                `;
            }

            successView.classList.remove('hidden');
            lucide.createIcons();
        };

        // DOWNSELL LOGIC
        function showDownsell() {
            if (!downsellEnabled || downsellTriggered || downsellAccepted) return;
            
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

            if (window.self !== window.top) {
                window.parent.postMessage('downsell-opened', '*');
            }
        }

        function acceptDownsell() {
            downsellAccepted = true;
            
            if (downsellType === 'fixed') {
                currentProductPrice = Math.max(0, baseProductPrice - downsellAmount);
            } else {
                currentProductPrice = baseProductPrice * (1 - (downsellAmount / 100));
            }

            const productPriceEls = document.querySelectorAll('.text-emerald-400, .text-green-400');
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
            setTimeout(() => modal.classList.add('hidden'), 250);

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
                history.pushState(null, null, window.location.href);
            }
        });

        // Trigger: Message from Parent (Modal Close Click)
        window.addEventListener('message', function (event) {
            if (event.data === 'trigger-downsell') {
                if (downsellEnabled && !downsellTriggered && !downsellAccepted) {
                    showDownsell();
                    if (window.self !== window.top) {
                        window.parent.postMessage('downsell-opened', '*');
                    }
                } else {
                    window.parent.postMessage('close-checkout-modal', '*');
                }
            }
        });

        // Auto-resize observer for iFrame embeds
        if (window.self !== window.top) {
            const sendHeight = () => {
                window.parent.postMessage({
                    type: 'checkout-resize',
                    height: document.documentElement.scrollHeight || document.body.scrollHeight
                }, '*');
            };
            
            const resizeObserver = new ResizeObserver(() => sendHeight());
            resizeObserver.observe(document.body);
            
            window.addEventListener('load', sendHeight);
            window.addEventListener('resize', sendHeight);
            
            const formElement = document.getElementById('checkout-form');
            if (formElement) {
                formElement.addEventListener('input', sendHeight);
                formElement.addEventListener('change', sendHeight);
            }
        }

        // REAL-TIME VISITOR HEARTBEAT
        (function() {
            let sessionId = safeStorage.getItem('checkout_session_id', true);
            if (!sessionId) {
                sessionId = 'sess_' + Math.random().toString(36).substring(2, 15) + '_' + Date.now().toString(36);
                safeStorage.setItem('checkout_session_id', sessionId, true);
            }

            let lastInputTime = 0;
            const productSlug = PLANOS['main'] ? PLANOS['main'].sku : '';

            const updateActivity = () => {
                lastInputTime = Date.now();
            };

            const formInputs = document.querySelectorAll('#checkout-form input, #checkout-form select, #checkout-form textarea');
            formInputs.forEach(input => {
                input.addEventListener('input', updateActivity);
                input.addEventListener('change', updateActivity);
                input.addEventListener('focus', updateActivity);
            });

            const sendPing = () => {
                const status = (Date.now() - lastInputTime < 8000) ? 'typing' : 'viewing';
                
                fetch(BACKEND_BASE_PATH + '/v1/heartbeat.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        session_id: sessionId,
                        status: status,
                        product_slug: productSlug
                    }),
                    keepalive: true
                }).catch(err => {});
            };

            sendPing();
            setInterval(sendPing, 5000);

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') {
                    const payload = JSON.stringify({
                        session_id: sessionId,
                        status: 'viewing',
                        product_slug: productSlug
                    });
                    if (navigator.sendBeacon) {
                        navigator.sendBeacon(BACKEND_BASE_PATH + '/v1/heartbeat.php', payload);
                    }
                } else {
                    sendPing();
                }
            });
        })();
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
                const bgClass = isDark ? 'bg-slate-900 border-slate-700 text-white shadow-2xl shadow-black/60' : 'bg-white border-gray-100 text-gray-900 shadow-2xl';

                notif.className = `fixed bottom-4 left-4 right-4 sm:right-auto sm:w-80 p-3.5 rounded-2xl border flex items-center gap-3 transform translate-y-20 opacity-0 transition-all duration-500 z-50 ${bgClass}`;
                notif.innerHTML = `
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-emerald-500 flex items-center justify-center shadow-md shadow-emerald-500/20">
                    <i data-lucide="check-circle" class="w-5 h-5 text-white"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold leading-tight text-white">${textContent}</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">Há ${minutes} minutos</p>
                </div>
                <button class="opacity-50 hover:opacity-100 p-1 shrink-0" onclick="this.parentElement.remove()">
                    <i data-lucide="x" class="w-3.5 h-3.5"></i>
                </button>
            `;

                document.body.appendChild(notif);
                lucide.createIcons();

                requestAnimationFrame(() => {
                    notif.classList.remove('translate-y-20', 'opacity-0');
                });

                setTimeout(() => {
                    notif.classList.add('translate-y-20', 'opacity-0');
                    setTimeout(() => notif.remove(), 500);
                }, 5000);
            }

            function scheduleNext() {
                const delay = Math.floor(Math.random() * (30000 - 10000 + 1)) + 10000;
                setTimeout(() => {
                    createNotification();
                    scheduleNext();
                }, delay);
            }

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
                const dateStr = now.toLocaleDateString('pt-BR', options);

                let processed = text.replace(/{datahoje}/gi, dateStr);
                processed = processed.replace(/{datahojemaiusculo}/gi, dateStr.toUpperCase());
                processed = processed.replace(/{datahojeminusculo}/gi, dateStr.toLowerCase());
                processed = processed.replace(/{datahojecapitalizado}/gi, dateStr.replace(/\b\w/g, l => l.toUpperCase()));

                const future = new Date(now.getTime() + (2 * 60 * 60 * 1000));
                const hours2 = new Intl.DateTimeFormat('pt-BR', {
                    hour: '2-digit',
                    hour12: false,
                    timeZone: 'America/Sao_Paulo'
                }).format(future);
                processed = processed.replace(/{2horas}/gi, hours2 + 'H');
                processed = processed.replace(/{timer}/gi, '<span id="top-bar-timer-countdown"></span>');

                return processed;
            }

            const bar = document.createElement('div');
            bar.className = 'w-full py-2.5 px-4 text-center text-xs sm:text-sm font-black uppercase tracking-wide z-[9999] fixed top-0 left-0 shadow-lg top-bar-container';
            bar.style.backgroundColor = config.bgColor;
            bar.style.color = config.textColor;
            bar.innerHTML = parseDateShortcodes(config.text);

            document.body.prepend(bar);

            const timerStr = config.timer ? config.timer.trim() : '';
            const timerEl = document.getElementById('top-bar-timer-countdown');

            if (timerEl && /^[0-9]+:[0-5][0-9]$/.test(timerStr)) {
                const parts = timerStr.split(':');
                const minutes = parseInt(parts[0], 10);
                const seconds = parseInt(parts[1], 10);
                const durationInSeconds = minutes * 60 + seconds;

                if (durationInSeconds > 0) {
                    const storageKey = 'checkout_timer_' + PLANOS.main.sku;
                    let endTime;

                    try {
                        const storedData = safeStorage.getItem(storageKey);
                        if (storedData) {
                            const parsed = JSON.parse(storedData);
                            if (parsed && parsed.duration === timerStr && typeof parsed.endTime === 'number') {
                                endTime = parsed.endTime;
                            }
                        }
                    } catch (e) {
                        console.error('Error reading checkout timer from storage', e);
                    }

                    if (!endTime) {
                        endTime = Date.now() + durationInSeconds * 1000;
                        safeStorage.setItem(storageKey, JSON.stringify({ endTime: endTime, duration: timerStr }));
                    }

                    const updateTimer = () => {
                        const remaining = Math.max(0, Math.floor((endTime - Date.now()) / 1000));

                        if (remaining <= 0) {
                            endTime = Date.now() + durationInSeconds * 1000;
                            safeStorage.setItem(storageKey, JSON.stringify({ endTime: endTime, duration: timerStr }));
                        }

                        const m = Math.floor(remaining / 60);
                        const s = remaining % 60;
                        const formatted = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');

                        timerEl.textContent = formatted;
                    };

                    updateTimer();
                    setInterval(updateTimer, 1000);
                }
            } else if (timerEl) {
                timerEl.textContent = '';
            }

            const spacer = document.createElement('div');
            spacer.style.width = '100%';
            spacer.style.flexShrink = '0';
            spacer.style.transition = 'height 0.2s ease';
            document.body.prepend(spacer);

            const updateLayout = () => {
                const height = bar.offsetHeight;
                spacer.style.height = height + 'px';

                document.querySelectorAll('.lg\\:sticky').forEach(el => {
                    el.style.top = (32 + height) + 'px';
                });
            };

            updateLayout();
            window.addEventListener('resize', updateLayout);
            setTimeout(updateLayout, 200);

            lucide.createIcons();
        })();
    </script>
</body>

</html>