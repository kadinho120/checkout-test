console.log('Script pedidos.js carregado v2.0');

document.addEventListener('DOMContentLoaded', () => {
    const API_URL = 'api.php';
    let allOrders = [];
    
    // Helper para pegar elemento com segurança
    const getEl = (id) => document.getElementById(id);

    const elTableBody = getEl('orders-table-body');
    const elSearch = getEl('search-input');
    const elStatusFilter = getEl('status-filter');
    const elRefreshBtn = getEl('refresh-btn');
    const elTotalRecords = getEl('total-records');
    const elLoader = getEl('loading-indicator');
    const filterSelect = getEl('date-filter');

    const moneyFormatter = new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    });

    async function fetchData() {
        console.log('Iniciando busca de dados...');
        try {
            if(elLoader) elLoader.classList.remove('hidden');
            const response = await fetch(`${API_URL}?t=${new Date().getTime()}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            console.log('Dados recebidos:', data.length, 'pedidos');
            
            allOrders = data.sort((a, b) => new Date(b.createdAt || 0) - new Date(a.createdAt || 0));
            renderTable();
        } catch (error) {
            console.error('Erro ao buscar dados:', error);
            if(elTableBody) elTableBody.innerHTML = `<tr><td colspan="7" class="p-6 text-center text-red-400">Erro na API: ${error.message}</td></tr>`;
        } finally {
            if(elLoader) elLoader.classList.add('hidden');
        }
    }

    function renderTable() {
        console.log('Renderizando tabela...');
        if(!elTableBody) {
            console.error('Elemento orders-table-body não encontrado!');
            return;
        }

        const searchTerm = (elSearch ? elSearch.value : '').toLowerCase();
        const statusTerm = elStatusFilter ? elStatusFilter.value : 'all';

        const filtered = allOrders.filter(order => {
            if (statusTerm !== 'all') {
                const s = (order.status || '').toLowerCase();
                if (statusTerm === 'paid' && s !== 'paid' && s !== 'completed') return false;
                if (statusTerm === 'pending' && s !== 'pending') return false;
                if (statusTerm === 'cancelled' && s !== 'cancelled' && s !== 'expired') return false;
            }

            const searchString = `${order.correlationId} ${order.customerName} ${order.email} ${order.whatsapp} ${order.productName}`.toLowerCase();
            return searchString.includes(searchTerm);
        });

        elTableBody.innerHTML = '';
        if(elTotalRecords) elTotalRecords.innerText = `${filtered.length} registros encontrados`;

        if (filtered.length === 0) {
            elTableBody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-slate-500">Nenhum pedido encontrado.</td></tr>`;
            return;
        }

        filtered.forEach(order => {
            try {
                const val = parseFloat(order.value || 0) / 100;
                const displayPrice = moneyFormatter.format(val);

                let statusBadge = '<span class="px-2 py-1 rounded text-[10px] font-bold uppercase bg-slate-500/10 text-slate-400 border border-slate-500/20">Desconhecido</span>';
                const s = (order.status || '').toUpperCase();
                if (s === 'PAID' || s === 'COMPLETED') statusBadge = '<span class="px-2 py-1 rounded text-[10px] font-bold uppercase bg-green-500/10 text-green-400 border border-green-500/20">Aprovado</span>';
                else if (s === 'PENDING') statusBadge = '<span class="px-2 py-1 rounded text-[10px] font-bold uppercase bg-yellow-500/10 text-yellow-400 border border-yellow-500/20">Pendente</span>';
                else if (s) statusBadge = `<span class="px-2 py-1 rounded text-[10px] font-bold uppercase bg-red-500/10 text-red-400 border border-red-500/20">${s}</span>`;

                const d = new Date((order.createdAt || '').replace(' ', 'T') || 0);
                const dateDisplay = `<div class="flex flex-col"><span class="text-white font-medium">${d.toLocaleDateString('pt-BR')}</span><span class="text-xs text-slate-500">${d.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'})}</span></div>`;

                const hasAddress = !!(order.cep && order.address);
                
                const row = document.createElement('tr');
                row.className = 'hover:bg-slate-800/40 transition-colors border-b border-slate-800/50';
                
                row.innerHTML = `
                    <td class="p-4">${dateDisplay}<div class="text-[10px] text-slate-600 mt-1 font-mono">${order.correlationId || '---'}</div></td>
                    <td class="p-4 text-white font-medium">${order.customerName || '---'}</td>
                    <td class="p-4 text-slate-400 font-mono text-xs">${order.whatsapp || '---'}</td>
                    <td class="p-4 text-slate-400 text-xs">${order.productName || 'Produto'}</td>
                    <td class="p-4 text-right font-bold text-white">${displayPrice}</td>
                    <td class="p-4 text-center">${statusBadge}</td>
                    <td class="p-4 text-center">
                        <div class="flex items-center justify-center gap-3">
                            <button onclick="toggleOrderDetails(${order.id})" class="bg-blue-600 hover:bg-blue-500 text-white text-[10px] font-bold px-3 py-1.5 rounded transition uppercase tracking-wider shadow-lg">
                                Detalhes
                            </button>
                            <button onclick="deleteOrder(${order.id})" class="text-slate-500 hover:text-red-400 transition" title="Excluir">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </td>
                `;
                elTableBody.appendChild(row);

                // Row de Detalhes
                const detailRow = document.createElement('tr');
                detailRow.id = `detail-row-${order.id}`;
                detailRow.className = 'hidden bg-slate-900/90 border-b border-slate-800';
                detailRow.innerHTML = `
                    <td colspan="7" class="p-0">
                        <div class="p-6 bg-slate-900/50">
                            ${hasAddress ? `
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div class="space-y-1">
                                        <p class="text-[10px] font-black text-blue-500 uppercase tracking-widest">Endereço de Entrega</p>
                                        <p class="text-white text-sm">${order.address}, ${order.address_number}</p>
                                        <p class="text-slate-400 text-xs">${order.neighborhood} - CEP: ${order.cep}</p>
                                        <p class="text-slate-400 text-xs">${order.city} / ${order.state}</p>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-[10px] font-black text-blue-500 uppercase tracking-widest">Complemento</p>
                                        <p class="text-white text-sm">${order.complement || '---'}</p>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-[10px] font-black text-blue-500 uppercase tracking-widest">Contato Cliente</p>
                                        <p class="text-white text-sm">${order.customerName}</p>
                                        <p class="text-slate-400 text-xs">${order.email}</p>
                                        <p class="text-slate-400 text-xs">${order.whatsapp}</p>
                                    </div>
                                </div>
                            ` : `
                                <div class="flex items-center gap-2 text-slate-500 text-sm italic">
                                    <i data-lucide="info" class="w-4 h-4"></i>
                                    Produto Digital: Nenhuma informação de entrega necessária.
                                </div>
                            `}
                        </div>
                    </td>
                `;
                elTableBody.appendChild(detailRow);
            } catch (err) {
                console.error("Erro ao renderizar linha:", err, order);
            }
        });
        
        if(typeof lucide !== 'undefined') lucide.createIcons();
    }

    window.toggleOrderDetails = (id) => {
        console.log('Alternando detalhes para:', id);
        const row = document.getElementById(`detail-row-${id}`);
        if (row) {
            row.classList.toggle('hidden');
        } else {
            console.error('Row de detalhes não encontrada para ID:', id);
        }
    };

    window.deleteOrder = async (id) => {
        if (!confirm('Deseja realmente excluir este pedido?')) return;
        try {
            const res = await fetch(`${API_URL}?id=${id}`, { method: 'DELETE' });
            if (res.ok) fetchData();
            else alert('Erro ao excluir pedido.');
        } catch (e) {
            console.error('Erro na exclusão:', e);
            alert('Erro de conexão ao excluir.');
        }
    };

    if(elRefreshBtn) elRefreshBtn.onclick = fetchData;
    if(elSearch) elSearch.oninput = renderTable;
    if(elStatusFilter) elStatusFilter.onchange = renderTable;
    
    if(filterSelect) {
        filterSelect.onchange = () => {
            const container = getEl('custom-date-container');
            if (filterSelect.value === 'custom') {
                if(container) container.classList.remove('hidden');
            } else {
                if(container) container.classList.add('hidden');
                renderTable();
            }
        };
    }

    const applyBtn = getEl('apply-custom-date');
    if(applyBtn) applyBtn.onclick = renderTable;

    fetchData();
});
