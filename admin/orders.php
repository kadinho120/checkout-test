<?php
require_once 'auth.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="bg-slate-950 text-slate-200 font-sans antialiased" x-data="ordersApp()">

    <div class="flex h-screen overflow-hidden">

        <!-- Sidebar -->
        <aside class="w-64 bg-slate-900 border-r border-slate-800 hidden md:flex flex-col">
            <div class="p-6 border-b border-slate-800 flex items-center gap-3">
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
                    class="flex items-center gap-3 px-4 py-3 bg-blue-600/10 text-blue-400 rounded-lg border border-blue-600/20 font-medium">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i> Pedidos
                </a>
                <a href="tracking.php"
                    class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="scan-line" class="w-5 h-5"></i> Rastreamento
                </a>
                <a href="capi.php"
                    class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="activity" class="w-5 h-5"></i> Testar CAPI
                </a>
            </nav>

            <div class="p-4 border-t border-slate-800">
                <div class="flex items-center gap-3 px-4 py-2">
                    <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-xs">AD</div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">Admin</p>
                    </div>
                    <a href="login.php?logout=true" class="text-slate-400 hover:text-red-400 transition" title="Sair">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden relative">
            <header
                class="h-16 bg-slate-900/50 backdrop-blur border-b border-slate-800 flex items-center justify-between px-6">
                <div class="flex items-center gap-4">
                    <h2 class="text-lg font-semibold text-white">Pedidos Realizados</h2>
                </div>
                <button @click="fetchOrders()"
                    class="bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i> Atualizar
                </button>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-950 p-6">

                <!-- Loading State -->
                <div x-show="isLoading" class="flex justify-center py-10">
                    <i data-lucide="loader" class="w-8 h-8 animate-spin text-blue-500"></i>
                </div>

                <!-- Orders Table -->
                <div x-show="!isLoading"
                    class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-lg">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-xs text-slate-400 border-b border-slate-800 bg-slate-900/50">
                                <th class="p-4 uppercase font-medium"># ID</th>
                                <th class="p-4 uppercase font-medium">Cliente</th>
                                <th class="p-4 uppercase font-medium">Valor</th>
                                <th class="p-4 uppercase font-medium text-center">Status</th>
                                <th class="p-4 uppercase font-medium">Data</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <template x-for="order in orders" :key="order.id">
                                <tr class="hover:bg-slate-800/50 transition">
                                    <td class="p-4 font-mono text-sm text-slate-500" x-text="'#' + order.id"></td>
                                    <td class="p-4">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-white text-sm"
                                                x-text="order.customer_name"></span>
                                            <span class="text-xs text-slate-400" x-text="order.customer_email"></span>
                                            <span class="text-xs text-slate-500" x-text="order.customer_phone"></span>
                                        </div>
                                    </td>
                                    <td class="p-4 text-white font-mono text-sm"
                                        x-text="'R$ ' + parseFloat(order.total_amount || 0).toFixed(2)"></td>
                                    <td class="p-4 text-center">
                                        <span x-show="order.status === 'paid'"
                                            class="bg-green-500/10 text-green-400 border border-green-500/20 px-2 py-1 rounded text-xs font-bold">PAGO</span>
                                        <span x-show="order.status === 'pending'"
                                            class="bg-yellow-500/10 text-yellow-400 border border-yellow-500/20 px-2 py-1 rounded text-xs font-bold">PENDENTE</span>

                                        <!-- Actions -->
                                        <div x-show="order.status === 'paid'"
                                            class="mt-2 text-left flex justify-center">
                                            <button @click="resendDeliverable(order.id)"
                                                :disabled="isResending === order.id"
                                                class="text-xs bg-slate-800 hover:bg-slate-700 text-blue-400 px-2 py-1 rounded border border-slate-700 flex items-center gap-1 transition"
                                                title="Reenviar Mensagens de Entrega">
                                                <i x-show="isResending !== order.id" data-lucide="send"
                                                    class="w-3 h-3"></i>
                                                <i x-show="isResending === order.id" data-lucide="loader-2"
                                                    class="w-3 h-3 animate-spin"></i>
                                                Reenviar
                                            </button>
                                        </div>
                                    </td>
                                    <td class="p-4 text-sm text-slate-400"
                                        x-text="new Date(order.created_at).toLocaleString()"></td>
                                </tr>
                            </template>
                            <template x-if="orders.length === 0">
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-slate-500">
                                        Nenhum pedido encontrado.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

            </main>
        </div>

    </div>

    <!-- App Logic -->
    <script>
        function ordersApp() {
            return {
                orders: [],
                isLoading: true,
                isResending: null,
                isRecovering: null,

                init() {
                    this.fetchOrders();
                },

                fetchOrders() {
                    this.isLoading = true;
                    fetch('../api/v1/orders.php')
                        .then(res => res.json())
                        .then(data => {
                            this.orders = data;
                            this.isLoading = false;
                            this.$nextTick(() => lucide.createIcons());
                        })
                        .catch(err => {
                            console.error(err);
                            this.isLoading = false;
                        });
                },

                resendDeliverable(orderId) {
                    if (!confirm('Deseja reenviar os produtos deste pedido via WhatsApp?')) return;

                    this.isResending = orderId;
                    fetch('../api/v1/resend-deliverable.php', {
                        method: 'POST',
                        body: JSON.stringify({ order_id: orderId })
                    })
                        .then(res => res.json())
                        .then(data => {
                            this.isResending = null;
                            if (data.success) {
                                alert('Mensagens enviadas com sucesso! (' + data.details.sent + ' enviados)');
                            } else {
                                alert('Erro: ' + data.message);
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            this.isResending = null;
                            alert('Erro de conexão ao reenviar.');
                        });
                }
            }
        }

    </script>
</body>

</html>