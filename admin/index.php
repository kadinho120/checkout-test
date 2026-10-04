<?php
require_once 'auth.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        [x-cloak] {
            display: none !important;
        }
        /* Custom scrollbar for orders container */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #0f172a;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 9999px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #475569;
        }
    </style>
</head>

<body class="bg-slate-950 text-slate-200 font-sans antialiased selection:bg-blue-600 selection:text-white" x-data="dashboardApp()">

    <div class="flex h-screen overflow-hidden">
        
        <!-- Reusable Desktop Sidebar & Mobile Drawer -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden relative">
            
            <!-- Top Navbar / Header -->
            <header class="min-h-16 bg-slate-900/80 backdrop-blur-md border-b border-slate-800 flex flex-wrap items-center justify-between px-3.5 sm:px-6 py-2.5 sm:py-0 gap-3 z-20 shrink-0">
                
                <!-- Left: Mobile Menu Toggle + Title + Status Badge -->
                <div class="flex items-center gap-3">
                    <button 
                        type="button" 
                        @click="$store.nav.toggle()"
                        class="md:hidden p-2 -ml-1 rounded-lg bg-slate-800/90 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700/60 transition focus:outline-none focus:ring-2 focus:ring-blue-500 active:scale-95"
                        aria-label="Abrir menu lateral"
                    >
                        <i data-lucide="menu" class="w-5 h-5"></i>
                    </button>

                    <div class="flex items-center gap-2.5">
                        <h2 class="text-base sm:text-lg font-bold text-white tracking-tight">Dashboard</h2>
                        <div class="flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/20">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-[10px] sm:text-xs text-emerald-400 font-bold uppercase tracking-wider">Online</span>
                        </div>
                    </div>
                </div>

                <!-- Right: Period Filter and Custom Date Picker -->
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <div class="relative flex-1 sm:flex-initial">
                        <select 
                            x-model="filter" 
                            @change="onPeriodFilterChange()"
                            class="w-full sm:w-auto bg-slate-800 text-white text-xs sm:text-sm border border-slate-700 rounded-lg px-3 py-2 outline-none focus:border-blue-500 transition cursor-pointer pr-8 appearance-none"
                        >
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
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-400">
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
                        </div>
                    </div>

                    <template x-if="filter === 'custom'">
                        <div class="flex items-center gap-1.5 w-full sm:w-auto mt-1 sm:mt-0">
                            <input 
                                type="date" 
                                x-model="startDate" 
                                @change="onPeriodFilterChange()"
                                class="bg-slate-800 text-white text-xs border border-slate-700 rounded-lg px-2.5 py-1.5 outline-none focus:border-blue-500 flex-1 sm:w-32"
                            >
                            <span class="text-slate-500 text-xs">até</span>
                            <input 
                                type="date" 
                                x-model="endDate" 
                                @change="onPeriodFilterChange()"
                                class="bg-slate-800 text-white text-xs border border-slate-700 rounded-lg px-2.5 py-1.5 outline-none focus:border-blue-500 flex-1 sm:w-32"
                            >
                        </div>
                    </template>
                </div>
            </header>

            <!-- Scrollable Content Body -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-950 p-3 sm:p-6 space-y-6">

                <!-- KPI CARDS GRID -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3.5 sm:gap-5">

                    <!-- Faturamento -->
                    <div class="bg-slate-900 border border-slate-800 p-4 sm:p-5 rounded-xl shadow-lg relative overflow-hidden group">
                        <div class="absolute -right-6 -top-6 bg-emerald-500/10 w-24 h-24 rounded-full blur-2xl group-hover:bg-emerald-500/20 transition"></div>
                        <div class="flex justify-between items-start mb-2 relative z-10">
                            <div>
                                <p class="text-slate-400 text-[11px] font-bold uppercase tracking-wider">Faturamento Total</p>
                                <h3 class="text-xl sm:text-2xl font-bold text-white mt-1" x-text="formatCurrency(stats.revenue)">R$ 0,00</h3>
                                <p class="text-xs text-emerald-400 mt-1 font-medium flex items-center gap-1">
                                    <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i>
                                    <span x-text="stats.paid_orders + ' Venda(s) Aprovada(s)'">0 Vendas</span>
                                </p>
                            </div>
                            <div class="p-2.5 bg-slate-800 border border-slate-700/60 rounded-xl text-emerald-400 shadow-inner">
                                <i data-lucide="dollar-sign" class="w-5 h-5"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Total de Pedidos -->
                    <div class="bg-slate-900 border border-slate-800 p-4 sm:p-5 rounded-xl shadow-lg relative overflow-hidden group">
                        <div class="absolute -right-6 -top-6 bg-blue-500/10 w-24 h-24 rounded-full blur-2xl group-hover:bg-blue-500/20 transition"></div>
                        <div class="flex justify-between items-start mb-2 relative z-10">
                            <div>
                                <p class="text-slate-400 text-[11px] font-bold uppercase tracking-wider">Total de Pedidos</p>
                                <h3 class="text-xl sm:text-2xl font-bold text-white mt-1" x-text="stats.total_orders">0</h3>
                                <p class="text-xs text-slate-400 mt-1">Registrados no período</p>
                            </div>
                            <div class="p-2.5 bg-slate-800 border border-slate-700/60 rounded-xl text-blue-400 shadow-inner">
                                <i data-lucide="shopping-bag" class="w-5 h-5"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Conversão -->
                    <div class="bg-slate-900 border border-slate-800 p-4 sm:p-5 rounded-xl shadow-lg relative overflow-hidden group">
                        <div class="absolute -right-6 -top-6 bg-yellow-500/10 w-24 h-24 rounded-full blur-2xl group-hover:bg-yellow-500/20 transition"></div>
                        <div class="flex justify-between items-start mb-2 relative z-10">
                            <div>
                                <p class="text-slate-400 text-[11px] font-bold uppercase tracking-wider">Taxa de Conversão</p>
                                <h3 class="text-xl sm:text-2xl font-bold text-white mt-1" x-text="stats.conversion_rate + '%'">0%</h3>
                                <p class="text-xs text-slate-400 mt-1">Pagos sobre iniciados</p>
                            </div>
                            <div class="p-2.5 bg-slate-800 border border-slate-700/60 rounded-xl text-yellow-400 shadow-inner">
                                <i data-lucide="percent" class="w-5 h-5"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Ticket Médio -->
                    <div class="bg-slate-900 border border-slate-800 p-4 sm:p-5 rounded-xl shadow-lg relative overflow-hidden group">
                        <div class="absolute -right-6 -top-6 bg-purple-500/10 w-24 h-24 rounded-full blur-2xl group-hover:bg-purple-500/20 transition"></div>
                        <div class="flex justify-between items-start mb-2 relative z-10">
                            <div>
                                <p class="text-slate-400 text-[11px] font-bold uppercase tracking-wider">Ticket Médio</p>
                                <h3 class="text-xl sm:text-2xl font-bold text-white mt-1" x-text="formatCurrency(calculateTicket())">R$ 0,00</h3>
                                <p class="text-xs text-slate-400 mt-1">Por venda aprovada</p>
                            </div>
                            <div class="p-2.5 bg-slate-800 border border-slate-700/60 rounded-xl text-purple-400 shadow-inner">
                                <i data-lucide="trending-up" class="w-5 h-5"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Pix Pendentes -->
                    <div class="bg-slate-900 border border-slate-800 p-4 sm:p-5 rounded-xl shadow-lg relative overflow-hidden group">
                        <div class="absolute -right-6 -top-6 bg-orange-500/10 w-24 h-24 rounded-full blur-2xl group-hover:bg-orange-500/20 transition"></div>
                        <div class="flex justify-between items-start mb-2 relative z-10">
                            <div>
                                <p class="text-slate-400 text-[11px] font-bold uppercase tracking-wider">Pix Pendentes</p>
                                <h3 class="text-xl sm:text-2xl font-bold text-white mt-1" x-text="stats.pending_orders">0</h3>
                                <p class="text-xs text-orange-400 mt-1 font-medium" x-text="formatCurrency(stats.pending_revenue)">R$ 0,00</p>
                            </div>
                            <div class="p-2.5 bg-slate-800 border border-slate-700/60 rounded-xl text-orange-400 shadow-inner">
                                <i data-lucide="clock" class="w-5 h-5"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Pix Manual - Cópias de Código -->
                    <div class="bg-slate-900 border border-slate-800 p-4 sm:p-5 rounded-xl shadow-lg relative overflow-hidden group">
                        <div class="absolute -right-6 -top-6 bg-teal-500/10 w-24 h-24 rounded-full blur-2xl group-hover:bg-teal-500/20 transition"></div>
                        <div class="flex justify-between items-start mb-2 relative z-10">
                            <div>
                                <p class="text-slate-400 text-[11px] font-bold uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-teal-400"></span>
                                    Pix Manual
                                </p>
                                <h3 class="text-xl sm:text-2xl font-bold text-white mt-1">
                                    <span x-text="stats.manual_pix_copied || 0">0</span>
                                    <span class="text-xs sm:text-sm font-normal text-slate-400">/ <span x-text="stats.manual_pix_total || 0">0</span> copiado(s)</span>
                                </h3>
                                <p class="text-xs text-teal-400 mt-1 font-medium flex items-center gap-1">
                                    <i data-lucide="copy" class="w-3 h-3"></i>
                                    <span x-text="(stats.manual_pix_rate || 0) + '% copiou (' + (stats.manual_pix_not_copied || 0) + ' sem cópia)'">0%</span>
                                </p>
                            </div>
                            <div class="p-2.5 bg-slate-800 border border-slate-700/60 rounded-xl text-teal-400 shadow-inner" title="Cópia de Pix Manual no Checkout">
                                <i data-lucide="copy-check" class="w-5 h-5"></i>
                            </div>
                        </div>
                    </div>

                    <!-- No Checkout (Tempo Real) -->
                    <div class="bg-slate-900 border border-slate-800 p-4 sm:p-5 rounded-xl shadow-lg relative overflow-hidden group">
                        <div class="absolute -right-6 -top-6 bg-indigo-500/10 w-24 h-24 rounded-full blur-2xl group-hover:bg-indigo-500/20 transition"></div>
                        <div class="flex justify-between items-start mb-2 relative z-10">
                            <div>
                                <p class="text-slate-400 text-[11px] font-bold uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="relative flex h-2 w-2">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                                    </span>
                                    No Checkout
                                </p>
                                <h3 class="text-xl sm:text-2xl font-bold text-white mt-1" x-text="stats.online_users">0</h3>
                                <p class="text-xs text-indigo-400 mt-1 font-medium">Usuários ativos agora</p>
                            </div>
                            <div class="p-2.5 bg-slate-800 border border-slate-700/60 rounded-xl text-indigo-400 shadow-inner">
                                <i data-lucide="users" class="w-5 h-5"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Preenchendo Inputs (Tempo Real) -->
                    <div class="bg-slate-900 border border-slate-800 p-4 sm:p-5 rounded-xl shadow-lg relative overflow-hidden group">
                        <div class="absolute -right-6 -top-6 bg-cyan-500/10 w-24 h-24 rounded-full blur-2xl group-hover:bg-cyan-500/20 transition"></div>
                        <div class="flex justify-between items-start mb-2 relative z-10">
                            <div>
                                <p class="text-slate-400 text-[11px] font-bold uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="flex gap-1 items-center">
                                        <span class="w-1.5 h-1.5 bg-cyan-400 rounded-full animate-bounce [animation-duration:1s]"></span>
                                        <span class="w-1.5 h-1.5 bg-cyan-400 rounded-full animate-bounce [animation-duration:1s] [animation-delay:0.2s]"></span>
                                        <span class="w-1.5 h-1.5 bg-cyan-400 rounded-full animate-bounce [animation-duration:1s] [animation-delay:0.4s]"></span>
                                    </span>
                                    Preenchendo
                                </p>
                                <h3 class="text-xl sm:text-2xl font-bold text-white mt-1" x-text="stats.typing_users">0</h3>
                                <p class="text-xs text-cyan-400 mt-1 font-medium">Digitando no checkout</p>
                            </div>
                            <div class="p-2.5 bg-slate-800 border border-slate-700/60 rounded-xl text-cyan-400 shadow-inner">
                                <i data-lucide="edit-3" class="w-5 h-5"></i>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- MAIN SECTION: DESEMPENHO POR PRODUTO & LISTA DE PEDIDOS COM INFINITE SCROLL -->
                <div class="grid grid-cols-1 xl:grid-cols-12 gap-5 sm:gap-6 items-start">

                    <!-- Sales By Product (5 Cols on XL) -->
                    <div class="xl:col-span-5 bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-xl flex flex-col max-h-[640px]">
                        <div class="p-4 sm:p-5 border-b border-slate-800 flex justify-between items-center bg-slate-900/90 shrink-0">
                            <div class="flex items-center gap-2">
                                <i data-lucide="bar-chart-2" class="w-4 h-4 text-blue-400"></i>
                                <h3 class="font-bold text-white text-sm sm:text-base">Desempenho por Produto</h3>
                            </div>
                            <span class="text-[11px] text-slate-400" x-text="(stats.sales_by_product ? stats.sales_by_product.length : 0) + ' produto(s)'"></span>
                        </div>
                        <div class="flex-1 overflow-x-auto overflow-y-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse min-w-[300px]">
                                <thead>
                                    <tr class="text-[11px] text-slate-400 border-b border-slate-800 bg-slate-900/60 sticky top-0 backdrop-blur z-10">
                                        <th class="p-3 sm:p-4 uppercase font-semibold">Produto</th>
                                        <th class="p-3 sm:p-4 uppercase font-semibold text-center">Qtd.</th>
                                        <th class="p-3 sm:p-4 uppercase font-semibold text-right">Faturamento</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/80">
                                    <template x-for="item in stats.sales_by_product" :key="item.name">
                                        <tr class="hover:bg-slate-800/40 transition">
                                            <td class="p-3 sm:p-4 text-xs sm:text-sm text-slate-200 font-medium line-clamp-2" x-text="item.name"></td>
                                            <td class="p-3 sm:p-4 text-xs sm:text-sm text-slate-400 text-center font-mono" x-text="item.qty"></td>
                                            <td class="p-3 sm:p-4 text-xs sm:text-sm text-emerald-400 text-right font-mono font-bold whitespace-nowrap"
                                                x-text="formatCurrency(item.revenue)"></td>
                                        </tr>
                                    </template>
                                    <template x-if="!stats.sales_by_product || stats.sales_by_product.length === 0">
                                        <tr>
                                            <td colspan="3" class="p-8 text-center text-slate-500 text-sm">
                                                Nenhum dado de venda disponível para este período.
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- All Orders with Infinite Scroll & Mark as Paid (7 Cols on XL) -->
                    <div class="xl:col-span-7 bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-xl flex flex-col h-[640px]">
                        
                        <!-- Card Header with Filters & Status -->
                        <div class="p-3.5 sm:p-4 border-b border-slate-800 flex flex-wrap items-center justify-between gap-2.5 bg-slate-900/90 shrink-0">
                            
                            <div class="flex items-center gap-2">
                                <i data-lucide="shopping-cart" class="w-4 h-4 text-blue-400"></i>
                                <h3 class="font-bold text-white text-sm sm:text-base">Lista de Pedidos</h3>
                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700/80 text-slate-300 font-mono"
                                      x-text="ordersTotalCount + ' pedido(s)'">0</span>
                            </div>

                            <div class="flex items-center gap-2 flex-wrap">
                                <!-- Status Filter -->
                                <select 
                                    x-model="orderStatusFilter" 
                                    @change="resetAndFetchOrders()"
                                    class="bg-slate-800 text-slate-200 text-xs border border-slate-700 rounded-lg px-2.5 py-1.5 outline-none focus:border-blue-500 transition cursor-pointer"
                                >
                                    <option value="all">Todos os Status</option>
                                    <option value="pending">Pendentes</option>
                                    <option value="paid">Pagos / Aprovados</option>
                                    <option value="manual_pix_copied">Pix Manual: Copiou</option>
                                    <option value="manual_pix_not_copied">Pix Manual: Não Copiou</option>
                                    <option value="manual_pix">Todos Pix Manual</option>
                                    <option value="cancelled">Cancelados</option>
                                </select>

                                <!-- Refresh Button -->
                                <button 
                                    type="button" 
                                    @click="resetAndFetchOrders()" 
                                    class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 transition"
                                    title="Recarregar pedidos"
                                >
                                    <i data-lucide="refresh-cw" class="w-3.5 h-3.5" :class="{ 'animate-spin': isLoadingOrders }"></i>
                                </button>

                                <a href="orders.php" 
                                   class="text-xs text-blue-400 hover:text-blue-300 transition flex items-center gap-1 font-medium px-2 py-1 rounded bg-blue-500/10 border border-blue-500/20 hover:bg-blue-500/20">
                                    <span>Gerenciar</span>
                                    <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Infinite Scrollable Orders Container -->
                        <div 
                            id="ordersScrollContainer"
                            class="flex-1 overflow-y-auto divide-y divide-slate-800/70 custom-scrollbar overscroll-contain"
                            @scroll.passive="onOrdersScroll($event)"
                        >
                            <!-- Orders Loop -->
                            <template x-for="order in orders" :key="order.id">
                                <div class="p-3.5 sm:p-4 hover:bg-slate-800/40 transition flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 group">
                                    
                                    <!-- Order Left Column: ID, Customer, Product, Date -->
                                    <div class="flex items-start sm:items-center gap-3 min-w-0 flex-1">
                                        
                                        <!-- ID Badge -->
                                        <div class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700/80 flex flex-col items-center justify-center shrink-0 group-hover:border-slate-600 transition shadow-inner">
                                            <span class="text-[9px] text-slate-400 font-semibold leading-none">ID</span>
                                            <span class="text-xs font-bold text-white font-mono leading-none mt-0.5" x-text="order.id"></span>
                                        </div>

                                        <!-- Customer & Product Information -->
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="text-sm font-bold text-white truncate max-w-[190px] sm:max-w-none" 
                                                      x-text="order.customer_name || 'Cliente Sem Nome'"></span>
                                                
                                                <!-- Mobile Status Badge -->
                                                <span x-show="['paid', 'completed'].includes((order.status || '').toLowerCase())"
                                                      class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 uppercase inline-flex items-center gap-1 sm:hidden">
                                                    <i data-lucide="check" class="w-3 h-3"></i> PAGO
                                                </span>
                                                <span x-show="['pending'].includes((order.status || '').toLowerCase())"
                                                      class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/30 uppercase inline-flex items-center gap-1 sm:hidden">
                                                    PENDENTE
                                                </span>
                                            </div>

                                            <p class="text-xs text-slate-300 truncate mt-0.5" x-text="order.product_name || 'Produto'"></p>
                                            
                                            <div class="flex items-center gap-2 mt-1 text-[11px] text-slate-400 flex-wrap">
                                                <span class="flex items-center gap-1">
                                                    <i data-lucide="calendar" class="w-3 h-3 text-slate-500"></i>
                                                    <span x-text="formatDate(order.created_at)"></span>
                                                </span>
                                                <template x-if="order.customer_phone">
                                                    <span class="text-slate-500 flex items-center gap-1">
                                                        <span>·</span>
                                                        <span x-text="order.customer_phone"></span>
                                                    </span>
                                                </template>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Order Right Column: Amount, Pix Copied, Mark as Paid Action -->
                                    <div class="flex items-center justify-between sm:justify-end sm:flex-col sm:items-end gap-2.5 shrink-0 pt-2.5 sm:pt-0 border-t border-slate-800/80 sm:border-0">
                                        
                                        <!-- Amount & Status Badge -->
                                        <div class="flex sm:flex-col items-baseline sm:items-end gap-2 sm:gap-1">
                                            <span class="text-sm sm:text-base font-bold text-emerald-400 font-mono tracking-tight" 
                                                  x-text="formatCurrency(order.total_amount)"></span>

                                            <!-- Pix Manual Copy Indicator -->
                                            <template x-if="order.gateway === 'manual_pix' || order.gateway === 'pix_manual' || order.gateway === 'direct_pix'">
                                                <div class="inline-flex">
                                                    <span x-show="order.pix_copied == 1"
                                                          class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase inline-flex items-center gap-1 bg-teal-500/10 text-teal-300 border-teal-500/30"
                                                          title="O cliente copiou o código Pix no checkout">
                                                        <i data-lucide="copy-check" class="w-3 h-3"></i> COPIOU PIX
                                                    </span>
                                                    <span x-show="!order.pix_copied || order.pix_copied == 0"
                                                          class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase inline-flex items-center gap-1 bg-amber-500/10 text-amber-400 border-amber-500/30"
                                                          title="O cliente ainda não copiou o código Pix">
                                                        <i data-lucide="copy-x" class="w-3 h-3"></i> NÃO COPIOU
                                                    </span>
                                                </div>
                                            </template>

                                            <!-- Desktop Status Badge (Non-Manual Pix) -->
                                            <template x-if="order.gateway !== 'manual_pix' && order.gateway !== 'pix_manual' && order.gateway !== 'direct_pix'">
                                                <span class="hidden sm:inline-block px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wider"
                                                      :class="{
                                                          'bg-emerald-500/10 text-emerald-400 border-emerald-500/30': ['paid', 'completed'].includes((order.status || '').toLowerCase()),
                                                          'bg-yellow-500/10 text-yellow-400 border-yellow-500/30': ['pending'].includes((order.status || '').toLowerCase()),
                                                          'bg-slate-800 text-slate-400 border-slate-700': !['paid', 'completed', 'pending'].includes((order.status || '').toLowerCase())
                                                      }"
                                                      x-text="['paid', 'completed'].includes((order.status || '').toLowerCase()) ? 'PAGO' : (order.status === 'pending' ? 'PENDENTE' : order.status)">
                                                </span>
                                            </template>
                                        </div>

                                        <!-- Mark as Paid Button -->
                                        <div>
                                            <template x-if="!['paid', 'completed'].includes((order.status || '').toLowerCase())">
                                                <button 
                                                    type="button"
                                                    @click.stop="markAsPaid(order.id)"
                                                    :disabled="isMarkingPaid === order.id"
                                                    class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 active:scale-95 text-white flex items-center gap-1.5 transition shadow-sm hover:shadow-emerald-500/25 border border-emerald-500/40 disabled:opacity-60 disabled:cursor-not-allowed whitespace-nowrap cursor-pointer"
                                                    title="Marcar como Pago e Enviar Entregáveis Automaticamente"
                                                >
                                                    <i x-show="isMarkingPaid !== order.id" data-lucide="check-circle-2" class="w-3.5 h-3.5"></i>
                                                    <i x-show="isMarkingPaid === order.id" data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i>
                                                    <span x-text="isMarkingPaid === order.id ? 'Processando...' : 'Marcar como pago'"></span>
                                                </button>
                                            </template>
                                            <template x-if="['paid', 'completed'].includes((order.status || '').toLowerCase())">
                                                <span class="hidden sm:inline-flex items-center gap-1 text-[11px] text-emerald-400 font-semibold px-2 py-0.5 rounded bg-emerald-500/10 border border-emerald-500/20">
                                                    <i data-lucide="check-check" class="w-3.5 h-3.5"></i> Aprovado
                                                </span>
                                            </template>
                                        </div>

                                    </div>
                                </div>
                            </template>

                            <!-- Sentinel Element for IntersectionObserver (Trigger next page) -->
                            <div x-ref="ordersSentinel" class="h-4 w-full"></div>

                            <!-- Loading More Indicator -->
                            <div x-show="isLoadingMoreOrders" class="p-4 flex items-center justify-center gap-2 text-slate-400 text-xs">
                                <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-blue-500"></i>
                                <span>Carregando mais pedidos...</span>
                            </div>

                            <!-- End of List Notice -->
                            <div x-show="!hasMoreOrders && orders.length > 0" class="p-4 text-center text-slate-500 text-xs border-t border-slate-800/40">
                                <span x-text="'Todos os ' + ordersTotalCount + ' pedido(s) foram carregados.'"></span>
                            </div>

                            <!-- Initial Loading State -->
                            <div x-show="isLoadingOrders && orders.length === 0" class="p-12 flex flex-col items-center justify-center gap-3 text-slate-400">
                                <i data-lucide="loader-2" class="w-6 h-6 animate-spin text-blue-500"></i>
                                <span class="text-xs sm:text-sm font-medium">Carregando pedidos da dashboard...</span>
                            </div>

                            <!-- Empty State -->
                            <div x-show="!isLoadingOrders && orders.length === 0" class="p-12 text-center text-slate-500 text-sm">
                                <i data-lucide="inbox" class="w-8 h-8 mx-auto text-slate-600 mb-2"></i>
                                <p>Nenhum pedido encontrado para o período/filtro selecionado.</p>
                            </div>

                        </div>
                    </div>

                </div>

            </main>
        </div>
    </div>

    <!-- Application Script -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) {
                lucide.createIcons();
            }
        });

        function dashboardApp() {
            return {
                stats: {
                    revenue: 0,
                    total_orders: 0,
                    paid_orders: 0,
                    pending_orders: 0,
                    pending_revenue: 0,
                    manual_pix_total: 0,
                    manual_pix_copied: 0,
                    manual_pix_not_copied: 0,
                    manual_pix_rate: 0,
                    conversion_rate: 0,
                    sales_by_product: [],
                    online_users: 0,
                    typing_users: 0
                },
                filter: 'today',
                startDate: '',
                endDate: '',
                isLoadingStats: true,

                // Orders List & Infinite Scroll State
                orders: [],
                ordersPage: 1,
                ordersLimit: 15,
                ordersTotalCount: 0,
                ordersTotalPages: 1,
                hasMoreOrders: true,
                isLoadingOrders: false,
                isLoadingMoreOrders: false,
                orderStatusFilter: 'all',
                isMarkingPaid: null,

                init() {
                    const today = new Date().toISOString().split('T')[0];
                    this.startDate = today;
                    this.endDate = today;

                    this.fetchStats();
                    this.resetAndFetchOrders();

                    // Setup IntersectionObserver for smooth infinite scroll on orders container
                    this.$nextTick(() => {
                        this.setupObserver();
                    });

                    // Auto-refresh KPI stats and real-time counter every 5s
                    setInterval(() => {
                        this.fetchStats(false);
                    }, 5000);
                },

                setupObserver() {
                    if (window.IntersectionObserver && this.$refs.ordersSentinel) {
                        const rootEl = document.getElementById('ordersScrollContainer');
                        const observer = new IntersectionObserver((entries) => {
                            if (entries[0].isIntersecting && !this.isLoadingOrders && !this.isLoadingMoreOrders && this.hasMoreOrders) {
                                this.loadMoreOrders();
                            }
                        }, {
                            root: rootEl,
                            rootMargin: '120px'
                        });
                        observer.observe(this.$refs.ordersSentinel);
                    }
                },

                onPeriodFilterChange() {
                    if (this.filter === 'custom' && (!this.startDate || !this.endDate)) {
                        return;
                    }
                    this.fetchStats(true);
                    this.resetAndFetchOrders();
                },

                fetchStats(showLoading = false) {
                    let query = `?searchDate=${this.filter}`;
                    if (this.filter === 'custom') {
                        if (!this.startDate || !this.endDate) return;
                        query += `&startDate=${this.startDate}&endDate=${this.endDate}`;
                    }

                    if (showLoading) {
                        this.isLoadingStats = true;
                    }

                    fetch('../api/v1/dashboard-stats.php' + query)
                        .then(res => res.json())
                        .then(data => {
                            this.stats = data;
                            this.isLoadingStats = false;
                            this.$nextTick(() => {
                                if (window.lucide) lucide.createIcons();
                            });
                        })
                        .catch(err => {
                            console.error('Error fetching dashboard stats:', err);
                            this.isLoadingStats = false;
                        });
                },

                resetAndFetchOrders() {
                    this.ordersPage = 1;
                    this.orders = [];
                    this.hasMoreOrders = true;
                    this.fetchOrders(false);
                },

                fetchOrders(isAppend = false) {
                    if (isAppend) {
                        this.isLoadingMoreOrders = true;
                    } else {
                        this.isLoadingOrders = true;
                    }

                    let url = `../api/v1/orders.php?page=${this.ordersPage}&limit=${this.ordersLimit}&status=${this.orderStatusFilter}&searchDate=${this.filter}`;
                    if (this.filter === 'custom') {
                        if (!this.startDate || !this.endDate) {
                            this.isLoadingOrders = false;
                            this.isLoadingMoreOrders = false;
                            return;
                        }
                        url += `&startDate=${this.startDate}&endDate=${this.endDate}`;
                    }

                    fetch(url)
                        .then(res => res.json())
                        .then(res => {
                            const newOrders = res.data || [];
                            this.ordersTotalCount = res.total_count || 0;
                            this.ordersTotalPages = res.total_pages || 1;

                            if (isAppend) {
                                // Deduplicate by ID
                                const existingIds = new Set(this.orders.map(o => o.id));
                                const uniqueNew = newOrders.filter(o => !existingIds.has(o.id));
                                this.orders = [...this.orders, ...uniqueNew];
                            } else {
                                this.orders = newOrders;
                            }

                            this.hasMoreOrders = this.ordersPage < this.ordersTotalPages;
                            this.isLoadingOrders = false;
                            this.isLoadingMoreOrders = false;

                            this.$nextTick(() => {
                                if (window.lucide) lucide.createIcons();
                            });
                        })
                        .catch(err => {
                            console.error('Error fetching orders:', err);
                            this.isLoadingOrders = false;
                            this.isLoadingMoreOrders = false;
                        });
                },

                loadMoreOrders() {
                    if (this.isLoadingOrders || this.isLoadingMoreOrders || !this.hasMoreOrders) return;
                    this.ordersPage++;
                    this.fetchOrders(true);
                },

                onOrdersScroll(e) {
                    const el = e.target;
                    // Trigger when 140px from the bottom
                    if (el.scrollHeight - el.scrollTop - el.clientHeight < 140) {
                        if (!this.isLoadingOrders && !this.isLoadingMoreOrders && this.hasMoreOrders) {
                            this.loadMoreOrders();
                        }
                    }
                },

                markAsPaid(orderId) {
                    if (!confirm(`Tem certeza que deseja marcar o Pedido #${orderId} como PAGO?\n\nIsso irá liberar o acesso e enviar automaticamente as mensagens de entrega (WhatsApp/Email) ao cliente.`)) {
                        return;
                    }

                    this.isMarkingPaid = orderId;

                    fetch('../api/v1/mark-order-paid.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ order_id: orderId })
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.isMarkingPaid = null;
                        if (data.success) {
                            // Update status in local array
                            const found = this.orders.find(o => o.id == orderId);
                            if (found) {
                                found.status = 'paid';
                            }
                            alert(data.message || `Pedido #${orderId} marcado como PAGO com sucesso!`);
                            // Refresh stats to reflect new revenue & paid counts
                            this.fetchStats(false);
                            this.$nextTick(() => {
                                if (window.lucide) lucide.createIcons();
                            });
                        } else {
                            alert('Erro: ' + (data.message || 'Falha ao processar pedido'));
                        }
                    })
                    .catch(err => {
                        console.error('Error marking order paid:', err);
                        this.isMarkingPaid = null;
                        alert('Erro de conexão ao marcar pedido como pago.');
                    });
                },

                formatCurrency(value) {
                    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
                },

                calculateTicket() {
                    if (this.stats.paid_orders > 0) return this.stats.revenue / this.stats.paid_orders;
                    return 0;
                },

                formatDate(dateString) {
                    if (!dateString) return '';
                    const date = new Date(dateString);
                    if (isNaN(date.getTime())) return dateString;
                    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + 
                           date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
                }
            };
        }
    </script>
</body>

</html>