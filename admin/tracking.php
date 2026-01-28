<?php
session_start();
if (!($_SESSION['logged_in'] ?? false)) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rastreamento - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-slate-950 text-slate-200 font-sans antialiased selection:bg-purple-500/30">

    <div class="flex h-screen" x-data="trackingPage()">

        <!-- Sidebar (Shared Component structure) -->
        <aside class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col">
            <div class="p-6 flex items-center gap-3">
                <div
                    class="w-10 h-8 bg-blue-600 rounded-lg flex items-center justify-center font-bold text-white text-xs">
                    APP</div>
                <span class="font-bold text-lg tracking-tight text-white">Checkout Admin</span>
            </div>

            <nav class="flex-1 p-4 space-y-2">
                <a href="index.php"
                    class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Dashboard
                </a>
                <a href="products.php"
                    class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="package" class="w-5 h-5"></i> Produtos
                </a>
                <a href="orders.php"
                    class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i> Pedidos
                </a>
                <a href="tracking.php"
                    class="flex items-center gap-3 px-4 py-3 bg-purple-600/10 text-purple-400 rounded-lg border border-purple-600/20 font-medium">
                    <i data-lucide="scan-line" class="w-5 h-5"></i> Rastreamento
                </a>
            </nav>

            <div class="p-4 border-t border-slate-800">
                <div class="flex items-center gap-3 px-4 py-2">
                    <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-xs">AD</div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">
                            <?= htmlspecialchars($_SESSION['user'] ?? 'Admin') ?>
                        </p>
                    </div>
                    <a href="login.php?logout=true" class="text-slate-400 hover:text-red-400 transition" title="Sair">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col overflow-hidden bg-slate-950">
            <!-- Header -->
            <header
                class="h-16 bg-slate-900/50 backdrop-blur border-b border-slate-800 flex items-center justify-between px-6">
                <div class="flex items-center gap-4">
                    <h2 class="text-lg font-semibold text-white">Rastreamento Avançado (S2S)</h2>
                </div>
                <button @click="fetchData()"
                    class="p-2 bg-slate-800 hover:bg-slate-700 rounded-lg transition text-slate-400 hover:text-white">
                    <i data-lucide="refresh-cw" class="w-4 h-4" :class="{'animate-spin': isLoading}"></i>
                </button>
            </header>

            <!-- Content -->
            <div class="flex-1 overflow-auto p-6">

                <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-lg">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr
                                    class="border-b border-slate-800 bg-slate-900 text-xs uppercase text-slate-400 font-bold tracking-wider">
                                    <th class="p-4">Data/Hora</th>
                                    <th class="p-4">Origem</th>
                                    <th class="p-4">Track IDs</th>
                                    <th class="p-4">Status Conversão</th>
                                    <th class="p-4 text-right">Correlation ID</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                <template x-for="item in items" :key="item.id">
                                    <tr class="hover:bg-slate-800/50 transition duration-150">
                                        <td class="p-4">
                                            <div class="text-white text-sm font-medium" x-text="formatDate(item.date)">
                                            </div>
                                            <div class="text-slate-500 text-xs" x-text="timeSince(item.date)"></div>
                                        </td>
                                        <td class="p-4">
                                            <div class="flex flex-col gap-1">
                                                <span
                                                    class="text-xs bg-slate-800 text-slate-300 px-2 py-0.5 rounded border border-slate-700 w-fit"
                                                    x-text="item.source.domain"></span>
                                                <div class="text-xs text-slate-400"
                                                    x-show="item.source.utm_source !== '-'">
                                                    src: <span class="text-blue-400"
                                                        x-text="item.source.utm_source"></span>
                                                </div>
                                                <div class="text-xs text-slate-400"
                                                    x-show="item.source.utm_campaign !== '-'">
                                                    cmp: <span class="text-purple-400"
                                                        x-text="item.source.utm_campaign"></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-4">
                                            <div class="space-y-1">
                                                <div class="flex items-center gap-2 text-xs text-slate-400"
                                                    title="Facebook Click ID">
                                                    <span class="font-bold text-slate-500">FBC:</span>
                                                    <span x-text="shorten(item.identifiers.fbc)"
                                                        class="font-mono"></span>
                                                </div>
                                                <div class="flex items-center gap-2 text-xs text-slate-400"
                                                    title="Facebook Browser ID">
                                                    <span class="font-bold text-slate-500">FBP:</span>
                                                    <span x-text="shorten(item.identifiers.fbp)"
                                                        class="font-mono"></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-4">
                                            <template x-if="item.conversion.converted">
                                                <span
                                                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-500/10 text-green-400 border border-green-500/20">
                                                    <span
                                                        class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                                    Pedido Criado
                                                </span>
                                            </template>
                                            <template x-if="!item.conversion.converted">
                                                <span
                                                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700/50 text-slate-400 border border-slate-700">
                                                    Checkout Iniciado
                                                </span>
                                            </template>
                                            <div x-show="item.conversion.converted" class="mt-1 text-xs text-slate-500">
                                                <span x-text="item.conversion.status.toUpperCase()"></span> • <span
                                                    x-text="formatCurrency(item.conversion.amount)"></span>
                                            </div>
                                        </td>
                                        <td class="p-4 text-right">
                                            <code class="text-xs text-slate-500 bg-slate-950 px-2 py-1 rounded"
                                                x-text="item.correlation_id"></code>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="items.length === 0 && !isLoading">
                                    <td colspan="5" class="p-8 text-center text-slate-500">
                                        Nenhum dado de rastreamento encontrado ainda.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        function trackingPage() {
            return {
                items: [],
                isLoading: false,
                init() {
                    this.fetchData();
                    lucide.createIcons();
                },
                fetchData() {
                    this.isLoading = true;
                    fetch('../api/v1/tracking.php')
                        .then(res => res.json())
                        .then(data => {
                            this.items = data;
                            this.isLoading = false;
                            this.$nextTick(() => lucide.createIcons());
                        })
                        .catch(err => {
                            console.error(err);
                            this.isLoading = false;
                        });
                },
                formatDate(dateStr) {
                    if (!dateStr) return '-';
                    const date = new Date(dateStr.replace(' ', 'T')); // Fix SQLite format if needed
                    return date.toLocaleString('pt-BR');
                },
                timeSince(dateStr) {
                    if (!dateStr) return '';
                    const date = new Date(dateStr.replace(' ', 'T'));
                    const seconds = Math.floor((new Date() - date) / 1000);
                    let interval = seconds / 31536000;
                    if (interval > 1) return Math.floor(interval) + " anos atrás";
                    interval = seconds / 2592000;
                    if (interval > 1) return Math.floor(interval) + " meses atrás";
                    interval = seconds / 86400;
                    if (interval > 1) return Math.floor(interval) + " dias atrás";
                    interval = seconds / 3600;
                    if (interval > 1) return Math.floor(interval) + " horas atrás";
                    interval = seconds / 60;
                    if (interval > 1) return Math.floor(interval) + " min atrás";
                    return Math.floor(seconds) + " seg atrás";
                },
                shorten(str) {
                    if (!str || str.length < 15) return str;
                    return str.substring(0, 8) + '...';
                },
                formatCurrency(value) {
                    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
                }
            }
        }
    </script>
</body>

</html>