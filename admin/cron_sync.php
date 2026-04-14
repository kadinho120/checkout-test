<?php
// admin/cron_sync.php
require_once 'config_meta.php';

// 1. Carregar Vendas do seu JSON (Seu Database atual)
$ordersFile = __DIR__ . '/../api/database/detailed_orders.json';
$ordersData = json_decode(file_get_contents($ordersFile), true) ?? [];

// Filtrar vendas de HOJE e agrupar por adset_id
$hoje = date('Y-m-d');
$vendasPorAdset = [];

foreach ($ordersData as $order) {
    // Verifica se a venda é de hoje
    // Assumindo que seu JSON tem um campo 'date' ou 'created_at'
    $orderDate = substr($order['date'], 0, 10); // Ajuste conforme seu JSON real
    
    if ($orderDate === $hoje && isset($order['utm_content'])) { // utm_content deve ser o adset_id
        $adsetId = $order['utm_content']; // Certifique-se que o checkout manda o ID aqui
        
        if (!isset($vendasPorAdset[$adsetId])) {
            $vendasPorAdset[$adsetId] = ['receita' => 0, 'vendas' => 0];
        }
        $vendasPorAdset[$adsetId]['receita'] += (float)$order['amount']; // Valor da venda
        $vendasPorAdset[$adsetId]['vendas'] += 1;
    }
}

// 2. Buscar Dados do Facebook (Spend e Status)
$url = "https://graph.facebook.com/v18.0/" . AD_ACCOUNT_ID . "/insights?level=adset&fields=adset_id,adset_name,spend,account_currency,actions&date_preset=today&access_token=" . META_ACCESS_TOKEN;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);

$dadosConsolidados = [];

if (isset($response['data'])) {
    foreach ($response['data'] as $adset) {
        $id = $adset['adset_id'];
        $spend = (float)$adset['spend'];
        
        // Cruzar dados
        $receitaReal = $vendasPorAdset[$id]['receita'] ?? 0;
        $vendasReais = $vendasPorAdset[$id]['vendas'] ?? 0;
        
        $cpaReal = ($vendasReais > 0) ? ($spend / $vendasReais) : $spend;
        $roasReal = ($spend > 0) ? ($receitaReal / $spend) : 0;

        $dadosConsolidados[$id] = [
            'name' => $adset['adset_name'],
            'spend' => $spend,
            'receita' => $receitaReal,
            'vendas' => $vendasReais,
            'roas' => $roasReal,
            'cpa' => $cpaReal,
            'status_api' => 'ACTIVE' // Simplificação, idealmente buscar status real em outra chamada se necessário
        ];

        // --- LÓGICA DO SURFSCALE (AUTOMATION) ---
        
        // REGRA 1: ESCALAR (Se ROAS bom e tem volume)
        if ($roasReal >= ROAS_META_SCALE && $vendasReais >= 1) {
             alterarOrcamentoFacebook($id, PORCENTAGEM_ESCALA);
             logAction("ESCALAR: Adset $id - ROAS: $roasReal");
        }

        // REGRA 2: PAUSAR (Se gastou muito e não vendeu ou está caro)
        if ($vendasReais == 0 && $spend >= CPA_META_PAUSE) {
             pausarAdsetFacebook($id);
             logAction("PAUSAR: Adset $id - Gasto sem venda: $spend");
        }
    }
}

// 3. Salvar Cache para o Dashboard ver rápido
file_put_contents(__DIR__ . '/db/facebook_cache.json', json_encode($dadosConsolidados, JSON_PRETTY_PRINT));

echo "Sincronização concluída.";

// --- FUNÇÕES AUXILIARES ISOLADAS ---
require_once __DIR__ . '/../api/functions/alterar_orcamento_facebook.php';
require_once __DIR__ . '/../api/functions/pausar_adset_facebook.php';
require_once __DIR__ . '/../api/functions/log_activity.php';

function logAction($msg) {
    log_activity($msg, 'surfscale_log.txt', __DIR__);
}
?>

