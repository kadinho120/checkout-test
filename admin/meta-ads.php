<?php
require_once 'auth.php';
require_once 'meta-ads-api.php';

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
$appId = getenv('META_ADS_APP_ID');
$secret = getenv('META_ADS_APP_SECRET');
$redirect = getenv('META_ADS_REDIRECT_URI');
$version = getenv('META_ADS_GRAPH_VERSION');
$configId = getenv('META_ADS_LOGIN_CONFIG_ID');
$configured = $appId && $secret && filter_var($redirect, FILTER_VALIDATE_URL)
    && parse_url($redirect, PHP_URL_SCHEME) === 'https' && preg_match('/^v\d+\.\d+$/D', $version ?: '');
$_SESSION['meta_ads_csrf'] ??= bin2hex(random_bytes(32));
$csrf = $_SESSION['meta_ads_csrf'];
$error = '';
$accounts = $businesses = $rows = $stats = [];
$insightsLoaded = false;
$account = (string) ($_GET['account'] ?? '');
$level = (string) ($_GET['level'] ?? 'campaigns');
$levels = ['campaigns' => 'campaign', 'adsets' => 'adset', 'ads' => 'ad'];
$level = isset($levels[$level]) ? $level : 'campaigns';
$date = static function ($value, $fallback) {
    if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts)) {
        return $fallback;
    }
    return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $value : $fallback;
};
$since = $date($_GET['since'] ?? null, date('Y-m-01'));
$until = $date($_GET['until'] ?? null, date('Y-m-d'));
$token = $_SESSION['meta_ads_token'] ?? '';
$canManage = !empty($_SESSION['meta_ads_manage']);
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

