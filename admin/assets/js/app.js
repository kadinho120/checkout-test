document.addEventListener('DOMContentLoaded', () => {
    const API_URL = 'api.php';
    let allOrders = [];
    
    // Elementos DOM
    const elRevenue = document.getElementById('kpi-revenue');
    const elRevenueCount = document.getElementById('kpi-revenue-count');
    const elPending = document.getElementById('kpi-pending');
    const elPendingCount = document.getElementById('kpi-pending-count');
    const elConversion = document.getElementById('kpi-conversion');
    const elTicket = document.getElementById('kpi-ticket');
    const elProductsTable = document.getElementById('products-table-body');
    const elRecentSales = document.getElementById('recent-sales-container');
    const elLoader = document.getElementById('loading-indicator');
    
    // Filtros de Data
    const filterSelect = document.getElementById('date-filter');
    const elCustomDateContainer = document.getElementById('custom-date-container');
    const elCustomStart = document.getElementById('custom-start-date');
    const elCustomEnd = document.getElementById('custom-end-date');
    const elCustomApplyBtn = document.getElementById('apply-custom-date');

    // Configura datas iniciais para o input customizado (Hoje)
    const today = new Date();
    elCustomEnd.valueAsDate = today;
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    elCustomStart.valueAsDate = firstDay;

    // Formatador de Moeda
    const moneyFormatter = new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    });

    // Função principal de busca
    async function fetchData() {
        try {
            elLoader.classList.remove('hidden');
            const response = await fetch(API_URL);
            if (!response.ok) throw new Error('Erro na API');
            
            const data = await response.json();
            
            // Ordena por data mais recente primeiro
            allOrders = data.sort((a, b) => {
                const dateA = new Date(a.createdAt || 0);
                const dateB = new Date(b.createdAt || 0);
                return dateB - dateA;
            });

            updateDashboard();
        } catch (error) {
            console.error('Erro ao buscar dados:', error);
        } finally {
            elLoader.classList.add('hidden');
        }
    }

    // --- HELPER DE DATAS (Igual ao gestao.js) ---
    function getDateRange(filter) {
        const now = new Date();
        const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        let start, end;

        if (filter === 'custom') {
            const sParts = elCustomStart.value.split('-');
            const eParts = elCustomEnd.value.split('-');
            
            if (sParts.length === 3) {
                start = new Date(sParts[0], sParts[1]-1, sParts[2]);
            } else {
                start = todayStart;
            }

            if (eParts.length === 3) {
                end = new Date(eParts[0], eParts[1]-1, eParts[2]);
            } else {
                end = todayStart;
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
                case '14d':
                    end = new Date(todayStart);
                    start = new Date(todayStart);
                    start.setDate(start.getDate() - 14);
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

    // Filtro de Data
    function getFilteredOrders() {
        const filter = filterSelect.value;
        const { start, end } = getDateRange(filter);
        
        return allOrders.filter(order => {
            if (!order.createdAt) return false;
            const orderDate = new Date(order.createdAt.replace(' ', 'T')); 
            // Zera hora para comparação
            const orderDay = new Date(orderDate.getFullYear(), orderDate.getMonth(), orderDate.getDate());
            // Para intervalo, precisamos comparar com timestamps ou objetos Date
            // Aqui comparamos dia>=start e dia<=end, mas orderDate com hora completa também funciona se start/end estiverem ajustados
            return orderDate >= start && orderDate <= end;
        });
    }

    function updateDashboard() {
        const filtered = getFilteredOrders();

        // 1. KPIs
        let totalRevenue = 0;
        let countPaid = 0;
        let totalPending = 0;
        let countPending = 0;

        filtered.forEach(order => {
            let val = parseFloat(order.value);
            if (val > 100) val = val / 100; 

            if (order.status === 'PAID' || order.status === 'COMPLETED') {
                totalRevenue += val;
                countPaid++;
            } else if (order.status === 'PENDING') {
                totalPending += val;
                countPending++;
            }
        });

        const totalOrders = countPaid + countPending;
        const conversionRate = totalOrders > 0 ? ((countPaid / totalOrders) * 100).toFixed(1) : 0;
        const ticketMedio = countPaid > 0 ? (totalRevenue / countPaid) : 0;

        elRevenue.innerText = moneyFormatter.format(totalRevenue);
        elRevenueCount.innerText = countPaid;
        elPending.innerText = moneyFormatter.format(totalPending);
        elPendingCount.innerText = countPending;
        elConversion.innerText = conversionRate + '%';
        elTicket.innerText = moneyFormatter.format(ticketMedio);

        // 2. Tabela de Produtos
        const productStats = {};
        filtered.forEach(order => {
            if (order.status !== 'PAID' && order.status !== 'COMPLETED') return; 

            const prodName = order.productName || 'Produto Desconhecido';
            if (!productStats[prodName]) {
                productStats[prodName] = { count: 0, revenue: 0 };
            }
            
            let val = parseFloat(order.value);
            if (val > 100) val = val / 100;

            productStats[prodName].count++;
            productStats[prodName].revenue += val;
        });

        // Renderiza Tabela Produtos
        elProductsTable.innerHTML = '';
        const sortedProducts = Object.entries(productStats).sort((a, b) => b[1].revenue - a[1].revenue);
        
        if (sortedProducts.length === 0) {
            elProductsTable.innerHTML = '<tr><td colspan="3" class="p-4 text-center text-slate-500">Nenhuma venda aprovada no período.</td></tr>';
        } else {
            sortedProducts.forEach(([name, stats]) => {
                const row = `
                    <tr class="border-b border-slate-800 hover:bg-slate-900/50 transition">
                        <td class="p-4 font-medium text-white">${name}</td>
                        <td class="p-4 text-right text-slate-300">${stats.count}</td>
                        <td class="p-4 text-right text-emerald-400 font-bold">${moneyFormatter.format(stats.revenue)}</td>
                    </tr>
                `;
                elProductsTable.insertAdjacentHTML('beforeend', row);
            });
        }

        // 3. Lista de Vendas Recentes
        elRecentSales.innerHTML = '';
        const recentOrders = filtered.slice(0, 20);
        
        if (recentOrders.length === 0) {
            elRecentSales.innerHTML = '<div class="p-4 text-center text-slate-500 text-sm">Sem movimentação no período.</div>';
        } else {
            recentOrders.forEach(order => {
                let statusColor = 'bg-yellow-500/10 text-yellow-500 border-yellow-500/20';
                let statusLabel = 'Pendente';
                
                if (order.status === 'PAID' || order.status === 'COMPLETED') {
                    statusColor = 'bg-green-500/10 text-green-500 border-green-500/20';
                    statusLabel = 'Aprovado';
                } else if (order.status === 'EXPIRED' || order.status === 'CANCELLED') {
                    statusColor = 'bg-red-500/10 text-red-500 border-red-500/20';
                    statusLabel = 'Cancelado';
                }

                let val = parseFloat(order.value);
                if (val > 100) val = val / 100;

                let timeStr = '---';
                if (order.createdAt) {
                    const d = new Date(order.createdAt.replace(' ', 'T'));
                    timeStr = d.toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
                }

                const item = `
                    <div class="p-4 border-b border-slate-800 flex items-center justify-between hover:bg-slate-900/50 transition">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-slate-800 flex items-center justify-center text-slate-400 font-bold text-xs">
                                ${order.customerName ? order.customerName.substring(0,2).toUpperCase() : 'CL'}
                            </div>
                            <div>
                                <p class="text-sm font-medium text-white truncate w-32 md:w-auto">${order.customerName || 'Cliente'}</p>
                                <p class="text-xs text-slate-500">${order.productName || 'Produto'}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-white">${moneyFormatter.format(val)}</p>
                            <div class="flex items-center justify-end gap-2 mt-1">
                                <span class="text-xs text-slate-500">${timeStr}</span>
                                <span class="text-[10px] px-2 py-0.5 rounded border ${statusColor} font-medium uppercase">${statusLabel}</span>
                            </div>
                        </div>
                    </div>
                `;
                elRecentSales.insertAdjacentHTML('beforeend', item);
            });
        }
    }

    // --- EVENT LISTENERS ---
    filterSelect.addEventListener('change', (e) => {
        if (e.target.value === 'custom') {
            elCustomDateContainer.classList.remove('hidden');
        } else {
            elCustomDateContainer.classList.add('hidden');
            updateDashboard();
        }
    });

    elCustomApplyBtn.addEventListener('click', () => {
        if (!elCustomStart.value || !elCustomEnd.value) {
            alert('Selecione as datas de início e fim.');
            return;
        }
        if (elCustomStart.value > elCustomEnd.value) {
            alert('A data final não pode ser anterior à data inicial.');
            return;
        }
        updateDashboard();
    });

    // Inicialização
    fetchData(); // Primeira carga
    setInterval(fetchData, 10000); // Polling a cada 10s
});