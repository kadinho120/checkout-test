document.addEventListener('DOMContentLoaded', () => {
    const API_URL = 'api.php';
    let allOrders = [];
    
    // Elementos
    const elTableBody = document.getElementById('orders-table-body');
    const elSearch = document.getElementById('search-input');
    const elStatusFilter = document.getElementById('status-filter');
    const elRefreshBtn = document.getElementById('refresh-btn');
    const elTotalRecords = document.getElementById('total-records');
    const elLoader = document.getElementById('loading-indicator');

    // Elementos de Filtro de Data
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

    // Formatador BRL
    const moneyFormatter = new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    });

    // Busca dados
    async function fetchData() {
        try {
            if(elLoader) elLoader.classList.remove('hidden');
            
            const response = await fetch(`${API_URL}?t=${new Date().getTime()}`);
            if (!response.ok) throw new Error('Erro na API');
            
            const data = await response.json();
            
            // Ordena por data (mais recente primeiro)
            allOrders = data.sort((a, b) => {
                const dateA = new Date(a.createdAt || 0);
                const dateB = new Date(b.createdAt || 0);
                return dateB - dateA;
            });

            renderTable();
        } catch (error) {
            console.error('Erro:', error);
            elTableBody.innerHTML = `<tr><td colspan="7" class="p-6 text-center text-red-400">Erro ao carregar dados.</td></tr>`;
        } finally {
            if(elLoader) elLoader.classList.add('hidden');
        }
    }

    // --- HELPER DE DATAS ---
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

    // Renderiza Tabela com Filtros (Busca, Status e Data)
    function renderTable() {
        const searchTerm = elSearch.value.toLowerCase();
        const statusTerm = elStatusFilter.value;
        
        // Filtro de Data
        const dateFilter = filterSelect.value;
        const { start, end } = getDateRange(dateFilter);

        const filtered = allOrders.filter(order => {
            // 1. Filtro de Data
            if (!order.createdAt) return false;
            const orderDate = new Date(order.createdAt.replace(' ', 'T')); 
            
            // Se for lifetime, nem checa datas específicas (apenas se existe)
            if (dateFilter !== 'lifetime') {
                if (orderDate < start || orderDate > end) return false;
            }

            // 2. Filtro de Status
            let matchStatus = true;
            if (statusTerm === 'paid') matchStatus = (order.status === 'PAID' || order.status === 'COMPLETED');
            else if (statusTerm === 'pending') matchStatus = (order.status === 'PENDING');
            else if (statusTerm === 'cancelled') matchStatus = (order.status === 'CANCELLED' || order.status === 'EXPIRED');

            // 3. Filtro de Busca (Nome, Email, ID, Produto)
            const searchString = `
                ${order.correlationId || ''} 
                ${order.customerName || ''} 
                ${order.email || ''} 
                ${order.whatsapp || ''}
                ${order.productName || ''}
            `.toLowerCase();
            
            const matchSearch = searchString.includes(searchTerm);

            return matchStatus && matchSearch;
        });

        elTableBody.innerHTML = '';
        elTotalRecords.innerText = `${filtered.length} registros encontrados`;

        if (filtered.length === 0) {
            elTableBody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-slate-500">Nenhum pedido encontrado com esses filtros.</td></tr>`;
            return;
        }

        filtered.forEach(order => {
            // Tratamento de Valores
            let val = parseFloat(order.value);
            if (val > 10000) { // Assuming it's in cents if it's very high, but let's be careful.
                 // Actually, admin/api.php now sends (total_amount * 100).
                 // So we should always divide by 100 for display.
            }
            const displayPrice = moneyFormatter.format(val / 100);

            // Tratamento de Status
            let statusBadge = '';
            let statusText = order.status || 'Desconhecido';
            
            if (statusText === 'PAID' || statusText === 'COMPLETED') {
                statusBadge = '<span class="px-2 py-1 rounded text-[10px] font-bold uppercase bg-green-500/10 text-green-400 border border-green-500/20">Aprovado</span>';
            } else if (statusText === 'PENDING') {
                statusBadge = '<span class="px-2 py-1 rounded text-[10px] font-bold uppercase bg-yellow-500/10 text-yellow-400 border border-yellow-500/20">Pendente</span>';
            } else {
                statusBadge = `<span class="px-2 py-1 rounded text-[10px] font-bold uppercase bg-red-500/10 text-red-400 border border-red-500/20">${statusText}</span>`;
            }

            // Tratamento de Data
            let dateDisplay = '---';
            let idDisplay = order.correlationId ? order.correlationId.substring(0, 12) + '...' : '---';
            
            if (order.createdAt) {
                const d = new Date(order.createdAt.replace(' ', 'T'));
                const dateStr = d.toLocaleDateString('pt-BR');
                const timeStr = d.toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
                dateDisplay = `<div class="flex flex-col"><span class="text-white font-medium">${dateStr}</span><span class="text-xs text-slate-500">${timeStr}</span></div>`;
            }

            // Ações (Pix + Deletar + Detalhes)
            let actionButtons = '';
            
            if (order.status === 'PENDING' && order.pixBrCode) {
                 // Note: JS doesn't have pixQrCode directly but admin/api.php could send it.
                 // For now, let's just keep the original logic if it existed.
            }

            const hasAddress = order.cep && order.address;
            const detailBtn = `<button onclick="toggleOrderDetails(${order.id})" class="text-slate-400 hover:text-white transition mr-2" title="Ver Detalhes"><i data-lucide="${hasAddress ? 'truck' : 'info'}" class="w-4 h-4"></i></button>`;
            const deleteBtn = `<button onclick="deleteOrder(${order.id})" class="text-slate-500 hover:text-red-400 transition" title="Excluir"><i data-lucide="trash-2" class="w-4 h-4"></i></button>`;

            const row = document.createElement('tr');
            row.className = 'hover:bg-slate-800/50 transition-colors group cursor-pointer';
            row.id = `order-row-${order.id}`;
            row.addEventListener('click', (e) => {
                if (e.target.closest('button')) return;
                console.log('Toggling details for order:', order.id);
                toggleOrderDetails(order.id);
            });
            row.innerHTML = `
                <td class="p-4">${dateDisplay}<div class="text-[10px] text-slate-600 mt-1 font-mono">${order.correlationId || '---'}</div></td>
                <td class="p-4 font-medium text-white">${order.customerName || '---'}<div class="text-xs text-slate-500 font-normal">${order.email || '---'}</div></td>
                <td class="p-4 text-slate-400 font-mono text-xs">${order.whatsapp || '---'}</td>
                <td class="p-4 text-slate-400 text-xs">${order.productName || 'Infoproduto'}</td>
                <td class="p-4 text-right font-bold text-white">${displayPrice}</td>
                <td class="p-4 text-center">${statusBadge}</td>
                <td class="p-4 text-center">
                    <div class="flex items-center justify-center">
                        ${detailBtn}
                        ${deleteBtn}
                    </div>
                </td>
            `;
            elTableBody.appendChild(row);

            // Row de Detalhes (Oculta)
            const detailRow = document.createElement('tr');
            detailRow.id = `detail-row-${order.id}`;
            detailRow.className = 'hidden bg-slate-900/50';
            
            let addressHtml = '';
            if (hasAddress) {
                addressHtml = `
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-6 border-t border-slate-800/50">
                        <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-black text-blue-500 uppercase tracking-widest">Endereço de Entrega</span>
                            <span class="text-white text-sm">${order.address}, ${order.address_number}</span>
                            <span class="text-slate-400 text-xs">${order.neighborhood}</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-black text-blue-500 uppercase tracking-widest">Localidade</span>
                            <span class="text-white text-sm">${order.city} / ${order.state}</span>
                            <span class="text-slate-400 text-xs">CEP: ${order.cep}</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-black text-blue-500 uppercase tracking-widest">Complemento</span>
                            <span class="text-white text-sm">${order.complement || '---'}</span>
                        </div>
                         <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-black text-blue-500 uppercase tracking-widest">ID Externo</span>
                            <span class="text-white text-sm font-mono">${order.external_id || '---'}</span>
                        </div>
                    </div>
                `;
            } else {
                addressHtml = `
                    <div class="p-6 border-t border-slate-800/50 text-slate-500 text-xs">
                        Produto Digital - Sem endereço de entrega.
                    </div>
                `;
            }

            detailRow.innerHTML = `<td colspan="7">${addressHtml}</td>`;
            elTableBody.appendChild(detailRow);
        });

        lucide.createIcons();
    }

    // --- FUNÇÕES GLOBAIS (Expostas para o onclick) ---
    window.toggleOrderDetails = (id) => {
        const row = document.getElementById(`detail-row-${id}`);
        if (row) {
            row.classList.toggle('hidden');
        }
    };

    window.deleteOrder = async (id) => {
        if (!confirm('Tem certeza que deseja excluir este pedido?')) return;
        
        try {
            const res = await fetch(`${API_URL}?id=${id}`, { method: 'DELETE' });
            if (res.ok) {
                fetchData();
            } else {
                alert('Erro ao deletar pedido');
            }
        } catch (e) {
            console.error(e);
            alert('Erro de conexão');
        }
    };

    // Eventos
    if(elRefreshBtn) elRefreshBtn.addEventListener('click', fetchData);
    if(elSearch) elSearch.addEventListener('input', renderTable);
    if(elStatusFilter) elStatusFilter.addEventListener('change', renderTable);
    if(filterSelect) filterSelect.addEventListener('change', () => {
        if (filterSelect.value === 'custom') {
            elCustomDateContainer.classList.remove('hidden');
        } else {
            elCustomDateContainer.classList.add('hidden');
            renderTable();
        }
    });
    if(elCustomApplyBtn) elCustomApplyBtn.addEventListener('click', renderTable);

    // Initial load
    fetchData();
});
