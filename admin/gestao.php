<?php
session_start();
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
    <title>Gestão Financeira - RNT CRM</title>
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
                <a href="index.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Dashboard
                </a>
                <a href="pedidos.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i> Pedidos
                </a>
                <!-- Menu Gestão Ativo -->
                <a href="gestao.php" class="flex items-center gap-3 px-4 py-3 bg-blue-600/10 text-blue-400 rounded-lg border border-blue-600/20 font-medium">
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
                        Gestão de Lucro & ROI
                    </h2>
                </div>
                <div class="flex items-center gap-3">
                    
                    <!-- Seletor de Data Personalizada (Inicialmente Oculto) -->
                    <div id="custom-date-container" class="hidden flex items-center gap-2 bg-slate-800 border border-slate-700 rounded-lg p-1 animate-in fade-in slide-in-from-right-4 duration-300">
                        <input type="date" id="custom-start-date" class="bg-transparent text-white text-xs border-none focus:ring-0 outline-none p-1 [&::-webkit-calendar-picker-indicator]:invert">
                        <span class="text-slate-500 text-xs">até</span>
                        <input type="date" id="custom-end-date" class="bg-transparent text-white text-xs border-none focus:ring-0 outline-none p-1 [&::-webkit-calendar-picker-indicator]:invert">
                        <button id="apply-custom-date" class="bg-blue-600 hover:bg-blue-500 text-white px-3 py-1 rounded text-xs transition font-medium">OK</button>
                    </div>

                    <!-- Filtro de Data Global -->
                    <select id="report-date-filter" class="bg-slate-800 border border-slate-700 text-slate-200 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2.5 cursor-pointer">
                        <option value="today">Hoje</option>
                        <option value="yesterday">Ontem</option>
                        <option value="7d">Últimos 7 dias</option>
                        <option value="30d">Últimos 30 dias</option>
                        <option value="lifetime">Lifetime</option>
                        <option value="custom">📅 Personalizado</option>
                    </select>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-950 p-6">
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- Coluna da Esquerda: Input de Gastos -->
                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-lg h-fit">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                            <i data-lucide="plus-circle" class="text-blue-500"></i> Lançar Gastos (Ads)
                        </h3>
                        <form id="ad-spend-form" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-400 mb-1">Data do Gasto</label>
                                <input type="date" id="spend-date" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-blue-500 outline-none [&::-webkit-calendar-picker-indicator]:invert" required>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-400 mb-2">Produtos da Campanha</label>
                                <div class="bg-slate-950 border border-slate-700 rounded-lg p-3 max-h-40 overflow-y-auto custom-scrollbar" id="product-selection-container">
                                    <!-- Checkboxes serão injetados aqui -->
                                    <p class="text-xs text-slate-500 italic">Carregando produtos...</p>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">Selecione todos os produtos que essa campanha vende (Principal + Order Bumps/Downsells).</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-400 mb-1">Valor Investido (R$)</label>
                                <input type="number" id="spend-amount" step="0.01" placeholder="0.00" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-blue-500 outline-none" required>
                            </div>

                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition flex justify-center items-center gap-2">
                                <i data-lucide="save" class="w-4 h-4"></i> Salvar Gasto
                            </button>
                        </form>
                        
                        <div class="mt-8 border-t border-slate-800 pt-6">
                            <h4 class="text-sm font-semibold text-slate-400 mb-3">Últimos Lançamentos</h4>
                            <div id="recent-spends-list" class="space-y-2 max-h-48 overflow-y-auto pr-2 custom-scrollbar">
                                <!-- Lista via JS -->
                            </div>
                        </div>
                    </div>

                    <!-- Coluna da Direita: Relatório Financeiro -->
                    <div class="lg:col-span-2 space-y-6">
                        
                        <!-- Cards de Resumo -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
                                <p class="text-slate-400 text-xs uppercase font-bold">Lucro Líquido</p>
                                <h3 class="text-3xl font-bold text-white mt-1" id="kpi-profit">R$ 0,00</h3>
                                <p class="text-xs text-slate-500 mt-1">Receita - Ads</p>
                            </div>
                            <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
                                <p class="text-slate-400 text-xs uppercase font-bold">ROAS</p>
                                <h3 class="text-3xl font-bold text-white mt-1" id="kpi-roas">0.00x</h3>
                                <p class="text-xs text-slate-500 mt-1">Retorno sobre Ads</p>
                            </div>
                            <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
                                <p class="text-slate-400 text-xs uppercase font-bold">Margem</p>
                                <h3 class="text-3xl font-bold text-white mt-1" id="kpi-margin">0%</h3>
                                <p class="text-xs text-slate-500 mt-1">Margem de Lucro</p>
                            </div>
                        </div>

                        <!-- Detalhamento Financeiro -->
                        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-lg">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-lg font-bold text-white">Detalhamento Financeiro</h3>
                                <div class="text-sm text-slate-400">
                                    <span class="block text-right">Receita Total: <span id="det-revenue" class="text-green-400 font-bold">R$ 0,00</span></span>
                                    <span class="block text-right">Investimento Ads: <span id="det-ads" class="text-red-400 font-bold">R$ 0,00</span></span>
                                </div>
                            </div>
                            
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="text-xs text-slate-500 border-b border-slate-800 uppercase">
                                            <th class="pb-3">Produto / Funil</th>
                                            <th class="pb-3 text-right">Receita</th>
                                            <th class="pb-3 text-right">Ads (Gasto)</th>
                                            <th class="pb-3 text-right">Lucro</th>
                                            <th class="pb-3 text-right">ROAS</th>
                                        </tr>
                                    </thead>
                                    <tbody id="profit-table-body" class="text-sm text-slate-300">
                                        <!-- Preenchido via JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="assets/js/gestao.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>