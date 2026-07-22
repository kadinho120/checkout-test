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
    <title>Rotação de Chaves Pix - Admin</title>
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

    <div class="flex h-screen" x-data="pixRotationsPage()">

        <!-- Sidebar Navigation -->
        <aside class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col">
            <div class="p-6 flex items-center gap-3">
                <div class="w-10 h-8 bg-blue-600 rounded-lg flex items-center justify-center font-bold text-white text-xs">
                    APP
                </div>
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
                    class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i> Pedidos
                </a>
                <a href="pix-rotations.php"
                    class="flex items-center gap-3 px-4 py-3 bg-purple-600/10 text-purple-400 rounded-lg border border-purple-600/20 font-medium">
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
            </nav>

            <div class="p-4 border-t border-slate-800">
                <div class="flex items-center gap-3 px-4 py-2">
                    <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-xs">AD</div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">
                            <?= htmlspecialchars($_SESSION['user'] ?? 'Admin') ?>
                        </p>
                    </div>
                    <a href="login.php?logout=true" class="text-slate-400 hover:text-red-400 transition" title="Sair">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col overflow-hidden bg-slate-950">
            <!-- Header -->
            <header class="h-16 bg-slate-900/50 backdrop-blur border-b border-slate-800 flex items-center justify-between px-6">
                <div class="flex items-center gap-4">
                    <h2 class="text-lg font-semibold text-white">Histórico de Rotação de Chaves Pix (Woovi)</h2>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="triggerRotation()" :disabled="isRotating"
                        class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white rounded-lg font-medium text-sm transition flex items-center gap-2 disabled:opacity-50 shadow-lg shadow-purple-600/20">
                        <i data-lucide="refresh-cw" class="w-4 h-4" :class="{'animate-spin': isRotating}"></i>
                        <span x-text="isRotating ? 'Rotacionando...' : 'Rotacionar Agora'"></span>
                    </button>
                    <button @click="fetchData()"
                        class="p-2 bg-slate-800 hover:bg-slate-700 rounded-lg transition text-slate-400 hover:text-white" title="Atualizar">
                        <i data-lucide="rotate-cw" class="w-4 h-4" :class="{'animate-spin': isLoading}"></i>
                    </button>
                </div>
            </header>

            <!-- Notifications / Toast -->
            <div x-show="toast.show" x-transition 
                :class="toast.type === 'success' ? 'bg-green-500/10 border-green-500/30 text-green-400' : 'bg-red-500/10 border-red-500/30 text-red-400'"
                class="mx-6 mt-4 p-4 rounded-xl border flex items-center justify-between text-sm">
                <div class="flex items-center gap-2">
                    <i :data-lucide="toast.type === 'success' ? 'check-circle' : 'alert-circle'" class="w-5 h-5"></i>
                    <span x-text="toast.message"></span>
                </div>
                <button @click="toast.show = false" class="text-slate-400 hover:text-white">&times;</button>
            </div>

            <!-- Content Area -->
            <div class="flex-1 overflow-auto p-6 space-y-6">

                <!-- Stats Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Current Active Key -->
                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-lg relative overflow-hidden">
                        <div class="flex items-center justify-between text-slate-400 mb-2">
                            <span class="text-xs font-semibold uppercase tracking-wider">Chave Ativa Atual</span>
                            <i data-lucide="key" class="w-4 h-4 text-green-400"></i>
                        </div>
                        <div class="flex items-center gap-2">
                            <code class="text-xs font-mono font-bold text-green-400 bg-green-500/10 px-2 py-1 rounded border border-green-500/20 truncate max-w-[180px]"
                                  x-text="currentActiveKey?.pix_key || 'Nenhuma'"></code>
                            <button x-show="currentActiveKey?.pix_key" @click="copyKey(currentActiveKey.pix_key)" 
                                    class="text-xs text-slate-400 hover:text-white p-1 rounded bg-slate-800" title="Copiar Chave">
                                <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-2">Chave padrão vinculada na Woovi</p>
                    </div>

                    <!-- Total Rotations -->
                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-lg">
                        <div class="flex items-center justify-between text-slate-400 mb-2">
                            <span class="text-xs font-semibold uppercase tracking-wider">Total de Rotações</span>
                            <i data-lucide="history" class="w-4 h-4 text-purple-400"></i>
                        </div>
                        <div class="text-2xl font-bold text-white" x-text="stats.total_rotations || 0"></div>
                        <p class="text-[11px] text-slate-500 mt-1">
                            <span class="text-slate-400 font-medium" x-text="stats.deleted_count || 0"></span> chaves anteriores excluídas
                        </p>
                    </div>

                    <!-- Interval -->
                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-lg">
                        <div class="flex items-center justify-between text-slate-400 mb-2">
                            <span class="text-xs font-semibold uppercase tracking-wider">Intervalo Automático</span>
                            <i data-lucide="clock" class="w-4 h-4 text-blue-400"></i>
                        </div>
                        <div class="text-2xl font-bold text-white">30 min</div>
                        <p class="text-[11px] text-slate-500 mt-1">Configurado via Cron / API</p>
                    </div>

                    <!-- Last Rotation -->
                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-lg">
                        <div class="flex items-center justify-between text-slate-400 mb-2">
                            <span class="text-xs font-semibold uppercase tracking-wider">Última Rotação</span>
                            <i data-lucide="calendar" class="w-4 h-4 text-amber-400"></i>
                        </div>
                        <div class="text-sm font-bold text-white truncate" x-text="formatDate(stats.last_rotation_at)"></div>
                        <p class="text-[11px] text-slate-500 mt-1" x-text="timeSince(stats.last_rotation_at)"></p>
                    </div>
                </div>

                <!-- Table Container -->
                <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-lg">
                    <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                        <h3 class="font-semibold text-white text-sm">Histórico de Chaves Registradas</h3>
                        <span class="text-xs text-slate-400" x-text="`Total: ${totalCount} registros`"></span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-800 bg-slate-900/50 text-xs uppercase text-slate-400 font-bold tracking-wider">
                                    <th class="p-4">Data / Hora de Criação</th>
                                    <th class="p-4">Chave Pix (EVP)</th>
                                    <th class="p-4">Tipo</th>
                                    <th class="p-4">Status Padrão</th>
                                    <th class="p-4">Status Rotação</th>
                                    <th class="p-4 text-right">Data de Exclusão</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                <template x-for="item in items" :key="item.id">
                                    <tr class="hover:bg-slate-800/50 transition duration-150">
                                        <td class="p-4">
                                            <div class="text-white text-sm font-medium" x-text="formatDate(item.created_at)"></div>
                                            <div class="text-slate-500 text-xs" x-text="timeSince(item.created_at)"></div>
                                        </td>
                                        <td class="p-4">
                                            <div class="flex items-center gap-2">
                                                <code class="text-xs font-mono bg-slate-950 px-2 py-1 rounded text-slate-300 border border-slate-800"
                                                      x-text="item.pix_key"></code>
                                                <button @click="copyKey(item.pix_key)" class="text-slate-500 hover:text-slate-300 p-1" title="Copiar">
                                                    <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="p-4">
                                            <span class="px-2 py-0.5 text-xs font-semibold rounded bg-slate-800 text-slate-300 border border-slate-700"
                                                  x-text="item.type || 'EVP'"></span>
                                        </td>
                                        <td class="p-4">
                                            <template x-if="item.is_default == 1">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-500/10 text-green-400 border border-green-500/20">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                                    Padrão Atual
                                                </span>
                                            </template>
                                            <template x-if="item.is_default == 0">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs text-slate-400 bg-slate-800">
                                                    Anterior
                                                </span>
                                            </template>
                                        </td>
                                        <td class="p-4">
                                            <template x-if="item.status === 'ACTIVE'">
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                                    Ativa
                                                </span>
                                            </template>
                                            <template x-if="item.status === 'DELETED'">
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-500/10 text-red-400 border border-red-500/20">
                                                    Excluída (Woovi)
                                                </span>
                                            </template>
                                        </td>
                                        <td class="p-4 text-right text-xs text-slate-400 font-mono">
                                            <span x-text="item.deleted_at ? formatDate(item.deleted_at) : '-'"></span>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="items.length === 0 && !isLoading">
                                    <td colspan="6" class="p-8 text-center text-slate-500">
                                        Nenhuma rotação realizada ainda. Clique em "Rotacionar Agora" para executar a primeira rotação.
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
                            <span class="font-semibold text-white" x-text="totalCount"></span> registros
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="prevPage()" :disabled="page === 1" 
                                class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 disabled:opacity-50 disabled:hover:bg-slate-800 transition text-xs font-bold flex items-center gap-1">
                                <i data-lucide="chevron-left" class="w-3.5 h-3.5"></i> Anterior
                            </button>
                            <div class="flex items-center gap-1">
                                <template x-for="p in totalPages" :key="p">
                                    <button @click="goToPage(p)" 
                                        :class="page === p ? 'bg-purple-600 text-white font-bold' : 'bg-slate-800 hover:bg-slate-700 text-slate-400'"
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

        function pixRotationsPage() {
            return {
                items: [],
                stats: {},
                currentActiveKey: null,
                page: 1,
                totalPages: 1,
                totalCount: 0,
                limit: 10,
                isLoading: false,
                isRotating: false,
                toast: { show: false, message: '', type: 'success' },

                init() {
                    this.fetchData();
                },

                fetchData() {
                    this.isLoading = true;
                    fetch(`../api/v1/pix-rotations.php?page=${this.page}&limit=${this.limit}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                this.items = data.data || [];
                                this.stats = data.stats || {};
                                this.currentActiveKey = data.current_active_key || null;
                                this.totalPages = data.total_pages || 1;
                                this.totalCount = data.total_count || 0;
                            }
                            this.isLoading = false;
                            this.$nextTick(() => lucide.createIcons());
                        })
                        .catch(err => {
                            console.error(err);
                            this.isLoading = false;
                        });
                },

                triggerRotation() {
                    if (this.isRotating) return;
                    this.isRotating = true;

                    fetch('../api/v1/rotate-pix-keys.php?force=1')
                        .then(res => res.json())
                        .then(data => {
                            this.isRotating = false;
                            if (data.success) {
                                this.showToast(data.message || 'Rotação concluída com sucesso!', 'success');
                                this.fetchData();
                            } else {
                                this.showToast(data.error || 'Falha ao rotacionar chaves Pix.', 'error');
                            }
                        })
                        .catch(err => {
                            this.isRotating = false;
                            this.showToast('Erro de conexão ao solicitar rotação.', 'error');
                            console.error(err);
                        });
                },

                showToast(msg, type = 'success') {
                    this.toast = { show: true, message: msg, type: type };
                    setTimeout(() => { this.toast.show = false; }, 5000);
                },

                copyKey(text) {
                    if (!text) return;
                    navigator.clipboard.writeText(text).then(() => {
                        this.showToast('Chave Pix copiada para a área de transferência!', 'success');
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
                    const parts = dateStr.split(' ');
                    if (parts.length === 2) {
                        const [y, m, d] = parts[0].split('-');
                        return `${d}/${m}/${y} ${parts[1]}`;
                    }
                    return dateStr;
                },

                timeSince(dateStr) {
                    if (!dateStr) return '';
                    const date = new Date(dateStr.replace(' ', 'T'));
                    const seconds = Math.floor((new Date() - date) / 1000);
                    if (isNaN(seconds) || seconds < 0) return 'agora';
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
                }
            }
        }
    </script>
</body>

</html>