try {
    if (isset($_GET['connect']) && $configured) {
        $_SESSION['meta_ads_state'] = bin2hex(random_bytes(32));
        $params = [
            'client_id' => $appId, 'redirect_uri' => $redirect,
            'state' => $_SESSION['meta_ads_state'], 'response_type' => 'code',
        ];
        if ($configId) {
            $params['config_id'] = $configId;
        } else {
            $params['scope'] = 'ads_read,ads_management,business_management';
        }
        header("Location: https://www.facebook.com/$version/dialog/oauth?" . http_build_query($params));
        exit;
    }

    if (isset($_GET['code']) || isset($_GET['error'])) {
        $state = $_SESSION['meta_ads_state'] ?? '';
        unset($_SESSION['meta_ads_state']);
        if (!$configured || !$state || !hash_equals($state, (string) ($_GET['state'] ?? ''))) {
            throw new RuntimeException('Sessão de autorização inválida. Inicie a conexão novamente.');
        }
        if (isset($_GET['error'])) {
            throw new RuntimeException('A conexão com a Meta foi cancelada ou não autorizada.');
        }
        $result = metaAdsRequest('oauth/access_token', [
            'client_id' => $appId, 'client_secret' => $secret,
            'redirect_uri' => $redirect, 'code' => $_GET['code'],
        ]);
        $accessToken = $result['access_token'] ?? '';
        if (!$accessToken) {
            throw new RuntimeException('A Meta não retornou um token de acesso.');
        }
        $permissions = metaAdsPages('me/permissions', [], $accessToken);
        $granted = array_column(array_filter($permissions, static fn($p) => $p['status'] === 'granted'), 'permission');
        if (!array_intersect(['ads_read', 'ads_management'], $granted)) {
            throw new RuntimeException('Autorize a leitura ou o gerenciamento de contas de anúncios.');
        }
        unset($_SESSION['meta_ads_objects']);
        $_SESSION['meta_ads_token'] = $accessToken;
        $_SESSION['meta_ads_manage'] = in_array('ads_management', $granted, true);
        $_SESSION['meta_ads_business'] = in_array('business_management', $granted, true);
        header('Location: meta-ads.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Sessão inválida. Recarregue o painel.');
        }
        if (isset($_POST['disconnect'])) {
            unset($_SESSION['meta_ads_token'], $_SESSION['meta_ads_manage'], $_SESSION['meta_ads_business'], $_SESSION['meta_ads_objects']);
            header('Location: meta-ads.php');
            exit;
        }
        $id = (string) ($_POST['object_id'] ?? '');
        $status = (string) ($_POST['status'] ?? '');
        if (!$token || !$canManage || !preg_match('/^\d+$/D', $id)
            || !in_array($status, ['ACTIVE', 'PAUSED'], true)
            || !in_array($id, $_SESSION['meta_ads_objects'][$account][$level] ?? [], true)) {
            throw new RuntimeException('A alteração não está disponível para este item.');
        }
        $result = metaAdsRequest($id, ['status' => $status], $token, true);
        if (empty($result['success'])) {
            throw new RuntimeException('A Meta não confirmou a alteração de status.');
        }
        header('Location: meta-ads.php?' . http_build_query(compact('account', 'level', 'since', 'until')));
        exit;
    }

    if ($token && $configured) {
        $fields = 'id,name,currency,timezone_name,business{id,name}';
        $accounts = array_column(metaAdsPages('me/adaccounts', ['fields' => $fields], $token), null, 'id');
        if (!empty($_SESSION['meta_ads_business'])) {
            $businesses = metaAdsPages('me/businesses', ['fields' => 'id,name'], $token);
            foreach ($businesses as $business) {
                foreach (['owned_ad_accounts', 'client_ad_accounts'] as $edge) {
                    try {
                        foreach (metaAdsPages($business['id'] . '/' . $edge, ['fields' => $fields], $token) as $item) {
                            $accounts[$item['id']] = $item;
                        }
                    } catch (RuntimeException $e) {
                        $error = 'Algumas contas de portfólio não puderam ser listadas. Confira as permissões do perfil.';
                    }
                }
            }
        }
        if (isset($accounts[$account]) && preg_match('/^act_\d+$/D', $account)) {
            if ($since > $until) {
                throw new RuntimeException('A data inicial deve ser anterior ou igual à data final.');
            }
            $rows = metaAdsPages("$account/$level", ['fields' => 'id,name,status,effective_status'], $token);
            $_SESSION['meta_ads_objects'][$account][$level] = array_column($rows, 'id');
            $key = $levels[$level] . '_id';
            $stats = array_column(metaAdsPages("$account/insights", [
                'level' => $levels[$level],
                'fields' => "$key,spend,impressions,clicks,cpm,cpc,ctr,actions",
                'time_range' => json_encode(compact('since', 'until')),
            ], $token), null, $key);
            $insightsLoaded = true;
        }
    }
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Meta Ads — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-slate-950 text-slate-100">
<div class="flex min-h-screen">
    <?php include 'sidebar.php'; ?>
    <main class="min-w-0 flex-1 p-4 sm:p-8">
        <h1 class="text-2xl font-bold mb-4">Meta Ads</h1>
        <?php if ($error): ?><p role="alert" class="mb-4 rounded bg-red-950 p-3"><?= $escape($error) ?></p><?php endif; ?>
        <?php if (!$configured): ?>
            <p>Configure o App ID, o segredo, a versão da Graph API e a URL de retorno no servidor para habilitar a conexão.</p>
        <?php endif; ?>
        <?php if ($configured): ?>
            <a href="?connect=1" class="inline-block rounded bg-blue-600 px-4 py-2"><?= $token ? 'Reconectar Facebook' : 'Conectar com Facebook' ?></a>
        <?php endif; ?>
        <?php if ($token): ?>
            <form method="post" class="inline-block ml-2">
                <input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
                <button name="disconnect" class="rounded bg-slate-800 px-4 py-2">Desconectar</button>
            </form>
            <?php if ($businesses): ?>
                <p class="mt-4 text-sm text-slate-400">Portfólios disponíveis: <?= $escape(implode(', ', array_column($businesses, 'name'))) ?></p>
            <?php endif; ?>
            <form class="my-5 flex flex-wrap items-end gap-3">
                <label class="grid gap-1">Conta de anúncios
                    <select name="account" required class="rounded bg-slate-800 p-2">
                        <option value="">Selecione uma conta</option>
                        <?php foreach ($accounts as $item): ?>
                            <option value="<?= $escape($item['id']) ?>" <?= $item['id'] === $account ? 'selected' : '' ?>>
                                <?= $escape(($item['business']['name'] ?? 'Conta disponível') . ' · ' . $item['name'] . ' · ' . $item['id']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="grid gap-1">Nível
                    <select name="level" class="rounded bg-slate-800 p-2">
                        <?php foreach (['campaigns' => 'Campanhas', 'adsets' => 'Conjuntos', 'ads' => 'Anúncios'] as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $value === $level ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="grid gap-1">De <input type="date" name="since" value="<?= $since ?>" required class="rounded bg-slate-800 p-2"></label>
                <label class="grid gap-1">Até <input type="date" name="until" value="<?= $until ?>" required class="rounded bg-slate-800 p-2"></label>
                <button class="rounded bg-blue-600 px-4 py-2">Consultar</button>
            </form>
        <?php endif; ?>
        <?php if ($rows): ?>
            <p class="mb-2 text-sm text-slate-400">Métricas da Meta · <?= $escape($accounts[$account]['currency']) ?> · <?= $escape($accounts[$account]['timezone_name']) ?></p>
            <p id="checkout-sync" class="mb-3 text-sm text-slate-400" role="status">Carregando os pagamentos do checkout…</p>
            <p class="mb-3 text-xs text-slate-400">Pedidos pagos com ID do anúncio, conjunto ou campanha. Datas do checkout em horário de Brasília. Receita menos mídia está disponível para contas em BRL e não inclui taxas, reembolsos ou custos de produto.</p>
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap text-left text-sm [&_td]:p-3 [&_th]:p-3">
                    <thead class="bg-slate-800"><tr>
                        <?php foreach (['Nome', 'Entrega', 'Gasto', 'Impressões', 'Cliques', 'CPM', 'CPC', 'CTR', 'Compras Meta', 'CPA Meta', 'Pagos no checkout', 'Receita bruta (R$)', 'Receita menos mídia (R$)', 'Ações'] as $title): ?>
                            <th><?= $title ?></th>
                        <?php endforeach; ?>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row):
                        $metric = $stats[$row['id']] ?? [];
                        $actions = array_column($metric['actions'] ?? [], 'value', 'action_type');
                        $purchases = (float) ($actions['purchase'] ?? $actions['omni_purchase'] ?? $actions['offsite_conversion.fb_pixel_purchase'] ?? 0);
                        $spend = (float) ($metric['spend'] ?? 0);
                        $fmt = static fn($n) => number_format((float) $n, 2, ',', '.');
                    ?>
                        <tr class="border-b border-slate-800" data-meta-id="<?= $escape($row['id']) ?>" data-spend="<?= $insightsLoaded && ($accounts[$account]['currency'] ?? '') === 'BRL' ? $spend : '' ?>">
                            <td><?= $escape($row['name']) ?></td><td><?= $escape($row['effective_status']) ?></td>
                            <td><?= $insightsLoaded ? $fmt($spend) : '—' ?></td><td><?= $insightsLoaded ? (int) ($metric['impressions'] ?? 0) : '—' ?></td><td><?= $insightsLoaded ? (int) ($metric['clicks'] ?? 0) : '—' ?></td>
                            <td><?= $insightsLoaded ? $fmt($metric['cpm'] ?? 0) : '—' ?></td><td><?= $insightsLoaded ? $fmt($metric['cpc'] ?? 0) : '—' ?></td><td><?= $insightsLoaded ? $fmt($metric['ctr'] ?? 0) . '%' : '—' ?></td>
                            <td><?= $insightsLoaded ? $purchases : '—' ?></td><td><?= $purchases > 0 ? $fmt($spend / $purchases) : '—' ?></td>
                            <td data-checkout-orders>—</td><td data-checkout-revenue>—</td><td data-checkout-balance>—</td>
                            <td>
                                <?php if ($canManage && in_array($row['status'], ['ACTIVE', 'PAUSED'], true)): ?>
                                    <form method="post" onsubmit="return confirm('Confirmar mudança de status na Meta?')">
                                        <input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
                                        <input type="hidden" name="object_id" value="<?= $escape($row['id']) ?>">
                                        <button name="status" value="<?= $row['status'] === 'ACTIVE' ? 'PAUSED' : 'ACTIVE' ?>" class="rounded bg-slate-800 px-3 py-1.5">
                                            <?= $row['status'] === 'ACTIVE' ? 'Pausar' : 'Ativar' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($account && !$error): ?>
            <p>Nenhum item encontrado para esta conta.</p>
        <?php endif; ?>
    </main>
</div>
<script>lucide.createIcons();</script>
<?php if ($rows): ?>
<script>
    const salesUrl = <?= json_encode('meta-ads-sales.php?' . http_build_query(compact('account', 'level', 'since', 'until')), JSON_HEX_TAG) ?>;
    const numberFormat = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const syncStatus = document.getElementById('checkout-sync');

    async function refreshCheckoutSales() {
        try {
            const response = await fetch(salesUrl, { cache: 'no-store' });
            if (!response.ok) throw new Error('Consulta indisponível');
            const sales = await response.json();
            document.querySelectorAll('[data-meta-id]').forEach(row => {
                const metrics = sales[row.dataset.metaId] || { orders: 0, revenue: 0 };
                row.querySelector('[data-checkout-orders]').textContent = metrics.orders;
                row.querySelector('[data-checkout-revenue]').textContent = numberFormat.format(metrics.revenue);
                row.querySelector('[data-checkout-balance]').textContent = row.dataset.spend === ''
                    ? '—' : numberFormat.format(metrics.revenue - Number(row.dataset.spend));
            });
            syncStatus.textContent = 'Pagamentos do checkout atualizados às ' + new Date().toLocaleTimeString('pt-BR');
        } catch (error) {
            syncStatus.textContent = 'Falha ao atualizar os pagamentos do checkout. Tentando novamente em 15 segundos.';
        } finally {
            window.setTimeout(refreshCheckoutSales, 15000);
        }
    }

    refreshCheckoutSales();
</script>
<?php endif; ?>
</body>
</html>
