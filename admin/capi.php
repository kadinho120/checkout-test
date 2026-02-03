<?php
require_once 'auth.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testar Meta CAPI - Admin</title>
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

<body class="bg-slate-950 text-slate-200 font-sans antialiased" x-data="capiTester()">

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
                    class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition"><i
                        data-lucide="layout-dashboard" class="w-5 h-5"></i> Dashboard</a>
                <a href="products.php"
                    class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition"><i
                        data-lucide="package" class="w-5 h-5"></i> Produtos</a>
                <a href="orders.php"
                    class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition"><i
                        data-lucide="shopping-cart" class="w-5 h-5"></i> Pedidos</a>
                <a href="tracking.php"
                    class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition"><i
                        data-lucide="scan-line" class="w-5 h-5"></i> Rastreamento</a>
                <a href="capi.php"
                    class="flex items-center gap-3 px-4 py-3 bg-blue-600/10 text-blue-400 rounded-lg border border-blue-600/20 font-medium"><i
                        data-lucide="activity" class="w-5 h-5"></i> Testar CAPI</a>
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

        <div class="flex-1 flex flex-col overflow-hidden relative">
            <header
                class="h-16 bg-slate-900/50 backdrop-blur border-b border-slate-800 flex items-center justify-between px-6">
                <h2 class="text-lg font-semibold text-white">Testador de API de Conversões (CAPI)</h2>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-950 p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Config Box -->
                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-lg h-fit">
                        <h3 class="text-white font-bold mb-4 flex items-center gap-2"><i data-lucide="settings"
                                class="w-5 h-5 text-blue-500"></i> Configuração</h3>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-400 mb-1">Pixel ID</label>
                                <input type="text" x-model="pixelId"
                                    class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3 text-white focus:border-blue-500 outline-none"
                                    placeholder="Ex: 123456789">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-400 mb-1">Access Token</label>
                                <textarea x-model="token" rows="3"
                                    class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3 text-white focus:border-blue-500 outline-none font-mono text-xs"
                                    placeholder="EAAB..."></textarea>
                            </div>

                            <hr class="border-slate-800 my-4">

                            <div class="grid grid-cols-1 gap-3">
                                <button @click="testEvent('InitiateCheckout')" :disabled="isLoading"
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition flex items-center justify-center gap-2">
                                    <i data-lucide="shopping-bag" class="w-4 h-4"></i> Testar InitiateCheckout
                                </button>
                                <button @click="testEvent('AddPaymentInfo')" :disabled="isLoading"
                                    class="w-full bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-3 rounded-lg transition flex items-center justify-center gap-2">
                                    <i data-lucide="credit-card" class="w-4 h-4"></i> Testar AddPaymentInfo
                                </button>
                                <button @click="testEvent('Purchase')" :disabled="isLoading"
                                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition flex items-center justify-center gap-2">
                                    <i data-lucide="check-circle" class="w-4 h-4"></i> Testar Purchase
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Log Console -->
                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-lg flex flex-col h-[600px]">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-white font-bold flex items-center gap-2"><i data-lucide="terminal"
                                    class="w-5 h-5 text-green-500"></i> Logs de Resposta</h3>
                            <button @click="logs = []" class="text-xs text-slate-500 hover:text-white">Limpar</button>
                        </div>

                        <div
                            class="flex-1 bg-slate-950 rounded-lg p-4 overflow-y-auto font-mono text-xs text-slate-300 space-y-4 border border-slate-800">
                            <template x-if="logs.length === 0">
                                <span class="text-slate-600 italic">Aguardando testes...</span>
                            </template>
                            <template x-for="(log, idx) in logs" :key="idx">
                                <div class="border-b border-slate-800 pb-2 mb-2 last:border-0 last:pb-0">
                                    <div class="flex justify-between text-slate-500 mb-1">
                                        <span x-text="log.time"></span>
                                        <span x-text="log.event" class="font-bold text-slate-300"></span>
                                    </div>
                                    <div class="mb-1">
                                        Status: <span :class="log.success ? 'text-green-400' : 'text-red-400'"
                                            x-text="log.status"></span>
                                    </div>
                                    <pre x-text="JSON.stringify(log.data, null, 2)"
                                        class="text-slate-400 break-all whitespace-pre-wrap"></pre>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function capiTester() {
            return {
                pixelId: '',
                token: '',
                isLoading: false,
                logs: [],

                async testEvent(eventName) {
                    if (!this.pixelId || !this.token) {
                        alert('Preencha Pixel ID e Token!');
                        return;
                    }

                    this.isLoading = true;
                    try {
                        const res = await fetch('../api/v1/test-capi.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                pixel_id: this.pixelId,
                                token: this.token,
                                event_name: eventName
                            })
                        });
                        const data = await res.json();

                        this.logs.unshift({
                            time: new Date().toLocaleTimeString(),
                            event: eventName,
                            success: data.success,
                            status: data.success ? 'SUCESSO (200)' : 'ERRO (' + (data.http_code || 'N/A') + ')',
                            data: data.meta_response || data
                        });

                    } catch (e) {
                        this.logs.unshift({
                            time: new Date().toLocaleTimeString(),
                            event: eventName,
                            success: false,
                            status: 'ERRO DE REDE',
                            data: { message: e.message }
                        });
                    } finally {
                        this.isLoading = false;
                    }
                }
            }
        }
    </script>
</body>

</html>