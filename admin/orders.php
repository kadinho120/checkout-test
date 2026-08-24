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
                <a href="pix-rotations.php"
                    class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="key-round" class="w-5 h-5"></i> Chaves Pix
                </a>
                <a href="meta-events.php"
                    class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="activity" class="w-5 h-5"></i> Monitor Meta
                </a>
                <a href="tracking.php"
                    class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="scan-line" class="w-5 h-5"></i> Rastreamento
                </a>
                <a href="webhooks.php"
                    class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="webhook" class="w-5 h-5"></i> Webhooks
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

                <!-- Filtros de Busca e Data/Horário -->
                <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 mb-6 shadow-xl space-y-4">
                    <!-- Linha 1: Busca Geral + Status + Limite -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                        <!-- Campo de Busca -->
                        <div class="md:col-span-6 relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500 pointer-events-none">
                                <i data-lucide="search" class="w-4 h-4"></i>
                            </span>
                            <input type="text" x-model="filters.search" @keydown.enter="applyFilters()"
                                placeholder="Buscar por cliente, e-mail, telefone, ID, transação..."
                                class="w-full bg-slate-950 border border-slate-700 text-slate-200 text-sm rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block pl-10 p-2.5 placeholder-slate-500 transition">
                        </div>

                        <!-- Filtro de Status -->
                        <div class="md:col-span-3">
                            <select x-model="filters.status" @change="applyFilters()"
                                class="w-full bg-slate-950 border border-slate-700 text-slate-200 text-sm rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 cursor-pointer">
                                <option value="all">Todos os Status</option>
                                <option value="paid">Aprovados / Pagos</option>
                                <option value="pending">Pendentes</option>
                                <option value="cancelled">Cancelados / Expirados</option>
                            </select>
                        </div>

                        <!-- Limite por Página -->
                        <div class="md:col-span-3">
                            <select x-model="filters.limit" @change="applyFilters()"
                                class="w-full bg-slate-950 border border-slate-700 text-slate-200 text-sm rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 cursor-pointer">
                                <option value="10">10 por página</option>
                                <option value="25">25 por página</option>
                                <option value="50">50 por página</option>
                                <option value="100">100 por página</option>
                            </select>
                        </div>
                    </div>

                    <!-- Linha 2: Filtro por Data e Range de Horas -->
                    <div class="pt-3 border-t border-slate-800/80 grid grid-cols-1 lg:grid-cols-12 gap-4 items-center">
                        <!-- Seletor de Data -->
                        <div class="lg:col-span-5 flex flex-col sm:flex-row sm:items-center gap-2">
                            <label class="text-xs font-semibold text-slate-400 flex items-center gap-1.5 whitespace-nowrap min-w-[50px]">
                                <i data-lucide="calendar" class="w-3.5 h-3.5 text-blue-400"></i> Data:
                            </label>
                            <div class="flex-1 flex items-center gap-1.5">
                                <input type="date" x-model="filters.date" @change="applyFilters()"
                                    class="w-full bg-slate-950 border border-slate-700 text-slate-200 text-xs rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 p-2 [&::-webkit-calendar-picker-indicator]:invert cursor-pointer">
                                <button type="button" @click="setQuickDate('today')"
                                    :class="filters.date === getTodayString() ? 'bg-blue-600 text-white font-bold' : 'bg-slate-800 hover:bg-slate-700 text-slate-300'"
                                    class="px-2.5 py-1.5 rounded-lg text-xs transition whitespace-nowrap" title="Filtrar hoje">
                                    Hoje
                                </button>
                                <button type="button" @click="setQuickDate('yesterday')"
                                    :class="filters.date === getYesterdayString() ? 'bg-blue-600 text-white font-bold' : 'bg-slate-800 hover:bg-slate-700 text-slate-300'"
                                    class="px-2.5 py-1.5 rounded-lg text-xs transition whitespace-nowrap" title="Filtrar ontem">
                                    Ontem
                                </button>
                                <button type="button" x-show="filters.date" @click="filters.date = ''; applyFilters()"
                                    class="p-1.5 text-slate-400 hover:text-red-400 hover:bg-slate-800 rounded-lg transition" title="Remover data">
                                    <i data-lucide="x" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Range de Horas -->
                        <div class="lg:col-span-5 flex flex-col sm:flex-row sm:items-center gap-2">
                            <label class="text-xs font-semibold text-slate-400 flex items-center gap-1.5 whitespace-nowrap min-w-[50px]">
                                <i data-lucide="clock" class="w-3.5 h-3.5 text-blue-400"></i> Horário:
                            </label>
                            <div class="flex-1 flex items-center gap-1.5">
                                <input type="time" x-model="filters.startTime" @change="applyFilters()"
                                    class="w-full bg-slate-950 border border-slate-700 text-slate-200 text-xs rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 p-2 [&::-webkit-calendar-picker-indicator]:invert"
                                    placeholder="00:00" title="Hora Inicial">
                                <span class="text-slate-500 text-xs font-medium">até</span>
                                <input type="time" x-model="filters.endTime" @change="applyFilters()"
                                    class="w-full bg-slate-950 border border-slate-700 text-slate-200 text-xs rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 p-2 [&::-webkit-calendar-picker-indicator]:invert"
                                    placeholder="23:59" title="Hora Final">
                                <button type="button" x-show="filters.startTime || filters.endTime" @click="filters.startTime = ''; filters.endTime = ''; applyFilters()"
                                    class="p-1.5 text-slate-400 hover:text-red-400 hover:bg-slate-800 rounded-lg transition" title="Remover horário">
                                    <i data-lucide="x" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Botões de Ação (Filtrar e Limpar) -->
                        <div class="lg:col-span-2 flex items-center gap-2 justify-end">
                            <button type="button" @click="applyFilters()"
                                class="flex-1 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold py-2.5 px-3 rounded-lg flex items-center justify-center gap-1.5 transition shadow hover:shadow-blue-500/20 active:scale-95">
                                <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                                <span>Filtrar</span>
                            </button>
                            <button type="button" @click="clearFilters()"
                                class="bg-slate-800 hover:bg-slate-700 hover:text-red-400 text-slate-400 text-xs font-medium p-2.5 rounded-lg border border-slate-700 transition"
                                title="Limpar todos os filtros">
                                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Linha 3: Atalhos de Horário Rápidos + Status dos Filtros Ativos -->
                    <div class="pt-2 flex flex-wrap items-center justify-between gap-3 text-xs">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-slate-500 text-[11px] font-medium mr-1">Faixas rápidas:</span>
                            <button type="button" @click="setQuickTime('06:00', '12:00')"
                                class="px-2 py-1 rounded bg-slate-800/80 hover:bg-slate-700 text-slate-300 text-[11px] border border-slate-700/60 transition">
                                🌅 Manhã (06h-12h)
                            </button>
                            <button type="button" @click="setQuickTime('12:00', '18:00')"
                                class="px-2 py-1 rounded bg-slate-800/80 hover:bg-slate-700 text-slate-300 text-[11px] border border-slate-700/60 transition">
                                ☀️ Tarde (12h-18h)
                            </button>
                            <button type="button" @click="setQuickTime('18:00', '23:59')"
                                class="px-2 py-1 rounded bg-slate-800/80 hover:bg-slate-700 text-slate-300 text-[11px] border border-slate-700/60 transition">
                                🌙 Noite (18h-23h59)
                            </button>
                            <button type="button" @click="setQuickTime('00:00', '23:59')"
                                class="px-2 py-1 rounded bg-slate-800/80 hover:bg-slate-700 text-slate-300 text-[11px] border border-slate-700/60 transition">
                                ⏱️ Dia Todo
                            </button>
                        </div>

                        <!-- Indicador de Registros / Filtros Ativos -->
                        <div class="flex items-center gap-2">
                            <span class="text-slate-400 font-medium">
                                Total encontrado: <strong class="text-white" x-text="totalCount"></strong> pedidos
                            </span>
                            <template x-if="hasActiveFilters()">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20 text-[10px] font-semibold">
                                    <i data-lucide="check" class="w-3 h-3"></i> Filtros ativos
                                </span>
                            </template>
                        </div>
                    </div>
                </div>

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
                                <th class="p-4 uppercase font-medium text-center">Ações</th>
                            </tr>
                        </thead>
                        <template x-for="order in orders" :key="order.id">
                            <tbody class="divide-y divide-slate-800 border-b border-slate-800">
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
                                        <div class="flex items-center justify-center gap-1.5 mb-1.5">
                                            <span x-show="order.status === 'paid'"
                                                class="bg-green-500/10 text-green-400 border border-green-500/20 px-2 py-0.5 rounded text-xs font-bold">PAGO</span>
                                            <span x-show="order.status === 'pending'"
                                                class="bg-yellow-500/10 text-yellow-400 border border-yellow-500/20 px-2 py-0.5 rounded text-xs font-bold">PENDENTE</span>
                                            <span x-show="order.gateway === 'manual_pix'"
                                                class="bg-purple-500/10 text-purple-400 border border-purple-500/20 px-1.5 py-0.5 rounded text-[10px] font-mono font-bold" title="Pix Manual">MANUAL</span>
                                        </div>

                                        <!-- Botão Marcar Como Pago (Para Pedidos Pendentes / Pix Manual) -->
                                        <div x-show="order.status === 'pending'" class="mb-2 flex justify-center">
                                            <button @click="markAsPaid(order.id)"
                                                :disabled="isMarkingPaid === order.id"
                                                class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs px-2.5 py-1 rounded border border-emerald-500/30 flex items-center gap-1 transition shadow hover:shadow-emerald-500/20 active:scale-95"
                                                title="Marcar como Pago e Enviar Acessos Automaticamente">
                                                <i x-show="isMarkingPaid !== order.id" data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                                                <i x-show="isMarkingPaid === order.id" data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i>
                                                <span>Marcar Pago</span>
                                            </button>
                                        </div>

                                        <!-- Actions quando Pago -->
                                        <div x-show="order.status === 'paid'" class="mt-1 flex justify-center gap-2">
                                            <!-- Reenviar WhatsApp -->
                                            <button @click="resendDeliverable(order.id, 'wpp')"
                                                :disabled="isResending === order.id + '_wpp'"
                                                class="text-xs bg-slate-800 hover:bg-slate-700 text-green-400 px-2 py-1 rounded border border-slate-700 flex items-center gap-1 transition"
                                                title="Reenviar WhatsApp">
                                                <i x-show="isResending !== order.id + '_wpp'"
                                                    data-lucide="message-circle" class="w-3 h-3"></i>
                                                <i x-show="isResending === order.id + '_wpp'" data-lucide="loader-2"
                                                    class="w-3 h-3 animate-spin"></i>
                                            </button>
                                            <!-- Reenviar Email -->
                                            <button @click="resendDeliverable(order.id, 'email')"
                                                :disabled="isResending === order.id + '_email'"
                                                class="text-xs bg-slate-800 hover:bg-slate-700 text-blue-400 px-2 py-1 rounded border border-slate-700 flex items-center gap-1 transition"
                                                title="Reenviar E-mail">
                                                <i x-show="isResending !== order.id + '_email'" data-lucide="mail"
                                                    class="w-3 h-3"></i>
                                                <i x-show="isResending === order.id + '_email'" data-lucide="loader-2"
                                                    class="w-3 h-3 animate-spin"></i>
                                            </button>
                                        </div>

                                        <!-- Actions de Recuperação quando Pendente -->
                                        <div x-show="order.status === 'pending'" class="flex justify-center gap-2">
                                            <!-- Recuperar WhatsApp -->
                                            <button @click="recoverPix(order.id, 'wpp')"
                                                :disabled="isRecovering === order.id + '_wpp'"
                                                class="text-xs bg-slate-800 hover:bg-slate-700 text-green-500 px-2 py-1 rounded border border-slate-700 flex items-center gap-1 transition"
                                                title="Recuperar via WhatsApp">
                                                <i x-show="isRecovering !== order.id + '_wpp'"
                                                    data-lucide="message-square" class="w-3 h-3"></i>
                                                <i x-show="isRecovering === order.id + '_wpp'" data-lucide="loader-2"
                                                    class="w-3 h-3 animate-spin"></i>
                                            </button>
                                            <!-- Recuperar Email -->
                                            <button @click="recoverPix(order.id, 'email')"
                                                :disabled="isRecovering === order.id + '_email'"
                                                class="text-xs bg-slate-800 hover:bg-slate-700 text-blue-500 px-2 py-1 rounded border border-slate-700 flex items-center gap-1 transition"
                                                title="Recuperar via E-mail">
                                                <i x-show="isRecovering !== order.id + '_email'" data-lucide="mail"
                                                    class="w-3 h-3"></i>
                                                <i x-show="isRecovering === order.id + '_email'" data-lucide="loader-2"
                                                    class="w-3 h-3 animate-spin"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="p-4 text-sm text-slate-400"
                                        x-text="new Date(order.created_at).toLocaleString()"></td>
                                    <td class="p-4 text-center">
                                        <button @click="order.expanded = !order.expanded" 
                                            class="bg-blue-600 hover:bg-blue-500 text-white text-[10px] font-bold px-3 py-1.5 rounded transition uppercase tracking-wider">
                                            Detalhes
                                        </button>
                                    </td>
                                </tr>
                                <!-- Expanded Address Details -->
                                <tr x-show="order.expanded" x-cloak class="bg-slate-900/80">
                                    <td colspan="6" class="p-0">
                                        <div class="p-6">
                                            <template x-if="order.cep">
                                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-left">
                                                    <div class="space-y-1">
                                                        <p class="text-[10px] font-black text-blue-500 uppercase tracking-widest">Endereço de Entrega</p>
                                                        <p class="text-white text-sm" x-text="order.address + ', ' + order.address_number"></p>
                                                        <p class="text-slate-400 text-xs" x-text="order.neighborhood + ' - CEP: ' + order.cep"></p>
                                                        <p class="text-slate-400 text-xs" x-text="order.city + ' / ' + order.state"></p>
                                                    </div>
                                                    <div class="space-y-1">
                                                        <p class="text-[10px] font-black text-blue-500 uppercase tracking-widest">Complemento</p>
                                                        <p class="text-white text-sm" x-text="order.complement || '---'"></p>
                                                    </div>
                                                    <div class="space-y-1">
                                                        <p class="text-[10px] font-black text-blue-500 uppercase tracking-widest">ID Externo</p>
                                                        <p class="text-white text-xs font-mono break-all" x-text="order.external_id || '---'"></p>
                                                    </div>
                                                </div>
                                            </template>
                                            <template x-if="!order.cep">
                                                <div class="flex items-center gap-2 text-slate-500 text-sm italic">
                                                    <i data-lucide="info" class="w-4 h-4"></i>
                                                    Produto Digital: Nenhuma informação de entrega coletada.
                                                </div>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </template>
                        <template x-if="orders.length === 0">
                            <tbody class="divide-y divide-slate-800">
                                <tr>
                                    <td colspan="7" class="p-8 text-center text-slate-500">
                                        Nenhum pedido encontrado.
                                    </td>
                                </tr>
                            </tbody>
                        </template>
                    </table>

                    <!-- Pagination Controls -->
                    <div x-show="totalPages > 1" class="px-6 py-4 bg-slate-900 border-t border-slate-800 flex items-center justify-between">
                        <div class="text-xs text-slate-400">
                            Mostrando <span class="font-semibold text-white" x-text="totalCount > 0 ? ((page - 1) * filters.limit) + 1 : 0"></span> a 
                            <span class="font-semibold text-white" x-text="Math.min(page * filters.limit, totalCount)"></span> de 
                            <span class="font-semibold text-white" x-text="totalCount"></span> pedidos
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

            </main>
        </div>

    </div>

    <!-- App Logic -->
    <script>
        lucide.createIcons();
        function ordersApp() {
            return {
                orders: [],
                page: 1,
                totalPages: 1,
                totalCount: 0,
                isLoading: true,
                isResending: null,
                isRecovering: null,
                isMarkingPaid: null,

                filters: {
                    date: '',
                    startTime: '',
                    endTime: '',
                    status: 'all',
                    search: '',
                    limit: 10
                },

                init() {
                    this.fetchOrders();
                },

                getTodayString() {
                    const d = new Date();
                    const year = d.getFullYear();
                    const month = String(d.getMonth() + 1).padStart(2, '0');
                    const day = String(d.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day}`;
                },

                getYesterdayString() {
                    const d = new Date();
                    d.setDate(d.getDate() - 1);
                    const year = d.getFullYear();
                    const month = String(d.getMonth() + 1).padStart(2, '0');
                    const day = String(d.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day}`;
                },

                setQuickDate(type) {
                    if (type === 'today') {
                        this.filters.date = this.getTodayString();
                    } else if (type === 'yesterday') {
                        this.filters.date = this.getYesterdayString();
                    }
                    this.applyFilters();
                },

                setQuickTime(start, end) {
                    this.filters.startTime = start;
                    this.filters.endTime = end;
                    this.applyFilters();
                },

                hasActiveFilters() {
                    return !!(
                        this.filters.date ||
                        this.filters.startTime ||
                        this.filters.endTime ||
                        (this.filters.status && this.filters.status !== 'all') ||
                        (this.filters.search && this.filters.search.trim().length > 0)
                    );
                },

                applyFilters() {
                    this.page = 1;
                    this.fetchOrders();
                },

                clearFilters() {
                    this.filters.date = '';
                    this.filters.startTime = '';
                    this.filters.endTime = '';
                    this.filters.status = 'all';
                    this.filters.search = '';
                    this.page = 1;
                    this.fetchOrders();
                },

                fetchOrders() {
                    this.isLoading = true;
                    const params = new URLSearchParams({
                        page: this.page,
                        limit: this.filters.limit
                    });

                    if (this.filters.date) params.append('date', this.filters.date);
                    if (this.filters.startTime) params.append('start_time', this.filters.startTime);
                    if (this.filters.endTime) params.append('end_time', this.filters.endTime);
                    if (this.filters.status && this.filters.status !== 'all') params.append('status', this.filters.status);
                    if (this.filters.search && this.filters.search.trim()) params.append('search', this.filters.search.trim());

                    fetch(`../api/v1/orders.php?${params.toString()}`)
                        .then(res => res.json())
                        .then(data => {
                            this.orders = (data.data || []).map(o => ({ ...o, expanded: false }));
                            this.totalPages = data.total_pages || 1;
                            this.totalCount = data.total_count || 0;
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
                        this.fetchOrders();
                    }
                },

                prevPage() {
                    if (this.page > 1) {
                        this.page--;
                        this.fetchOrders();
                    }
                },

                goToPage(p) {
                    this.page = p;
                    this.fetchOrders();
                },

                resendDeliverable(orderId, type) {
                    const actionName = type === 'wpp' ? 'WhatsApp' : 'E-mail';
                    if (!confirm(`Deseja reenviar o pedido #${orderId} via ${actionName}?`)) return;

                    const loadingKey = orderId + '_' + type;
                    this.isResending = loadingKey;
                    
                    fetch('../api/v1/resend-deliverable.php', {
                        method: 'POST',
                        body: JSON.stringify({ order_id: orderId, type: type })
                    })
                        .then(res => res.json())
                        .then(data => {
                            this.isResending = null;
                            if (data.success) {
                                alert(`Envio (${actionName}) realizado com sucesso!`);
                            } else {
                                alert('Erro: ' + data.message);
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            this.isResending = null;
                            alert('Erro de conexão ao reenviar.');
                        });
                },

                recoverPix(orderId, type) {
                    const actionName = type === 'wpp' ? 'WhatsApp' : 'E-mail';
                    if (!confirm(`Enviar recuperação de Pix (Pedido #${orderId}) via ${actionName}?`)) return;

                    const loadingKey = orderId + '_' + type;
                    this.isRecovering = loadingKey;

                    fetch('../api/v1/recover-pix.php', {
                        method: 'POST',
                        body: JSON.stringify({ order_id: orderId, type: type })
                    })
                        .then(res => res.json())
                        .then(data => {
                            this.isRecovering = null;
                            if (data.success) {
                                alert(`Recuperação (${actionName}) enviada!`);
                            } else {
                                alert('Erro: ' + data.message);
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            this.isRecovering = null;
                            alert('Erro de conexão ao recuperar.');
                        });
                },

                markAsPaid(orderId) {
                    if (!confirm(`Tem certeza que deseja marcar o Pedido #${orderId} como PAGO?\n\nIsso irá liberar o acesso e enviar automaticamente as mensagens de entrega (WhatsApp/Email) ao cliente.`)) return;

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
                                alert(data.message || `Pedido #${orderId} marcado como PAGO com sucesso!`);
                                this.fetchOrders();
                            } else {
                                alert('Erro: ' + (data.message || 'Falha ao processar pedido'));
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            this.isMarkingPaid = null;
                            alert('Erro de conexão ao marcar pedido como pago.');
                        });
                }
            }
        }

    </script>
</body>

</html>