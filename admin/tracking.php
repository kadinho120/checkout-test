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

    <div class="flex h-screen overflow-hidden" x-data="trackingPage()">

        <!-- Sidebar Navigation -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-950">
            <!-- Header -->
            <header
                class="min-h-16 bg-slate-900/80 backdrop-blur border-b border-slate-800 flex items-center justify-between px-3.5 sm:px-6 py-2.5 sm:py-0 gap-3">
                <div class="flex items-center gap-3">
                    <button 
                        type="button" 
                        @click="$store.nav.toggle()"
                        class="md:hidden p-2 -ml-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700/60 transition focus:outline-none focus:ring-2 focus:ring-blue-500"
                        aria-label="Abrir menu"
                    >
                        <i data-lucide="menu" class="w-5 h-5"></i>
                    </button>
                    <h2 class="text-base sm:text-lg font-semibold text-white truncate">Rastreamento Avançado (S2S)</h2>
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

                    <!-- Pagination Controls -->
                    <div x-show="totalPages > 1" class="px-6 py-4 bg-slate-900 border-t border-slate-800 flex items-center justify-between">
                        <div class="text-xs text-slate-400">
                            Mostrando <span class="font-semibold text-white" x-text="((page - 1) * limit) + 1"></span> a 
                            <span class="font-semibold text-white" x-text="Math.min(page * limit, totalCount)"></span> de 
                            <span class="font-semibold text-white" x-text="totalCount"></span> logs
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="prevPage()" :disabled="page === 1" 
                                class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 disabled:opacity-50 disabled:hover:bg-slate-800 transition text-xs font-bold flex items-center gap-1">
                                <i data-lucide="chevron-left" class="w-3.5 h-3.5"></i> Anterior
                            </button>
                            <div class="flex items-center gap-1">
                                <template x-for="p in totalPages" :key="p">
                                    <button @click="goToPage(p)" 
                                        :class="page === p ? 'bg-blue-600 text-white font-bold' : 'bg-slate-800 hover:bg-slate-700 text-slate-400'"
                                        class="w-8 h-8 rounded-lg text-xs transition flex items-center justify-center"
                                        x-text="p">
                                    </button>
                                </template>
                            </div>
                            <button @click="nextPage()" :disabled="page === totalPages" 
                                class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 disabled:opacity-50 disabled:hover:bg-slate-800 transition text-xs font-bold flex items-center gap-1">
                                Próximo <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();
        function trackingPage() {
            return {
                items: [],
                page: 1,
                totalPages: 1,
                totalCount: 0,
                limit: 10,
                isLoading: false,
                init() {
                    this.fetchData();
                    lucide.createIcons();
                },
                fetchData() {
                    this.isLoading = true;
                    fetch(`../api/v1/tracking.php?page=${this.page}&limit=${this.limit}`)
                        .then(res => res.json())
                        .then(data => {
                            this.items = data.data;
                            this.totalPages = data.total_pages;
                            this.totalCount = data.total_count;
                            this.isLoading = false;
                            this.$nextTick(() => lucide.createIcons());
                        })
                        .catch(err => {
                            console.error(err);
                            this.isLoading = false;
                        });
                },

                nextPage() {
                    if (this.page < this.totalPages) {
                        this.page++;
                        this.fetchData();
                    }
                },

                prevPage() {
                    if (this.page > 1) {
                        this.page--;
                        this.fetchData();
                    }
                },

                goToPage(p) {
                    this.page = p;
                    this.fetchData();
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