document.addEventListener('DOMContentLoaded', () => {
    const API_URL = 'api.php';
    let allOrders = [];
    
    const elTableBody = document.getElementById('orders-table-body');
    const elSearch = document.getElementById('search-input');
    const elStatusFilter = document.getElementById('status-filter');
    const elRefreshBtn = document.getElementById('refresh-btn');
    const elTotalRecords = document.getElementById('total-records');
    const elLoader = document.getElementById('loading-indicator');
    const filterSelect = document.getElementById('date-filter');
    const elCustomDateContainer = document.getElementById('custom-date-container');
    const elCustomStart = document.getElementById('custom-start-date');
    const elCustomEnd = document.getElementById('custom-end-date');
    const elCustomApplyBtn = document.getElementById('apply-custom-date');

    const moneyFormatter = new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    });

    async function fetchData() {
        try {
            if(elLoader) elLoader.classList.remove('hidden');
            const response = await fetch(`${API_URL}?t=${new Date().getTime()}`);
            const data = await response.json();
            allOrders = data.sort((a, b) => new Date(b.createdAt || 0) - new Date(a.createdAt || 0));
            renderTable();
        } catch (error) {
            console.error('Erro:', error);
            if(elTableBody) elTableBody.innerHTML = `<tr><td colspan="7" class="p-6 text-center text-red-400">Erro ao carregar dados.</td></tr>`;
        } finally {
            if(elLoader) elLoader.classList.add('hidden');
        }
    }

    function getDateRange(filter) {
        const now = new Date();
        const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        let start = todayStart, end = todayStart;

        if (filter === 'custom') {
            start = new Date(elCustomStart.value + 'T00:00:00') || todayStart;
            end = new Date(elCustomEnd.value + 'T23:59:59') || todayStart;
        } else {
            switch (filter) {
                case 'today': break;
                case 'yesterday': start.setDate(start.getDate() - 1); end = new Date(start); break;
                case '7d': start.setDate(start.getDate() - 7); end = new Date(); break;
                case '30d': start.setDate(start.getDate() - 30); end = new Date(); break;
                case 'lifetime': start = new Date(2000, 0, 1); end = new Date(2100, 0, 1); break;
            }
        }
        end.setHours(23, 59, 59, 999);
        return { start, end };
    }

    function renderTable() {
        if(!elTableBody) return;
        const searchTerm = elSearch.value.toLowerCase();
        const statusTerm = elStatusFilter.value;
        const { start, end } = getDateRange(filterSelect.value);

        const filtered = allOrders.filter(order => {
            const orderDate = new Date(order.createdAt ? order.createdAt.replace(' ', 'T') : 0);
            if (filterSelect.value !== 'lifetime' && (orderDate < start || orderDate > end)) return false;

            if (statusTerm !== 'all') {
                const s = order.status ? order.status.toLowerCase() : '';
                if (statusTerm === 'paid' && s !== 'paid' && s !== 'completed') return false;
                if (statusTerm === 'pending' && s !== 'pending') return false;
                if (statusTerm === 'cancelled' && s !== 'cancelled' && s !== 'expired') return false;
            }

            const searchString = `${order.correlationId} ${order.customerName} ${order.email} ${order.whatsapp} ${order.productName}`.toLowerCase();
            return searchString.includes(searchTerm);
        });

        elTableBody.innerHTML = '';
        elTotalRecords.innerText = `${filtered.length} registros encontrados`;

        if (filtered.length === 0) {
            elTableBody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-slate-500">Nenhum pedido encontrado.</td></tr>`;
            return;
        }

        filtered.forEach(order => {
            try {
                const val = parseFloat(order.value || 0) / 100;
                const displayPrice = moneyFormatter.format(val);

                let statusBadge = '<span class="px-2 py-1 rounded text-[10px] font-bold uppercase bg-slate-500/10 text-slate-400 border border-slate-500/20">Desconhecido</span>';
                const s = order.status ? order.status.toUpperCase() : '';
                if (s === 'PAID' || s === 'COMPLETED') statusBadge = '<span class="px-2 py-1 rounded text-[10px] font-bold uppercase bg-green-500/10 text-green-400 border border-green-500/20">Aprovado</span>';
                else if (s === 'PENDING') statusBadge = '<span class="px-2 py-1 rounded text-[10px] font-bold uppercase bg-yellow-500/10 text-yellow-400 border border-yellow-500/20">Pendente</span>';
                else if (s) statusBadge = `<span class="px-2 py-1 rounded text-[10px] font-bold uppercase bg-red-500/10 text-red-400 border border-red-500/20">${s}</span>`;

                let dateDisplay = '---';
                if (order.createdAt) {
                    const d = new Date(order.createdAt.replace(' ', 'T'));
                    dateDisplay = `<div class="flex flex-col"><span class="text-white font-medium">${d.toLocaleDateString('pt-BR')}</span><span class="text-xs text-slate-500">${d.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'})}</span></div>`;
                }

                const hasAddress = !!(order.cep && order.address);
                
                const row = document.createElement('tr');
                row.className = 'hover:bg-slate-800/60 transition-colors group';
                row.style.cursor = 'pointer'; // FORÇA O CURSOR VIA STYLE
                row.setAttribute('onclick', `toggleOrderDetails(${order.id})`); // MÉTODO MAIS ROBUSTO
                
                row.innerHTML = `
                    <td class="p-4">${dateDisplay}<div class="text-[10px] text-slate-600 mt-1 font-mono">${order.correlationId || '---'}</div></td>
                    <td class="p-4 font-medium text-white">${order.customerName || '---'}<div class="text-xs text-slate-500 font-normal">${order.email || '---'}</div></td>
                    <td class="p-4 text-slate-400 font-mono text-xs">${order.whatsapp || '---'}</td>
                    <td class="p-4 text-slate-400 text-xs">${order.productName || 'Produto'}</td>
                    <td class="p-4 text-right font-bold text-white">${displayPrice}</td>
                    <td class="p-4 text-center">${statusBadge}</td>
                    <td class="p-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button class="text-slate-400 hover:text-white transition"><i data-lucide="${hasAddress ? 'truck' : 'info'}" class="w-4 h-4"></i></button>
                            <button onclick="event.stopPropagation(); deleteOrder(${order.id})" class="text-slate-500 hover:text-red-400 transition"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                        </div>
                    </td>
                `;
                elTableBody.appendChild(row);

                const detailRow = document.createElement('tr');
                detailRow.id = `detail-row-${order.id}`;
                detailRow.className = 'hidden bg-slate-900/80';
                detailRow.innerHTML = `
                    <td colspan="7">
                        <div class="p-6 border-t border-slate-800/50">
                            ${hasAddress ? `
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
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
                                        <span class="text-[10px] font-black text-blue-500 uppercase tracking-widest">ID da Transação</span>
                                        <span class="text-white text-xs font-mono break-all">${order.correlationId || '---'}</span>
                                    </div>
                                </div>
                            ` : `
                                <div class="text-slate-500 text-xs italic">Produto Digital: Nenhum dado de entrega coletado.</div>
                            `}
                        </div>
                    </td>
                `;
                elTableBody.appendChild(detailRow);
            } catch (err) {
                console.error("Erro na linha do pedido:", err, order);
            }
        });
        if(typeof lucide !== 'undefined') lucide.createIcons();
    }

    window.toggleOrderDetails = (id) => {
        const row = document.getElementById(`detail-row-${id}`);
        if (row) row.classList.toggle('hidden');
    };

    window.deleteOrder = async (id) => {
        if (!confirm('Excluir este pedido permanentemente?')) return;
        try {
            const res = await fetch(`${API_URL}?id=${id}`, { method: 'DELETE' });
            if (res.ok) fetchData();
            else alert('Erro ao deletar');
        } catch (e) { alert('Erro de conexão'); }
    };

    if(elRefreshBtn) elRefreshBtn.onclick = fetchData;
    if(elSearch) elSearch.oninput = renderTable;
    if(elStatusFilter) elStatusFilter.onchange = renderTable;
    if(filterSelect) filterSelect.onchange = () => {
        if (filterSelect.value === 'custom') elCustomDateContainer.classList.remove('hidden');
        else { elCustomDateContainer.classList.add('hidden'); renderTable(); }
    };
    if(elCustomApplyBtn) elCustomApplyBtn.onclick = renderTable;

    fetchData();
});
