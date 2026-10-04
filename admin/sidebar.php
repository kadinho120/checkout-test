<?php
// admin/sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);

$menuItems = [
    ['url' => 'index.php', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
    ['url' => 'products.php', 'icon' => 'package', 'label' => 'Produtos'],
    ['url' => 'orders.php', 'icon' => 'shopping-cart', 'label' => 'Pedidos'],
    ['url' => 'pix-rotations.php', 'icon' => 'key-round', 'label' => 'Chaves Pix'],
    ['url' => 'meta-events.php', 'icon' => 'activity', 'label' => 'Monitor Meta'],
    ['url' => 'tracking.php', 'icon' => 'scan-line', 'label' => 'Rastreamento'],
    ['url' => 'webhooks.php', 'icon' => 'webhook', 'label' => 'Webhooks'],
    ['url' => 'capi.php', 'icon' => 'cpu', 'label' => 'Testar CAPI'],
];
?>

<!-- Alpine Store for Navigation -->
<script>
(function() {
    function initNavStore() {
        if (window.Alpine && !window.Alpine.store('nav')) {
            window.Alpine.store('nav', {
                open: false,
                toggle() { 
                    this.open = !this.open;
                    if (this.open && window.lucide) {
                        setTimeout(() => window.lucide.createIcons(), 50);
                    }
                },
                close() { this.open = false; }
            });
        }
    }
    if (window.Alpine) {
        initNavStore();
    } else {
        document.addEventListener('alpine:init', initNavStore);
    }
})();
</script>

<!-- Desktop Sidebar -->
<aside class="w-64 bg-slate-900 border-r border-slate-800 hidden md:flex flex-col shrink-0 h-screen sticky top-0 select-none z-30">
    <div class="p-6 border-b border-slate-800 flex items-center gap-3">
        <div class="w-10 h-8 bg-blue-600 rounded-lg flex items-center justify-center font-bold text-white text-xs shadow-md shadow-blue-600/30">
            APP
        </div>
        <div>
            <span class="font-bold text-lg tracking-tight text-white block leading-none">Checkout Admin</span>
            <span class="text-[10px] text-slate-500 font-medium">Painel de Controle</span>
        </div>
    </div>

    <nav class="flex-1 p-4 space-y-1.5 overflow-y-auto">
        <?php foreach ($menuItems as $item): 
            $isActive = ($currentPage === $item['url']);
        ?>
            <a href="<?= $item['url'] ?>"
               class="flex items-center gap-3 px-4 py-3 rounded-lg font-medium text-sm transition-all duration-150 <?= $isActive ? 'bg-blue-600/10 text-blue-400 border border-blue-600/20 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/80' ?>">
                <i data-lucide="<?= $item['icon'] ?>" class="w-5 h-5 <?= $isActive ? 'text-blue-400' : 'text-slate-400' ?>"></i>
                <span><?= $item['label'] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="p-4 border-t border-slate-800 bg-slate-900/50">
        <div class="flex items-center gap-3 px-2 py-1">
            <div class="w-9 h-9 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-xs font-bold text-slate-300">
                AD
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-white truncate">Administrador</p>
                <div class="flex items-center gap-1.5 mt-0.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    <span class="text-[11px] text-slate-400">Online</span>
                </div>
            </div>
            <a href="login.php?logout=true" 
               class="p-2 text-slate-400 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition" 
               title="Sair do painel">
                <i data-lucide="log-out" class="w-4 h-4"></i>
            </a>
        </div>
    </div>
</aside>

<!-- Mobile Drawer Backdrop & Navigation -->
<div x-show="$store.nav && $store.nav.open" 
     x-cloak 
     class="fixed inset-0 z-50 md:hidden" 
     role="dialog" 
     aria-modal="true">

    <!-- Overlay Backdrop -->
    <div x-show="$store.nav && $store.nav.open"
         x-transition:enter="transition-opacity ease-linear duration-250"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm"
         @click="$store.nav.close()">
    </div>

    <!-- Drawer Panel -->
    <div x-show="$store.nav && $store.nav.open"
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="-translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="-translate-x-full"
         class="relative flex flex-col w-72 max-w-[85vw] h-full bg-slate-900 border-r border-slate-800 shadow-2xl">

        <!-- Mobile Drawer Header -->
        <div class="p-5 border-b border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-8 bg-blue-600 rounded-lg flex items-center justify-center font-bold text-white text-xs shadow-md shadow-blue-500/30">
                    APP
                </div>
                <div>
                    <span class="font-bold text-base tracking-tight text-white block leading-none">Checkout Admin</span>
                    <span class="text-[10px] text-slate-500">Menu Principal</span>
                </div>
            </div>
            <button type="button" 
                    @click="$store.nav.close()"
                    class="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition focus:outline-none"
                    aria-label="Fechar menu">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Mobile Drawer Navigation Links -->
        <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
            <?php foreach ($menuItems as $item): 
                $isActive = ($currentPage === $item['url']);
            ?>
                <a href="<?= $item['url'] ?>"
                   class="flex items-center gap-3 px-3.5 py-3 rounded-lg font-medium text-sm transition-all duration-150 <?= $isActive ? 'bg-blue-600/10 text-blue-400 border border-blue-600/20 shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/80 active:bg-slate-800' ?>">
                    <i data-lucide="<?= $item['icon'] ?>" class="w-5 h-5 <?= $isActive ? 'text-blue-400' : 'text-slate-400' ?>"></i>
                    <span><?= $item['label'] ?></span>
                    <?php if ($isActive): ?>
                        <span class="ml-auto w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- Mobile Drawer User Profile & Logout -->
        <div class="p-4 border-t border-slate-800 bg-slate-900/60">
            <div class="flex items-center gap-3 px-2 py-1">
                <div class="w-9 h-9 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-xs font-bold text-slate-300">
                    AD
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white truncate">Administrador</p>
                    <span class="text-[11px] text-emerald-400 flex items-center gap-1 font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Conectado
                    </span>
                </div>
                <a href="login.php?logout=true" 
                   class="p-2 text-slate-400 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition" 
                   title="Sair">
                    <i data-lucide="log-out" class="w-5 h-5"></i>
                </a>
            </div>
        </div>
    </div>
</div>
