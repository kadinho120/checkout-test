<?php
require_once 'auth.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoramento Meta Ads - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        [x-cloak] { display: none !important; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 10px; }
    </style>
</head>

<body class="bg-slate-950 text-slate-200 font-sans antialiased" x-data="metaMonitorApp()">

    <div class="flex h-screen overflow-hidden">

        <!-- Sidebar (Hardcoded for consistency with other pages) -->
        <aside class="w-64 bg-slate-900 border-r border-slate-800 hidden md:flex flex-col">
            <div class="p-6 border-b border-slate-800 flex items-center gap-3">
                <div class="w-10 h-8 bg-blue-600 rounded-lg flex items-center justify-center font-bold text-white text-xs">APP</div>
                <span class="font-bold text-lg tracking-tight text-white">Checkout Admin</span>
            </div>

            <nav class="flex-1 p-4 space-y-2">
                <a href="index.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Dashboard
                </a>
                <a href="products.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="package" class="w-5 h-5"></i> Produtos
                </a>
                <a href="orders.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i> Pedidos
                </a>
                <a href="meta-events.php" class="flex items-center gap-3 px-4 py-3 bg-blue-600/10 text-blue-400 rounded-lg border border-blue-600/20 font-medium">
                    <i data-lucide="facebook" class="w-5 h-5"></i> Monitor Meta
                </a>
                <a href="tracking.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="scan-line" class="w-5 h-5"></i> Rastreamento
                </a>
                <a href="webhooks.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="webhook" class="w-5 h-5"></i> Webhooks
                </a>
                <a href="capi.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="activity" class="w-5 h-5"></i> Testar CAPI
                </a>
            </nav>

            <div class="p-4 border-t border-slate-800">
                <div class="flex items-center gap-3 px-4 py-2">
                    <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-xs">AD</div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">Admin</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden relative">
            <header class="h-16 bg-slate-900/50 backdrop-blur border-b border-slate-800 flex items-center justify-between px-6">
                <div class="flex items-center gap-4">
                    <h2 class="text-lg font-semibold text-white">Monitoramento de Eventos (Meta Ads)</h2>
                </div>
                <div class="flex gap-2">
                    <button @click="fetchLogs()" class="bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i> Atualizar
                    </button>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-950 p-6">
                
                <!-- Cards Summary -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 shadow-xl">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-slate-400 text-sm font-medium">Eventos com Sucesso</span>
                            <div class="p-2 bg-green-500/10 rounded-lg"><i data-lucide="check-circle-2" class="text-green-500 w-5 h-5"></i></div>
                        </div>
                        <h3 class="text-2xl font-bold text-white font-mono" x-text="stats.success">0</h3>
                    </div>
                    <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 shadow-xl">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-slate-400 text-sm font-medium">Eventos com Erro</span>
                            <div class="p-2 bg-red-500/10 rounded-lg"><i data-lucide="x-circle" class="text-red-500 w-5 h-5"></i></div>
                        </div>
                        <h3 class="text-2xl font-bold text-white font-mono" x-text="stats.error">0</h3>
                    </div>
                    <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 shadow-xl">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-slate-400 text-sm font-medium">Pendentes / Processando</span>
                            <div class="p-2 bg-yellow-500/10 rounded-lg"><i data-lucide="clock" class="text-yellow-500 w-5 h-5"></i></div>
                        </div>
                        <h3 class="text-2xl font-bold text-white font-mono" x-text="stats.pending">0</h3>
                    </div>
                </div>

                <!-- Events Table -->
                <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-lg">
                    <div class="flex items-center justify-between p-4 border-b border-slate-800 bg-slate-900/50">
                        <h3 class="font-bold text-slate-300">Últimos Pedidos Pagos</h3>
                    </div>
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-xs text-slate-400 border-b border-slate-800 bg-slate-900/50">
                                <th class="p-4 uppercase font-medium">Pedido</th>
                                <th class="p-4 uppercase font-medium">Cliente</th>
                                <th class="p-4 uppercase font-medium text-center">Status Tracking</th>
                                <th class="p-4 uppercase font-medium">Resultado API</th>
                                <th class="p-4 uppercase font-medium text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <template x-for="item in logs" :key="item.id">
                                <tr class="hover:bg-slate-800/50 transition whitespace-nowrap">
                                    <td class="p-4 font-mono text-xs text-slate-500" x-text="'#' + item.id"></td>
                                    <td class="p-4">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-white text-sm" x-text="item.customer_name"></span>
                                            <span class="text-xs text-slate-500" x-text="item.customer_email"></span>
                                        </div>
                                    </td>
                                    <td class="p-4 text-center">
                                        <template x-if="item.meta_purchase_status == 1">
                                            <span class="bg-green-500/10 text-green-400 border border-green-500/20 px-2 py-1 rounded text-[10px] font-bold">SUCESSO</span>
                                        </template>
                                        <template x-if="item.meta_purchase_status == 2">
                                            <span class="bg-red-500/10 text-red-400 border border-red-500/20 px-2 py-1 rounded text-[10px] font-bold">ERRO</span>
                                        </template>
                                        <template x-if="item.meta_purchase_status == 0">
                                            <span class="bg-yellow-500/10 text-yellow-400 border border-yellow-500/20 px-2 py-1 rounded text-[10px] font-bold">PENDENTE</span>
                                        </template>
                                    </td>
                                    <td class="p-4">
                                        <div class="max-w-[300px] overflow-hidden truncate text-xs font-mono text-slate-400" :title="item.meta_purchase_log" x-text="item.meta_purchase_log || '-'"></div>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button @click="showLog(item)" class="p-2 bg-slate-800 hover:bg-slate-700 rounded transition text-slate-400" title="Ver Log Completo">
                                                <i data-lucide="eye" class="w-4 h-4"></i>
                                            </button>
                                            <button @click="retryTracking(item.id)" :disabled="isRetrying === item.id" class="p-2 bg-blue-600/10 hover:bg-blue-600/20 rounded transition text-blue-400 disabled:opacity-50" title="Reenviar Evento">
                                                <i x-show="isRetrying !== item.id" data-lucide="refresh-cw" class="w-4 h-4"></i>
                                                <i x-show="isRetrying === item.id" data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="logs.length === 0">
                                <tr><td colspan="5" class="p-8 text-center text-slate-500">Nenhum evento processado até o momento.</td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <!-- Log Modal -->
    <div x-show="selectedLog" x-cloak class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div @click.outside="selectedLog = null" class="bg-slate-900 border border-slate-700 w-full max-w-2xl rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[80vh]">
            <div class="p-4 border-b border-slate-800 flex justify-between items-center">
                <h3 class="font-bold text-white">Log de Resposta da API (Meta)</h3>
                <button @click="selectedLog = null" class="text-slate-500 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-6 overflow-y-auto">
                <div class="bg-slate-950 p-4 rounded border border-slate-800">
                    <pre class="text-xs text-green-400 overflow-x-auto whitespace-pre-wrap font-mono" x-text="selectedLog ? selectedLog.meta_purchase_log : ''"></pre>
                </div>
            </div>
            <div class="p-4 border-t border-slate-800 flex justify-end">
                <button @click="selectedLog = null" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 rounded-lg font-bold text-sm">Fechar</button>
            </div>
        </div>
    </div>

    <script>
        function metaMonitorApp() {
            return {
                logs: [],
                stats: { success: 0, error: 0, pending: 0 },
                isLoading: false,
                isRetrying: null,
                selectedLog: null,

                init() {
                    this.fetchLogs();
                },

                fetchLogs() {
                    this.isLoading = true;
                    fetch('../api/v1/meta-tracking-status.php')
                        .then(res => res.json())
                        .then(data => {
                            this.logs = data;
                            this.calculateStats();
                            this.isLoading = false;
                            this.$nextTick(() => lucide.createIcons());
                        })
                        .catch(err => {
                            console.error(err);
                            this.isLoading = false;
                        });
                },

                calculateStats() {
                    this.stats = {
                        success: this.logs.filter(l => l.meta_purchase_status == 1).length,
                        error: this.logs.filter(l => l.meta_purchase_status == 2).length,
                        pending: this.logs.filter(l => l.meta_purchase_status == 0).length,
                    };
                },

                showLog(item) {
                    this.selectedLog = item;
                    this.$nextTick(() => lucide.createIcons());
                },

                retryTracking(orderId) {
                    this.isRetrying = orderId;
                    fetch('../api/v1/meta-retry.php', {
                        method: 'POST',
                        body: JSON.stringify({ order_id: orderId })
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.isRetrying = null;
                        if (data.success) {
                            alert('Evento reenviado com sucesso!');
                            this.fetchLogs();
                        } else {
                            alert('Erro ao reenviar: ' + (data.message || data.response));
                            this.fetchLogs(); // Atualiza para mostrar o log de erro
                        }
                    })
                    .catch(err => {
                        this.isRetrying = null;
                        alert('Erro de conexão ao reenviar.');
                    });
                }
            }
        }
    </script>
</body>

</html>
