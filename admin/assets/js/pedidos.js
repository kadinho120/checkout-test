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
            elTableBody.innerHTML = `<tr><td colspan="6" class="p-6 text-center text-red-400">Erro ao carregar dados.</td></tr>`;
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
            elTableBody.innerHTML = `<tr><td colspan="6" class="p-8 text-center text-slate-500">Nenhum pedido encontrado com esses filtros.</td></tr>`;
            return;
        }

        filtered.forEach(order => {
            // Tratamento de Valores
            let val = parseFloat(order.value);
            if (val > 100) val = val / 100; 

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

            // Ações (Pix + Deletar)
            let actionBtn = '';
            
            // Botão Pix (se aplicável)
            if (order.status === 'PENDING' && order.pixQrCode) {
                actionBtn += `<a href="${order.pixQrCode}" target="_blank" class="text-blue-400 hover:text-blue-300 transition mr-2" title="Ver QR Code"><i data-lucide="qr-code" class="w-4 h-4"></i></a>`;
            }
            
            // Botão Deletar (Novo)
            // Usamos correlationId como ID único
            if (order.correlationId) {
                actionBtn += `<button onclick="deleteOrder('${order.correlationId}')" class="text-slate-600 hover:text-red-500 transition" title="Excluir Pedido"><i data-lucide="trash-2" class="w-4 h-4"></i></button>`;
            }

            // --- NOVA LÓGICA DO WHATSAPP ---
            let whatsappBtn = '<span class="text-slate-600 text-xs">---</span>';
            if (order.whatsapp) {
                // Remove tudo que não for número para criar o link limpo
                const cleanNumber = order.whatsapp.replace(/\D/g, '');
                whatsappBtn = `
                    <a href="https://wa.me/${cleanNumber}" target="_blank" class="flex items-center gap-2 w-fit px-2 py-1 rounded text-[11px] font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20 transition">
                        <i data-lucide="message-circle" class="w-3 h-3"></i>
                        ${order.whatsapp}
                    </a>
                `;
            }

            const row = `
                <tr class="border-b border-slate-800 hover:bg-slate-900/50 transition group">
                    <td class="p-4">
                        ${dateDisplay}
                        <span class="text-[10px] text-slate-600 font-mono mt-1 block group-hover:text-slate-400 transition" title="${order.correlationId}">${idDisplay}</span>
                    </td>
                    <td class="p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-slate-800 flex items-center justify-center text-slate-400 font-bold text-xs shrink-0">
                                ${order.customerName ? order.customerName.substring(0,2).toUpperCase() : 'CL'}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-white truncate max-w-[150px]" title="${order.customerName}">${order.customerName || 'Cliente'}</p>
                                <p class="text-xs text-slate-500 truncate max-w-[150px]">${order.email || ''}</p>
                            </div>
                        </div>
                    </td>
                    
                    <td class="p-4">
                        ${whatsappBtn}
                    </td>

                    <td class="p-4 text-slate-300 text-sm max-w-[200px] truncate" title="${order.productName}">
                        ${order.productName || '---'}
                    </td>
                    <td class="p-4 text-right font-bold text-slate-200">
                        ${moneyFormatter.format(val)}
                    </td>
                    <td class="p-4 text-center">
                        ${statusBadge}
                    </td>
                    <td class="p-4 text-center">
                        <div class="flex justify-center items-center gap-2">
                            ${actionBtn}
                        </div>
                    </td>
                </tr>
            `;
            elTableBody.insertAdjacentHTML('beforeend', row);
        });
        
        if(window.lucide) window.lucide.createIcons();
    }

    // --- EVENT LISTENERS ---
    elSearch.addEventListener('input', renderTable);
    elStatusFilter.addEventListener('change', renderTable);
    
    elRefreshBtn.addEventListener('click', () => {
        const icon = elRefreshBtn.querySelector('i');
        icon.classList.add('animate-spin');
        fetchData().then(() => setTimeout(() => icon.classList.remove('animate-spin'), 500));
    });

    // Listeners de Data
    filterSelect.addEventListener('change', (e) => {
        if (e.target.value === 'custom') {
            elCustomDateContainer.classList.remove('hidden');
        } else {
            elCustomDateContainer.classList.add('hidden');
            renderTable();
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
        renderTable();
    });

    // Inicialização
    fetchData();
    setInterval(fetchData, 10000);

    // Função global para deletar
    window.deleteOrder = async (id) => {
        if (!confirm('Tem certeza que deseja EXCLUIR este pedido permanentemente?')) return;
        
        try {
            const response = await fetch(`${API_URL}?action=delete&id=${id}`);
            const result = await response.json();
            
            if (result.success) {
                // Atualiza a tabela sem recarregar a página
                fetchData();
            } else {
                alert('Erro ao excluir: ' + (result.message || 'Erro desconhecido'));
            }
        } catch (error) {
            console.error(error);
            alert('Erro de conexão ao tentar excluir.');
        }
    };
});
