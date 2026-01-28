document.addEventListener('DOMContentLoaded', () => {
    const API_REVENUE = 'api.php';
    const API_ADS = 'api-gestao.php';
    
    // Elementos DOM
    const elSpendDate = document.getElementById('spend-date');
    const elProductContainer = document.getElementById('product-selection-container');
    const elSpendForm = document.getElementById('ad-spend-form');
    const elRecentSpends = document.getElementById('recent-spends-list');
    
    // Elementos de Filtro
    const elFilterDate = document.getElementById('report-date-filter');
    const elCustomDateContainer = document.getElementById('custom-date-container');
    const elCustomStart = document.getElementById('custom-start-date');
    const elCustomEnd = document.getElementById('custom-end-date');
    const elCustomApplyBtn = document.getElementById('apply-custom-date');
    
    // KPIs
    const elKpiProfit = document.getElementById('kpi-profit');
    const elKpiRoas = document.getElementById('kpi-roas');
    const elKpiMargin = document.getElementById('kpi-margin');
    const elDetRevenue = document.getElementById('det-revenue');
    const elDetAds = document.getElementById('det-ads');
    const elProfitTable = document.getElementById('profit-table-body');

    // Configurações Iniciais
    elSpendDate.valueAsDate = new Date(); // Data de hoje no input de gasto
    
    // Datas padrão para o filtro personalizado (Início do mês até hoje)
    const today = new Date();
    elCustomEnd.valueAsDate = today;
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    elCustomStart.valueAsDate = firstDay;

    const moneyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

    // Variáveis Globais de Dados
    let ordersData = [];
    let adsData = [];
    let detectedProducts = new Set();

    // --- CARREGAMENTO DE DADOS ---
    async function loadAllData() {
        try {
            const resRev = await fetch(`${API_REVENUE}?t=${Date.now()}`);
            ordersData = await resRev.json();

            const resAds = await fetch(`${API_ADS}?t=${Date.now()}`);
            adsData = await resAds.json();

            processData();
            renderRecentSpends();
        } catch (err) {
            console.error('Erro ao carregar dados:', err);
        }
    }

    function processData() {
        detectedProducts.clear();
        
        ordersData.forEach(order => {
            if (order.productName) detectedProducts.add(order.productName);
        });
        
        adsData.forEach(ad => {
            if (Array.isArray(ad.product)) {
                ad.product.forEach(p => { if (p && p !== 'global') detectedProducts.add(p); });
            } else if (ad.product && ad.product !== 'global') {
                detectedProducts.add(ad.product);
            }
        });

        renderProductCheckboxes();
        calculateMetrics();
    }

    // --- RENDERIZAÇÃO DO FORMULÁRIO DE GASTOS ---
    function renderProductCheckboxes() {
        const currentChecked = Array.from(document.querySelectorAll('input[name="spend_products"]:checked')).map(cb => cb.value);
        elProductContainer.innerHTML = '';
        
        const globalDiv = document.createElement('div');
        globalDiv.className = 'flex items-center gap-2 mb-2 pb-2 border-b border-slate-800';
        globalDiv.innerHTML = `
            <input type="checkbox" name="spend_products" value="global" id="prod_global" class="w-4 h-4 rounded bg-slate-800 border-slate-600 text-blue-600 focus:ring-blue-600">
            <label for="prod_global" class="text-sm text-slate-300 cursor-pointer font-bold">Gasto Global / Institucional</label>
        `;
        elProductContainer.appendChild(globalDiv);

        const sortedProds = Array.from(detectedProducts).sort();
        
        if (sortedProds.length === 0) {
            elProductContainer.innerHTML += '<p class="text-xs text-slate-600 p-2">Nenhum produto detectado ainda.</p>';
        }

        sortedProds.forEach((prod, index) => {
            const div = document.createElement('div');
            div.className = 'flex items-center gap-2 mb-1';
            const id = `prod_${index}`;
            const isChecked = currentChecked.includes(prod) ? 'checked' : '';

            div.innerHTML = `
                <input type="checkbox" name="spend_products" value="${prod}" id="${id}" ${isChecked} class="w-4 h-4 rounded bg-slate-800 border-slate-600 text-blue-600 focus:ring-blue-600">
                <label for="${id}" class="text-sm text-slate-300 cursor-pointer truncate" title="${prod}">${prod}</label>
            `;
            elProductContainer.appendChild(div);
        });
    }

    function getSelectedProducts() {
        const checkboxes = document.querySelectorAll('input[name="spend_products"]:checked');
        return Array.from(checkboxes).map(cb => cb.value);
    }

    // --- CÁLCULO DE MÉTRICAS E CLUSTERS ---
    function calculateMetrics() {
        const filter = elFilterDate.value;
        const { start, end } = getDateRange(filter);

        // 1. Agregação de Receita por Produto Individual
        const revenueByProduct = {}; 
        let totalRevenueAll = 0;

        ordersData.forEach(order => {
            if (order.status !== 'PAID' && order.status !== 'COMPLETED') return;
            
            const orderDate = new Date(order.createdAt.replace(' ', 'T'));
            const orderDay = new Date(orderDate.getFullYear(), orderDate.getMonth(), orderDate.getDate());
            
            if (orderDay >= start && orderDay <= end) {
                let val = parseFloat(order.value);
                if (val > 100) val = val / 100; 
                totalRevenueAll += val;
                const pName = order.productName || 'Outros';
                revenueByProduct[pName] = (revenueByProduct[pName] || 0) + val;
            }
        });

        // 2. Agregação de Custos e Definição de Clusters
        let totalAdsAll = 0;
        const clusters = []; 

        function addToCluster(productList, amount) {
            let foundCluster = null;
            const prods = Array.isArray(productList) ? productList : [productList];
            
            // Transitive closure: se produtos novos tocam cluster existente, mescla
            for (let c of clusters) {
                const hasOverlap = prods.some(p => c.products.has(p));
                if (hasOverlap) {
                    foundCluster = c;
                    break; 
                }
            }

            if (foundCluster) {
                prods.forEach(p => foundCluster.products.add(p));
                foundCluster.cost += amount;
            } else {
                clusters.push({
                    products: new Set(prods),
                    cost: amount,
                    revenue: 0
                });
            }
        }

        adsData.forEach(ad => {
            const parts = ad.date.split('-');
            const adDate = new Date(parts[0], parts[1]-1, parts[2]);
            
            if (adDate >= start && adDate <= end) {
                totalAdsAll += ad.amount;
                addToCluster(ad.product, ad.amount);
            }
        });

        // 3. Consolidação Final
        Object.keys(revenueByProduct).forEach(prod => {
            let inCluster = false;
            for (let c of clusters) {
                if (c.products.has(prod)) {
                    inCluster = true;
                    break;
                }
            }
            if (!inCluster) {
                clusters.push({ products: new Set([prod]), cost: 0, revenue: 0 });
            }
        });

        clusters.forEach(c => {
            c.revenue = 0;
            c.products.forEach(prod => {
                if (revenueByProduct[prod]) c.revenue += revenueByProduct[prod];
            });
        });

        // KPIs Gerais
        const profit = totalRevenueAll - totalAdsAll;
        const roas = totalAdsAll > 0 ? (totalRevenueAll / totalAdsAll) : 0;
        const margin = totalRevenueAll > 0 ? (profit / totalRevenueAll) * 100 : 0;

        elKpiProfit.innerText = moneyFormatter.format(profit);
        elKpiProfit.className = `text-3xl font-bold mt-1 ${profit >= 0 ? 'text-green-400' : 'text-red-400'}`;
        elKpiRoas.innerText = roas.toFixed(2) + 'x';
        elKpiRoas.className = `text-3xl font-bold mt-1 ${roas >= 1.5 ? 'text-green-400' : (roas >= 1 ? 'text-yellow-400' : 'text-red-400')}`;
        elKpiMargin.innerText = margin.toFixed(1) + '%';
        elDetRevenue.innerText = moneyFormatter.format(totalRevenueAll);
        elDetAds.innerText = moneyFormatter.format(totalAdsAll);

        renderProfitTable(clusters);
    }

    function renderProfitTable(clusters) {
        elProfitTable.innerHTML = '';
        
        if (clusters.length === 0) {
            elProfitTable.innerHTML = '<tr><td colspan="5" class="py-4 text-center text-slate-500">Sem dados para o período.</td></tr>';
            return;
        }

        clusters.sort((a, b) => b.revenue - a.revenue);

        clusters.forEach(c => {
            const r = c.revenue;
            const a = c.cost;
            const p = r - a;
            const roas = a > 0 ? (r / a).toFixed(2) : (r > 0 ? '∞' : '0.00');
            
            const prodNames = Array.from(c.products).filter(n => n !== 'global');
            let displayName = prodNames.join(' + ');
            
            if (c.products.has('global')) {
                displayName = displayName ? `Global + ${displayName}` : 'Custos Gerais / Global';
            }
            if (!displayName) displayName = 'Outros';

            const row = `
                <tr class="border-b border-slate-800 hover:bg-slate-900/50 transition">
                    <td class="py-3 font-medium text-white max-w-[200px] truncate" title="${displayName}">${displayName}</td>
                    <td class="py-3 text-right text-green-400">${moneyFormatter.format(r)}</td>
                    <td class="py-3 text-right text-red-400">${moneyFormatter.format(a)}</td>
                    <td class="py-3 text-right font-bold ${p >= 0 ? 'text-green-500' : 'text-red-500'}">${moneyFormatter.format(p)}</td>
                    <td class="py-3 text-right font-mono text-slate-400">${roas}x</td>
                </tr>
            `;
            elProfitTable.insertAdjacentHTML('beforeend', row);
        });
    }

    // --- HELPER DE DATAS ---
    function getDateRange(filter) {
        const now = new Date();
        const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        let start, end;

        if (filter === 'custom') {
            // Leitura dos inputs manuais (YYYY-MM-DD)
            // split é usado para evitar conversão de timezone UTC
            const sParts = elCustomStart.value.split('-');
            const eParts = elCustomEnd.value.split('-');
            
            if (sParts.length === 3) {
                start = new Date(sParts[0], sParts[1]-1, sParts[2]);
            } else {
                start = todayStart; // Fallback
            }

            if (eParts.length === 3) {
                end = new Date(eParts[0], eParts[1]-1, eParts[2]);
            } else {
                end = todayStart; // Fallback
            }
        } else {
            switch (filter) {
                case 'today':
                    start = new Date(todayStart);
                    end = new Date(todayStart);
                    break;
                case 'yesterday':
                    start = new Date(todayStart);
                    start.setDate(start.getDate() - 1);
                    end = new Date(start);
                    break;
                case '7d':
                    end = new Date(todayStart);
                    start = new Date(todayStart);
                    start.setDate(start.getDate() - 7);
                    break;
                case '30d':
                    end = new Date(todayStart);
                    start = new Date(todayStart);
                    start.setDate(start.getDate() - 30);
                    break;
                case 'lifetime':
                    start = new Date(2000, 0, 1);
                    end = new Date(2100, 0, 1);
                    break;
                default:
                    start = todayStart;
                    end = todayStart;
            }
        }
        
        // Ajuste para pegar o final do dia no 'end'
        end.setHours(23, 59, 59, 999);
        return { start, end };
    }

    // --- EVENT LISTENERS DO FILTRO DE DATA ---
    elFilterDate.addEventListener('change', (e) => {
        if (e.target.value === 'custom') {
            elCustomDateContainer.classList.remove('hidden');
            // Não calcula imediatamente, espera o usuário definir as datas e clicar OK
        } else {
            elCustomDateContainer.classList.add('hidden');
            calculateMetrics();
        }
    });

    elCustomApplyBtn.addEventListener('click', () => {
        if (!elCustomStart.value || !elCustomEnd.value) {
            alert('Selecione as datas de início e fim.');
            return;
        }
        // Verifica se a data de fim é menor que a de início
        if (elCustomStart.value > elCustomEnd.value) {
            alert('A data final não pode ser anterior à data inicial.');
            return;
        }
        calculateMetrics();
    });

    // --- RESTANTE DA LÓGICA (LISTAS E SUBMISSÃO) ---
    
    function renderRecentSpends() {
        elRecentSpends.innerHTML = '';
        const recent = [...adsData].sort((a,b) => {
            const dA = a.createdAt ? new Date(a.createdAt) : new Date(a.date);
            const dB = b.createdAt ? new Date(b.createdAt) : new Date(b.date);
            return dB - dA;
        }).slice(0, 10);

        if (recent.length === 0) {
            elRecentSpends.innerHTML = '<p class="text-xs text-slate-600 text-center py-2">Nenhum gasto registrado.</p>';
            return;
        }

        recent.forEach(item => {
            const parts = item.date.split('-');
            const dateDisplay = `${parts[2]}/${parts[1]}`;
            
            let prodName = 'Geral';
            if (Array.isArray(item.product)) {
                prodName = item.product.length > 1 ? 'Combo Múltiplo' : item.product[0];
            } else if (item.product !== 'global') {
                prodName = item.product;
            }

            const div = document.createElement('div');
            div.className = 'flex justify-between items-center bg-slate-800/50 p-2 rounded border border-slate-800 text-xs';
            div.innerHTML = `
                <div>
                    <span class="text-slate-400 font-mono mr-2">${dateDisplay}</span>
                    <span class="text-white font-medium truncate w-24 inline-block align-bottom" title="${Array.isArray(item.product) ? item.product.join(', ') : item.product}">${prodName}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-red-400 font-bold">${moneyFormatter.format(item.amount)}</span>
                    <button onclick="deleteAdSpend('${item.id}')" class="text-slate-600 hover:text-red-500 transition"><i data-lucide="trash-2" class="w-3 h-3"></i></button>
                </div>
            `;
            elRecentSpends.appendChild(div);
        });
        
        if(window.lucide) window.lucide.createIcons();
    }

    window.deleteAdSpend = async (id) => {
        if(!confirm('Tem certeza que deseja excluir este lançamento?')) return;
        try {
            const res = await fetch(`${API_ADS}?action=delete&id=${id}`);
            const json = await res.json();
            if(json.success) {
                loadAllData();
            } else {
                alert('Erro ao excluir.');
            }
        } catch(e) { console.error(e); }
    };

    elSpendForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const selectedProducts = getSelectedProducts();
        if (selectedProducts.length === 0) {
            alert('Selecione pelo menos um produto ou Global.');
            return;
        }

        const payload = {
            date: elSpendDate.value,
            product: selectedProducts, 
            amount: document.getElementById('spend-amount').value
        };

        try {
            const btn = elSpendForm.querySelector('button');
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Salvando...';
            btn.disabled = true;

            const res = await fetch(API_ADS, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            
            const json = await res.json();
            if (json.success) {
                document.getElementById('spend-amount').value = '';
                loadAllData();
            } else {
                alert('Erro: ' + json.message);
            }
            btn.innerHTML = originalText;
            btn.disabled = false;
        } catch (err) {
            console.error(err);
            alert('Erro de conexão.');
        }
    });

    loadAllData();
});
