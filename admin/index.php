<?php
require_once 'auth.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="bg-slate-950 text-slate-200 font-sans antialiased" x-data="dashboardApp()">

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
                    class="flex items-center gap-3 px-4 py-3 bg-blue-600/10 text-blue-400 rounded-lg border border-blue-600/20 font-medium">
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
                    class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="scan-line" class="w-5 h-5"></i> Rastreamento
                </a>
            </nav>
            <div class="p-4 border-t border-slate-800">
                <div class="flex items-center gap-3 px-4 py-2">
                    <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-xs">AD</div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">Admin</p>
                    </div>
                    <a href="login.php?logout=true" class="text-slate-400 hover:text-red-400 transition"><i
                            data-lucide="log-out" class="w-4 h-4"></i></a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden relative">
            <header
                class="h-16 bg-slate-900/50 backdrop-blur border-b border-slate-800 flex items-center justify-between px-6">
                <div class="flex items-center gap-4">
                    <h2 class="text-lg font-semibold text-white">Dashboard</h2>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                        <span class="text-xs text-green-500 font-bold uppercase">Online</span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <select x-model="filter" @change="fetchStats()"
                        class="bg-slate-800 text-white text-sm border border-slate-700 rounded-lg p-2 outline-none focus:border-blue-500">
                        <option value="today">Hoje</option>
                        <option value="yesterday">Ontem</option>
                        <option value="last7">Últimos 7 dias</option>
                        <option value="last14">Últimos 14 dias</option>
                        <option value="last30">Últimos 30 dias</option>
                        <option value="this_month">Este Mês</option>
                        <option value="this_year">Este Ano</option>
                        <option value="all">Todo o Período</option>
                        <option value="custom">Personalizado</option>
                    </select>

                    <template x-if="filter === 'custom'">
                        <div class="flex items-center gap-2">
                            <input type="date" x-model="startDate" @change="fetchStats()"
                                class="bg-slate-800 text-white text-sm border border-slate-700 rounded-lg p-2 outline-none">
                            <span class="text-slate-500">-</span>
                            <input type="date" x-model="endDate" @change="fetchStats()"
                                class="bg-slate-800 text-white text-sm border border-slate-700 rounded-lg p-2 outline-none">
                        </div>
                    </template>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-950 p-6">

                <!-- KPI CARDS -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">

                    <!-- Faturamento -->
                    <div
                        class="bg-slate-900 border border-slate-800 p-6 rounded-xl shadow-lg relative overflow-hidden group">
                        <div
                            class="absolute -right-6 -top-6 bg-green-500/10 w-24 h-24 rounded-full blur-2xl group-hover:bg-green-500/20 transition">
                        </div>
                        <div class="flex justify-between items-start mb-4 relative z-10">
                            <div>
                                <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Faturamento Total
                                </p>
                                <h3 class="text-2xl font-bold text-white mt-1" x-text="formatCurrency(stats.revenue)">R$
                                    0,00</h3>
                                <p class="text-xs text-green-400 mt-1 font-medium flex items-center gap-1">
                                    <i data-lucide="check-circle" class="w-3 h-3"></i>
                                    <span x-text="stats.paid_orders + ' Venda(s) Aprovada(s)'">0 Vendas</span>
                                </p>
                            </div>
                            <div class="p-2 bg-slate-800 rounded-lg text-green-500"><i data-lucide="dollar-sign"
                                    class="w-5 h-5"></i></div>
                        </div>
                    </div>

                    <!-- Pedidos -->
                    <div
                        class="bg-slate-900 border border-slate-800 p-6 rounded-xl shadow-lg relative overflow-hidden group">
                        <div
                            class="absolute -right-6 -top-6 bg-blue-500/10 w-24 h-24 rounded-full blur-2xl group-hover:bg-blue-500/20 transition">
                        </div>
                        <div class="flex justify-between items-start mb-4 relative z-10">
                            <div>
                                <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Total de Pedidos
                                </p>
                                <h3 class="text-2xl font-bold text-white mt-1" x-text="stats.total_orders">0</h3>
                            </div>
                            <div class="p-2 bg-slate-800 rounded-lg text-blue-500"><i data-lucide="shopping-bag"
                                    class="w-5 h-5"></i></div>
                        </div>
                    </div>

                    <!-- Conversão -->
                    <div
                        class="bg-slate-900 border border-slate-800 p-6 rounded-xl shadow-lg relative overflow-hidden group">
                        <div
                            class="absolute -right-6 -top-6 bg-yellow-500/10 w-24 h-24 rounded-full blur-2xl group-hover:bg-yellow-500/20 transition">
                        </div>
                        <div class="flex justify-between items-start mb-4 relative z-10">
                            <div>
                                <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Taxa de Conversão
                                </p>
                                <h3 class="text-2xl font-bold text-white mt-1" x-text="stats.conversion_rate + '%'">0%
                                </h3>
                            </div>
                            <div class="p-2 bg-slate-800 rounded-lg text-yellow-500"><i data-lucide="percent"
                                    class="w-5 h-5"></i></div>
                        </div>
                        <p class="text-xs text-slate-500 mt-2 relative z-10">Pedidos pagos sobre iniciados</p>
                    </div>

                    <!-- Ticket Médio (Calculado no front) -->
                    <div
                        class="bg-slate-900 border border-slate-800 p-6 rounded-xl shadow-lg relative overflow-hidden group">
                        <div
                            class="absolute -right-6 -top-6 bg-purple-500/10 w-24 h-24 rounded-full blur-2xl group-hover:bg-purple-500/20 transition">
                        </div>
                        <div class="flex justify-between items-start mb-4 relative z-10">
                            <div>
                                <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Ticket Médio</p>
                                <h3 class="text-2xl font-bold text-white mt-1"
                                    x-text="formatCurrency(calculateTicket())">R$ 0,00</h3>
                            </div>
                            <div class="p-2 bg-slate-800 rounded-lg text-purple-500"><i data-lucide="trending-up"
                                    class="w-5 h-5"></i></div>
                        </div>
                    </div>

                    <!-- Pix Pendentes (Novo) -->
                    <div
                        class="bg-slate-900 border border-slate-800 p-6 rounded-xl shadow-lg relative overflow-hidden group">
                        <div
                            class="absolute -right-6 -top-6 bg-orange-500/10 w-24 h-24 rounded-full blur-2xl group-hover:bg-orange-500/20 transition">
                        </div>
                        <div class="flex justify-between items-start mb-4 relative z-10">
                            <div>
                                <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Pix Pendentes</p>
                                <h3 class="text-2xl font-bold text-white mt-1" x-text="stats.pending_orders">0</h3>
                                <p class="text-xs text-orange-400 mt-1" x-text="formatCurrency(stats.pending_revenue)">
                                    R$ 0,00</p>
                            </div>
                            <div class="p-2 bg-slate-800 rounded-lg text-orange-500"><i data-lucide="clock"
                                    class="w-5 h-5"></i></div>
                        </div>
                    </div>

                </div>

                <!-- SALES BY PRODUCT & RECENT ORDERS GRID -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <!-- Sales By Product -->
                    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-lg h-fit">
                        <div class="p-6 border-b border-slate-800 flex justify-between items-center">
                            <h3 class="font-bold text-white">Desempenho por Produto</h3>
                        </div>
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-xs text-slate-400 border-b border-slate-800 bg-slate-900/50">
                                    <th class="p-4 uppercase font-medium">Produto</th>
                                    <th class="p-4 uppercase font-medium text-center">Qtd.</th>
                                    <th class="p-4 uppercase font-medium text-right">Faturamento</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                <template x-for="item in stats.sales_by_product" :key="item.name">
                                    <tr class="hover:bg-slate-800/50 transition">
                                        <td class="p-4 text-sm text-slate-300 font-medium" x-text="item.name"></td>
                                        <td class="p-4 text-sm text-slate-400 text-center font-mono" x-text="item.qty">
                                        </td>
                                        <td class="p-4 text-sm text-green-400 text-right font-mono font-bold"
                                            x-text="formatCurrency(item.revenue)"></td>
                                    </tr>
                                </template>
                                <template x-if="!stats.sales_by_product || stats.sales_by_product.length === 0">
                                    <tr>
                                        <td colspan="3" class="p-8 text-center text-slate-500 text-sm">
                                            Nenhum dado de venda disponível.
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Recent Orders -->
                    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-lg h-fit">
                        <div class="p-6 border-b border-slate-800 flex justify-between items-center">
                            <h3 class="font-bold text-white">Pedidos Recentes</h3>
                            <a href="orders.php" class="text-xs text-blue-400 hover:text-blue-300 transition">Ver
                                Todos</a>
                        </div>
                        <div class="divide-y divide-slate-800">
                            <template x-for="order in stats.recent_orders" :key="order.id">
                                <div class="p-4 flex items-center justify-between hover:bg-slate-800/50 transition">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-slate-800 flex items-center justify-center text-slate-400 font-bold text-xs"
                                            x-text="'#'+order.id"></div>
                                        <div>
                                            <p class="text-sm font-bold text-white line-clamp-1"
                                                x-text="order.customer_name"></p>
                                            <p class="text-xs text-slate-400" x-text="order.product_name"></p>
                                            <p class="text-[10px] text-slate-500 mt-0.5"
                                                x-text="new Date(order.created_at).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })">
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-bold text-white text-green-400"
                                            x-text="formatCurrency(order.total_amount)"></p>
                                        <span :class="{
                                            'bg-green-500/10 text-green-400 border-green-500/20': ['paid', 'completed'].includes(order.status.toLowerCase()),
                                            'bg-yellow-500/10 text-yellow-500 border-yellow-500/20': ['pending'].includes(order.status.toLowerCase()),
                                        }" class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase inline-block mt-1"
                                            x-text="order.status === 'paid' ? 'PAGO' : (order.status === 'pending' ? 'PENDENTE' : order.status)">
                                        </span>
                                    </div>
                                </div>
                            </template>
                            <template x-if=" stats.recent_orders.length===0">
                                ainda.</td>
                                </tr>
                            </template>
                            </tbody>
                            </table>
                        </div>

            </main>
        </div>
    </div>

    <script>
        function dashboardApp() {
            return {
                stats: {
                    revenue: 0,
                    total_orders: 0,
                    paid_orders: 0,
                    pending_orders: 0,
                    pending_revenue: 0,
                    conversion_rate: 0,
                    recent_orders: []
                },
                filter: 'today',
                startDate: '',
                endDate: '',
                isLoading: true,

                init() {
                    const today = new Date().toISOString().split('T')[0];
                    this.startDate = today;
                    this.endDate = today;
                    this.fetchStats();

                    // Auto-refresh every 30s
                    setInterval(() => {
                        this.fetchStats();
                    }, 30000);
                },

                fetchStats() {
                    // Build Query Params
                    let query = `?searchDate=${this.filter}`;
                    if (this.filter === 'custom') {
                        if (!this.startDate || !this.endDate) return; // Wait for both dates
                        query += `&startDate=${this.startDate}&endDate=${this.endDate}`;
                    }

                    this.isLoading = true;
                    fetch('../api/v1/dashboard-stats.php' + query)
                        .then(res => res.json())
                        .then(data => {
                            this.stats = data;
                            this.isLoading = false;
                            this.$nextTick(() => lucide.createIcons());
                        })
                        .catch(err => {
                            console.error(err);
                            this.isLoading = false;
                        });
                },
                formatCurrency(value) {
                    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
                },
                calculateTicket() {
                    if (this.stats.paid_orders > 0) return this.stats.revenue / this.stats.paid_orders;
                    return 0;
                },
                formatDate(dateString) {
                    const date = new Date(dateString);
                    return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
                }
            }
        }
    </script>
</body>

</html>