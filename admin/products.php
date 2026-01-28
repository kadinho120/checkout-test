<?php
require_once 'auth.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Produtos - Admin</title>
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

<body class="bg-slate-950 text-slate-200 font-sans antialiased" x-data="productsApp()">

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
                    class="flex items-center gap-3 px-4 py-3 bg-blue-600/10 text-blue-400 rounded-lg border border-blue-600/20 font-medium">
                    <i data-lucide="package" class="w-5 h-5"></i> Produtos
                </a>
                <a href="orders.php"
                    class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i> Pedidos
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
                    <h2 class="text-lg font-semibold text-white">Gerenciar Produtos</h2>
                </div>
                <button @click="openModal()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2">
                    <i data-lucide="plus" class="w-4 h-4"></i> Novo Produto
                </button>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-950 p-6">

                <!-- Loading State -->
                <div x-show="isLoading" class="flex justify-center py-10">
                    <i data-lucide="loader" class="w-8 h-8 animate-spin text-blue-500"></i>
                </div>

                <!-- Products Table -->
                <div x-show="!isLoading"
                    class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-lg">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-xs text-slate-400 border-b border-slate-800 bg-slate-900/50">
                                <th class="p-4 uppercase font-medium">Produto</th>
                                <th class="p-4 uppercase font-medium">Slug</th>
                                <th class="p-4 uppercase font-medium">Preço</th>
                                <th class="p-4 uppercase font-medium text-center">Status</th>
                                <th class="p-4 uppercase font-medium text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <template x-for="product in products" :key="product.id">
                                <tr class="hover:bg-slate-800/50 transition">
                                    <td class="p-4 font-medium text-white">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded bg-slate-700 overflow-hidden">
                                                <img :src="product.image_url" class="w-full h-full object-cover"
                                                    onerror="this.src='https://placehold.co/100x100?text=IMG'">
                                            </div>
                                            <span x-text="product.name"></span>
                                        </div>
                                    </td>
                                    <td class="p-4 text-slate-400 text-sm" x-text="product.slug"></td>
                                    <td class="p-4 text-white font-mono text-sm"
                                        x-text="'R$ ' + parseFloat(product.price).toFixed(2)"></td>
                                    <td class="p-4 text-center">
                                        <span
                                            :class="product.active == 1 ? 'bg-green-500/10 text-green-400 border-green-500/20' : 'bg-red-500/10 text-red-400 border-red-500/20'"
                                            class="px-2 py-1 rounded text-xs font-bold border">
                                            <span x-text="product.active == 1 ? 'ATIVO' : 'INATIVO'"></span>
                                        </span>
                                    </td>
                                    <td class="p-4 text-right flex justify-end gap-2">
                                        <a :href="'../checkout.php?slug=' + product.slug" target="_blank"
                                            class="p-2 text-slate-400 hover:text-white bg-slate-800 rounded hover:bg-slate-700"
                                            title="Ver Checkout">
                                            <i data-lucide="external-link" class="w-4 h-4"></i>
                                        </a>
                                        <button @click="openModal(product)"
                                            class="p-2 text-blue-400 hover:text-blue-300 bg-slate-800 rounded hover:bg-slate-700"
                                            title="Editar">
                                            <i data-lucide="edit" class="w-4 h-4"></i>
                                        </button>
                                        <button @click="deleteProduct(product.id)"
                                            class="p-2 text-red-400 hover:text-red-300 bg-slate-800 rounded hover:bg-slate-700"
                                            title="Excluir">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="products.length === 0">
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-slate-500">
                                        Nenhum produto encontrado. Crie o primeiro!
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

            </main>
        </div>

        <!-- Create/Edit Modal -->
        <div x-show="isModalOpen" x-transition.opacity x-cloak
            class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div @click.outside="closeModal()"
                class="bg-slate-900 border border-slate-700 w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-xl shadow-2xl flex flex-col">
                <div
                    class="p-6 border-b border-slate-800 flex justify-between items-center sticky top-0 bg-slate-900 z-10">
                    <h3 class="text-xl font-bold text-white" x-text="form.id ? 'Editar Produto' : 'Novo Produto'"></h3>
                    <button @click="closeModal()" class="text-slate-400 hover:text-white"><i
                            data-lucide="x"></i></button>
                </div>

                <div class="p-6 space-y-6">
                    <!-- Basic Info -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-400 mb-1">Nome do Produto</label>
                                <input x-model="form.name" type="text"
                                    class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-400 mb-1">Slug (URL)</label>
                                <input x-model="form.slug" type="text"
                                    class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-400 mb-1">Preço (R$)</label>
                                <input x-model="form.price" type="number" step="0.01"
                                    class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-400 mb-1">URL da Imagem</label>
                                <input x-model="form.image_url" type="text"
                                    class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-400 mb-1">Descrição Curta</label>
                                <textarea x-model="form.description"
                                    class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2.5 text-white h-[86px] resize-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"></textarea>
                            </div>
                            <div class="flex items-center gap-2 pt-2">
                                <input x-model="form.active" type="checkbox" id="active"
                                    class="w-5 h-5 rounded border-slate-600 bg-slate-700 text-blue-600 focus:ring-blue-500">
                                <label for="active" class="text-white font-medium">Produto Ativo no Checkout</label>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-400 mb-1">Tema do Checkout</label>
                                <select x-model="form.theme"
                                    class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                                    <option value="dark">Escuro (Padrão)</option>
                                    <option value="light">Claro (Light Mode)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <hr class="border-slate-800">

                    <!-- Order Bumps -->
                    <div>
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="text-lg font-bold text-white flex items-center gap-2"><i data-lucide="zap"
                                    class="text-yellow-500 w-5 h-5"></i> Order Bumps</h4>
                            <button @click="addBump()"
                                class="text-xs bg-slate-800 hover:bg-slate-700 text-white px-3 py-1 rounded border border-slate-700 flex items-center gap-1">
                                <i data-lucide="plus" class="w-3 h-3"></i> Adicionar
                            </button>
                        </div>

                        <div class="space-y-4">
                            <template x-for="(bump, index) in form.bumps" :key="index">
                                <div class="bg-slate-950 p-4 rounded-lg border border-slate-800 relative group">
                                    <button @click="removeBump(index)"
                                        class="absolute top-2 right-2 text-red-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition"><i
                                            data-lucide="trash" class="w-4 h-4"></i></button>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                        <div class="md:col-span-2">
                                            <input x-model="bump.title" type="text" placeholder="Título da Oferta"
                                                class="w-full bg-transparent border-b border-slate-700 text-sm text-white focus:border-yellow-500 outline-none mb-2 pb-1">
                                            <input x-model="bump.description" type="text"
                                                placeholder="Descrição curta (ex: Receita secreta...)"
                                                class="w-full bg-transparent border-b border-slate-700 text-xs text-slate-400 focus:border-yellow-500 outline-none pb-1">
                                        </div>
                                        <div>
                                            <input x-model="bump.price" type="number" step="0.01" placeholder="Preço"
                                                class="w-full bg-transparent border-b border-slate-700 text-sm text-white focus:border-yellow-500 outline-none mb-2 pb-1">
                                            <input x-model="bump.image_url" type="text"
                                                placeholder="URL Imagem (Opcional)"
                                                class="w-full bg-transparent border-b border-slate-700 text-xs text-slate-400 focus:border-yellow-500 outline-none pb-1">
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template x-if="form.bumps.length === 0">
                                <p class="text-slate-500 text-sm italic">Nenhum order bump configurado.</p>
                            </template>
                        </div>
                    </div>

                    <hr class="border-slate-800">

                    <!-- Pixels -->
                    <div>
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="text-lg font-bold text-white flex items-center gap-2"><i data-lucide="target"
                                    class="text-blue-500 w-5 h-5"></i> Pixels de Rastreamento</h4>
                            <button @click="addPixel()"
                                class="text-xs bg-slate-800 hover:bg-slate-700 text-white px-3 py-1 rounded border border-slate-700 flex items-center gap-1">
                                <i data-lucide="plus" class="w-3 h-3"></i> Adicionar
                            </button>
                        </div>

                        <div class="space-y-3">
                            <template x-for="(pixel, index) in form.pixels" :key="index">
                                <div
                                    class="flex items-center gap-3 bg-slate-950 p-3 rounded-lg border border-slate-800 relative group">
                                    <button @click="removePixel(index)"
                                        class="absolute top-3 right-3 text-red-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition"><i
                                            data-lucide="trash" class="w-4 h-4"></i></button>

                                    <select x-model="pixel.type"
                                        class="bg-slate-900 border border-slate-700 text-white text-xs rounded p-2 outline-none">
                                        <option value="facebook">Facebook Ads</option>
                                        <option value="google">Google Ads</option>
                                        <option value="tiktok">TikTok</option>
                                        <option value="custom">Custom Script</option>
                                    </select>

                                    <input x-model="pixel.pixel_id" type="text" placeholder="ID do Pixel / Tag"
                                        class="flex-1 bg-transparent border-b border-slate-700 text-sm text-white focus:border-blue-500 outline-none pb-1">

                                    <input x-show="pixel.type === 'facebook'" x-model="pixel.token" type="text"
                                        placeholder="Token API (Opcional)"
                                        class="flex-1 bg-transparent border-b border-slate-700 text-sm text-white focus:border-blue-500 outline-none pb-1">
                                </div>
                            </template>
                            <template x-if="form.pixels.length === 0">
                                <p class="text-slate-500 text-sm italic">Nenhum pixel configurado.</p>
                            </template>
                        </div>
                    </div>

                </div>

                <div class="p-6 border-t border-slate-800 bg-slate-900 flex justify-end gap-3 sticky bottom-0">
                    <button @click="closeModal()"
                        class="px-5 py-2 rounded-lg text-slate-300 hover:text-white font-medium transition">Cancelar</button>
                    <button @click="saveProduct()" :disabled="isSaving"
                        class="px-5 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white font-bold shadow-lg transition flex items-center gap-2">
                        <span x-show="isSaving" class="animate-spin"><i data-lucide="loader-2"
                                class="w-4 h-4"></i></span>
                        <span x-text="isSaving ? 'Salvando...' : 'Salvar Produto'"></span>
                    </button>
                </div>
            </div>
        </div>

    </div>

    <!-- App Logic -->
    <script>
        function productsApp() {
            return {
                products: [],
                isLoading: true,
                isModalOpen: false,
                isSaving: false,
                form: {
                    id: null,
                    name: '',
                    slug: '',
                    description: '',
                    price: '',
                    image_url: '',
                    image_url: '',
                    active: true,
                    theme: 'dark',
                    bumps: [],
                    pixels: []
                },

                init() {
                    this.fetchProducts();
                },

                fetchProducts() {
                    this.isLoading = true;
                    fetch('../api/v1/products.php')
                        .then(res => res.json())
                        .then(data => {
                            this.products = data;
                            this.isLoading = false;
                            this.$nextTick(() => lucide.createIcons());
                        })
                        .catch(err => {
                            console.error(err);
                            this.isLoading = false;
                        });
                },

                openModal(product = null) {
                    if (product) {
                        // Load full details including bumps/pixels by fetching single
                        fetch(`../api/v1/products.php?id=${product.id}`)
                            .then(res => res.json())
                            .then(data => {
                                this.form = {
                                    id: data.id,
                                    name: data.name,
                                    slug: data.slug,
                                    description: data.description,
                                    price: data.price,
                                    image_url: data.image_url,
                                    image_url: data.image_url,
                                    active: data.active == 1,
                                    theme: data.theme || 'dark',
                                    bumps: data.bumps || [],
                                    pixels: data.pixels || []
                                };
                                this.isModalOpen = true;
                                this.$nextTick(() => lucide.createIcons());
                            });
                    } else {
                        // Reset Form
                        this.form = {
                            id: null,
                            name: '',
                            slug: '',
                            description: '',
                            price: '',
                            image_url: '',
                            image_url: '',
                            active: true,
                            theme: 'dark',
                            bumps: [],
                            pixels: []
                        };
                        this.isModalOpen = true;
                    }
                },

                closeModal() {
                    this.isModalOpen = false;
                },

                addBump() {
                    this.form.bumps.push({ title: '', description: '', price: '', image_url: '', active: 1 });
                    this.$nextTick(() => lucide.createIcons());
                },
                removeBump(index) {
                    this.form.bumps.splice(index, 1);
                },

                addPixel() {
                    this.form.pixels.push({ type: 'facebook', pixel_id: '', token: '', active: 1 });
                    this.$nextTick(() => lucide.createIcons());
                },
                removePixel(index) {
                    this.form.pixels.splice(index, 1);
                },

                saveProduct() {
                    this.isSaving = true;
                    fetch('../api/v1/products.php', {
                        method: 'POST',
                        body: JSON.stringify(this.form)
                    })
                        .then(res => res.json())
                        .then(data => {
                            this.isSaving = false;
                            // Reload list
                            this.fetchProducts();
                            this.closeModal();
                        })
                        .catch(err => {
                            alert('Erro ao salvar');
                            this.isSaving = false;
                        });
                },

                deleteProduct(id) {
                    if (!confirm('Tem certeza? Isso não pode ser desfeito.')) return;

                    fetch(`../api/v1/products.php?id=${id}`, {
                        method: 'DELETE'
                    }).then(() => {
                        this.fetchProducts();
                    });
                }
            }
        }

    </script>
</body>

</html>