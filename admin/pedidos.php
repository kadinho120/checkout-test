<?php
session_start();

// Segurança: Mesma lógica do index.php
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos - RNT CRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-slate-950 text-slate-200 font-sans antialiased">

    <div class="flex h-screen overflow-hidden">
        
        <!-- Sidebar -->
        <aside class="w-64 bg-slate-900 border-r border-slate-800 hidden md:flex flex-col">
            <div class="p-6 border-b border-slate-800 flex items-center gap-3">
                <div class="w-10 h-8 bg-blue-600 rounded-lg flex items-center justify-center font-bold text-white text-xs">RNT</div>
                <span class="font-bold text-lg tracking-tight text-white">RNT CRM</span>
            </div>
            
            <nav class="flex-1 p-4 space-y-2">
                <!-- Menu Dashboard (Link) -->
                <a href="index.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Dashboard
                </a>
                <!-- Menu Pedidos (Ativo) -->
                <a href="pedidos.php" class="flex items-center gap-3 px-4 py-3 bg-blue-600/10 text-blue-400 rounded-lg border border-blue-600/20 font-medium">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i> Pedidos
                </a>
                <!-- Menu Gestão (Link) -->
                <a href="gestao.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="bar-chart-3" class="w-5 h-5"></i> Gestão
                </a>
            </nav>

            <div class="p-4 border-t border-slate-800">
                <div class="flex items-center gap-3 px-4 py-2">
                    <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-xs">KD</div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">Kadinho</p>
                        <p class="text-xs text-slate-500 truncate">Admin</p>
                    </div>
                    <a href="index.php?logout=true" class="text-slate-400 hover:text-red-400 transition" title="Sair">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden relative">
            <header class="h-16 bg-slate-900/50 backdrop-blur border-b border-slate-800 flex items-center justify-between px-6">
                <div class="flex items-center gap-4">
                    <button class="md:hidden text-slate-400"><i data-lucide="menu"></i></button>
                    <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                        Gerenciar Pedidos
                        <span id="loading-indicator" class="hidden">
                            <i data-lucide="refresh-cw" class="w-3 h-3 animate-spin text-blue-500"></i>
                        </span>
                    </h2>
                </div>

                 <!-- Filtro de Data (Novo) -->
                 <div class="flex items-center gap-3">
                     <!-- Seletor de Data Personalizada (Inicialmente Oculto) -->
                     <div id="custom-date-container" class="hidden flex items-center gap-2 bg-slate-800 border border-slate-700 rounded-lg p-1 animate-in fade-in slide-in-from-right-4 duration-300">
                        <input type="date" id="custom-start-date" class="bg-transparent text-white text-xs border-none focus:ring-0 outline-none p-1 [&::-webkit-calendar-picker-indicator]:invert">
                        <span class="text-slate-500 text-xs">até</span>
                        <input type="date" id="custom-end-date" class="bg-transparent text-white text-xs border-none focus:ring-0 outline-none p-1 [&::-webkit-calendar-picker-indicator]:invert">
                        <button id="apply-custom-date" class="bg-blue-600 hover:bg-blue-500 text-white px-3 py-1 rounded text-xs transition font-medium">OK</button>
                    </div>

                    <select id="date-filter" class="bg-slate-800 border border-slate-700 text-slate-200 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2.5 cursor-pointer">
                        <option value="lifetime">Todo o período</option>
                        <option value="today">Hoje</option>
                        <option value="yesterday">Ontem</option>
                        <option value="7d">Últimos 7 dias</option>
                        <option value="30d">Últimos 30 dias</option>
                        <option value="custom">📅 Personalizado</option>
                    </select>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-950 p-6">
                
                <!-- Filtros -->
                <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 mb-6 flex flex-col md:flex-row gap-4 justify-between items-center shadow-lg">
                    <!-- Busca -->
                    <div class="relative w-full md:w-96">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">
                            <i data-lucide="search" class="w-4 h-4"></i>
                        </span>
                        <input type="text" id="search-input" placeholder="Buscar por nome, email, ID..." class="w-full bg-slate-950 border border-slate-700 text-slate-200 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block pl-10 p-2.5 placeholder-slate-600 transition">
                    </div>
                    
                    <!-- Filtro de Status -->
                    <div class="flex gap-4 w-full md:w-auto">
                        <select id="status-filter" class="bg-slate-950 border border-slate-700 text-slate-200 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2.5 w-full md:w-40 cursor-pointer">
                            <option value="all">Todos Status</option>
                            <option value="paid">Aprovados</option>
                            <option value="pending">Pendentes</option>
                            <option value="cancelled">Cancelados/Exp</option>
                        </select>
                        
                        <!-- Botão Atualizar Manual -->
                        <button id="refresh-btn" class="p-2.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-lg text-slate-300 transition" title="Atualizar Lista">
                            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <!-- Tabela -->
                <div class="bg-slate-900 border border-slate-800 rounded-xl shadow-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-xs text-slate-400 border-b border-slate-800 bg-slate-950/50 uppercase tracking-wider">
                                    <th class="p-4 font-medium">ID / Data</th>
                                    <th class="p-4 font-medium">Cliente</th>
                                    <th class="p-4 font-medium">WhatsApp</th>
                                    <th class="p-4 font-medium">Produto</th>
                                    <th class="p-4 font-medium text-right">Valor</th>
                                    <th class="p-4 font-medium text-center">Status</th>
                                    <th class="p-4 font-medium text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="orders-table-body" class="text-sm divide-y divide-slate-800">
                                <!-- Preenchido via JS -->
                                <tr><td colspan="6" class="p-8 text-center text-slate-500">Carregando pedidos...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Rodapé da Tabela -->
                    <div class="p-4 border-t border-slate-800 bg-slate-900 text-xs text-slate-500 flex flex-col sm:flex-row justify-between items-center gap-2">
                        <span id="total-records">Calculando...</span>
                        <div class="flex gap-2">
                            <!-- Paginação simples pode ser adicionada aqui no futuro -->
                            <span class="px-2 py-1 bg-slate-800 rounded border border-slate-700">Mostrando todos os registros recentes</span>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- JS Específico de Pedidos -->
    <script src="assets/js/pedidos.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>