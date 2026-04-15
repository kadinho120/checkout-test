<?php
require_once 'auth.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações de Webhooks - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>

<body class="bg-slate-950 text-slate-200 font-sans antialiased" x-data="webhooksApp()">

    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
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
                <a href="meta-events.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="facebook" class="w-5 h-5"></i> Monitor Meta
                </a>
                <a href="tracking.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="scan-line" class="w-5 h-5"></i> Rastreamento
                </a>
                <a href="webhooks.php" class="flex items-center gap-3 px-4 py-3 bg-blue-600/10 text-blue-400 rounded-lg border border-blue-600/20 font-medium">
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
                    <a href="login.php?logout=true" class="text-slate-400 hover:text-red-400 transition"><i data-lucide="log-out" class="w-4 h-4"></i></a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="h-16 bg-slate-900 border-b border-slate-800 flex items-center justify-between px-6 shrink-0">
                <h2 class="text-lg font-semibold text-white">Gerenciar Webhooks</h2>
                <button @click="openModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-bold transition flex items-center gap-2">
                    <i data-lucide="plus" class="w-4 h-4"></i> Novo Webhook
                </button>
            </header>

            <main class="flex-1 overflow-y-auto p-6 bg-slate-950">
                <div x-show="isLoading" class="flex justify-center py-12">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                </div>

                <div x-show="!isLoading" x-cloak>
                    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-lg">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-xs text-slate-400 border-b border-slate-800 bg-slate-900/50">
                                    <th class="p-4 uppercase font-medium">URL do Webhook</th>
                                    <th class="p-4 uppercase font-medium">Eventos</th>
                                    <th class="p-4 uppercase font-medium text-center">Status</th>
                                    <th class="p-4 uppercase font-medium text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                <template x-for="webhook in webhooks" :key="webhook.id">
                                    <tr class="hover:bg-slate-800/50 transition">
                                        <td class="p-4">
                                            <p class="text-sm text-white font-medium truncate max-w-md" x-text="webhook.url"></p>
                                            <p class="text-[10px] text-slate-500 mt-0.5" x-text="'Criado em: ' + formatDate(webhook.created_at)"></p>
                                        </td>
                                        <td class="p-4">
                                            <div class="flex flex-wrap gap-1">
                                                <template x-for="event in webhook.events.split(',')" :key="event">
                                                    <span class="px-2 py-0.5 bg-slate-800 border border-slate-700 rounded text-[10px] text-slate-400" x-text="event.trim()"></span>
                                                </template>
                                            </div>
                                        </td>
                                        <td class="p-4 text-center">
                                            <span :class="webhook.active == 1 ? 'bg-green-500/10 text-green-500 border-green-500/20' : 'bg-slate-800 text-slate-500 border-slate-700'" 
                                                  class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase"
                                                  x-text="webhook.active == 1 ? 'ATIVO' : 'INATIVO'">
                                            </span>
                                        </td>
                                        <td class="p-4 text-right">
                                            <div class="flex justify-end gap-2">
                                                <button @click="openModal(webhook)" class="p-2 bg-slate-800 hover:bg-slate-700 text-blue-400 rounded-lg transition" title="Editar">
                                                    <i data-lucide="edit-2" class="w-4 h-4"></i>
                                                </button>
                                                <button @click="deleteWebhook(webhook.id)" class="p-2 bg-slate-800 hover:bg-slate-700 text-red-400 rounded-lg transition" title="Excluir">
                                                    <i data-lucide="trash" class="w-4 h-4"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="webhooks.length === 0">
                                    <tr>
                                        <td colspan="4" class="p-12 text-center text-slate-500">
                                            <i data-lucide="webhook" class="w-12 h-12 mx-auto mb-4 opacity-10"></i>
                                            <p>Nenhum webhook cadastrado.</p>
                                            <button @click="openModal()" class="mt-4 text-blue-400 hover:underline text-sm font-medium">Cadastrar primeiro webhook</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Dica -->
                    <div class="mt-6 p-4 bg-blue-600/5 border border-blue-600/10 rounded-xl flex gap-3">
                        <i data-lucide="info" class="w-5 h-5 text-blue-500 shrink-0"></i>
                        <p class="text-sm text-slate-400">
                            Os webhooks permitem que você integre o checkout com outras ferramentas (n8n, Zapier, Make, etc). Toda vez que um evento selecionado ocorrer, enviaremos um POST JSON com todos os dados do pedido.
                        </p>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Adicionar/Editar -->
    <div x-show="isModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="isModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="closeModal()"></div>
        
        <div x-show="isModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" 
             class="bg-slate-900 border border-slate-800 w-full max-w-xl rounded-2xl shadow-2xl relative overflow-hidden flex flex-col max-h-[90vh]">
            
            <header class="p-6 border-b border-slate-800 flex justify-between items-center bg-slate-900 z-10">
                <h3 class="text-xl font-bold text-white flex items-center gap-2">
                    <i data-lucide="webhook" class="text-blue-500"></i>
                    <span x-text="form.id ? 'Editar Webhook' : 'Novo Webhook'"></span>
                </h3>
                <button @click="closeModal()" class="text-slate-400 hover:text-white transition">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </header>

            <div class="flex-1 overflow-y-auto p-6 space-y-6">
                <!-- URL -->
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-2">URL do Endpoint</label>
                    <input x-model="form.url" type="url" placeholder="https://seu-webhook.com/endpoint"
                           class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3 text-white focus:border-blue-500 outline-none transition shadow-inner">
                    <p class="text-[10px] text-slate-500 mt-1">Sua URL deve estar preparada para receber requisições POST com corpo em JSON.</p>
                </div>

                <!-- Eventos -->
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-2">Eventos para Disparo</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <template x-for="evt in availableEvents" :key="evt.id">
                            <label class="flex items-center gap-3 p-3 bg-slate-950 border border-slate-700 rounded-lg cursor-pointer transition hover:bg-slate-900 group">
                                <input type="checkbox" :value="evt.id" x-model="form.events" 
                                       class="w-4 h-4 rounded border-slate-700 text-blue-600 bg-slate-800 focus:ring-offset-slate-900">
                                <div>
                                    <p class="text-sm font-bold text-white" x-text="evt.label"></p>
                                    <p class="text-[10px] text-slate-500" x-text="evt.desc"></p>
                                </div>
                            </label>
                        </template>
                    </div>
                </div>

                <!-- Status -->
                <div class="flex items-center justify-between p-4 bg-slate-950/50 rounded-xl border border-slate-800">
                    <div>
                        <p class="text-sm font-bold text-white">Webhook Ativo</p>
                        <p class="text-[10px] text-slate-500">Desative temporariamente se necessário.</p>
                    </div>
                    <button @click="form.active = !form.active" 
                            class="w-12 h-6 rounded-full bg-slate-700 relative transition-colors duration-300"
                            :class="form.active ? 'bg-green-500' : 'bg-slate-700'">
                        <span class="absolute top-1 left-1 w-4 h-4 bg-white rounded-full transition-transform duration-300 shadow-md"
                              :class="form.active ? 'translate-x-6' : 'translate-x-0'"></span>
                    </button>
                </div>
            </div>

            <footer class="p-6 border-t border-slate-800 bg-slate-900 flex justify-end gap-3 z-10 shrink-0">
                <button @click="closeModal()" class="px-5 py-2 rounded-lg text-slate-300 hover:text-white font-medium transition">Cancelar</button>
                <button @click="saveWebhook()" :disabled="isSaving" 
                        class="px-6 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-bold shadow-lg transition flex items-center gap-2">
                    <span x-show="isSaving" class="animate-spin"><i data-lucide="loader-2" class="w-4 h-4"></i></span>
                    <span x-text="isSaving ? 'Salvando...' : 'Salvar Configuração'"></span>
                </button>
            </footer>
        </div>
    </div>

    <script>
        lucide.createIcons();
        function webhooksApp() {
            return {
                webhooks: [],
                isLoading: true,
                isModalOpen: false,
                isSaving: false,
                availableEvents: [
                    { id: 'order.created', label: 'Pedido Criado', desc: 'Disparado assim que o Pix é gerado.' },
                    { id: 'order.paid', label: 'Pedido Pago', desc: 'Disparado na confirmação do pagamento.' }
                ],
                form: {
                    id: null,
                    url: '',
                    events: [],
                    active: true
                },

                init() {
                    this.fetchWebhooks();
                    this.$nextTick(() => lucide.createIcons());
                },

                fetchWebhooks() {
                    this.isLoading = true;
                    fetch('../api/v1/webhooks.php')
                        .then(res => res.json())
                        .then(data => {
                            this.webhooks = data;
                            this.isLoading = false;
                            this.$nextTick(() => lucide.createIcons());
                        })
                        .catch(err => {
                            console.error(err);
                            this.isLoading = false;
                        });
                },

                openModal(webhook = null) {
                    if (webhook) {
                        this.form = {
                            id: webhook.id,
                            url: webhook.url,
                            events: webhook.events.split(',').map(e => e.trim()),
                            active: webhook.active == 1
                        };
                    } else {
                        this.form = {
                            id: null,
                            url: '',
                            events: ['order.created', 'order.paid'],
                            active: true
                        };
                    }
                    this.isModalOpen = true;
                    this.$nextTick(() => lucide.createIcons());
                },

                closeModal() {
                    this.isModalOpen = false;
                },

                saveWebhook() {
                    if (!this.form.url || this.form.events.length === 0) {
                        alert('Preencha a URL e selecione ao menos um evento.');
                        return;
                    }

                    this.isSaving = true;
                    fetch('../api/v1/webhooks.php', {
                        method: 'POST',
                        body: JSON.stringify(this.form)
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.isSaving = false;
                        if (data.success) {
                            this.fetchWebhooks();
                            this.closeModal();
                        } else {
                            alert('Erro ao salvar: ' + data.message);
                        }
                    })
                    .catch(err => {
                        this.isSaving = false;
                        alert('Erro de conexão.');
                    });
                },

                deleteWebhook(id) {
                    if (!confirm('Tem certeza que deseja excluir este webhook?')) return;

                    fetch('../api/v1/webhooks.php?id=' + id, { method: 'DELETE' })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.fetchWebhooks();
                        }
                    });
                },

                formatDate(date) {
                    return new Date(date).toLocaleString('pt-BR');
                }
            }
        }
    </script>
</body>
</html>
