<?php

function metaAdsRequest(string $path, array $params = [], ?string $token = null, bool $write = false): array
{
    $version = getenv('META_ADS_GRAPH_VERSION');
    if (!preg_match('/^v\d+\.\d+$/D', $version ?: '')) {
        throw new RuntimeException('Configure META_ADS_GRAPH_VERSION com uma versão ativa do seu app.');
    }
    if ($token && getenv('META_ADS_APP_SECRET')) {
        $params['appsecret_proof'] = hash_hmac('sha256', $token, getenv('META_ADS_APP_SECRET'));
    }
    $url = "https://graph.facebook.com/$version/$path";
    $curl = curl_init($url . (!$write && $params ? '?' . http_build_query($params) : ''));
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $token ? ['Authorization: Bearer ' . $token] : [],
    ]);
    if ($write) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    $body = curl_exec($curl);
    $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $result = json_decode($body ?: '', true);
    if ($httpStatus >= 400 || !is_array($result) || isset($result['error'])) {
        $code = (int) ($result['error']['code'] ?? 0);
        throw new RuntimeException($code === 190
            ? 'A sessão da Meta expirou. Conecte sua conta novamente.'
            : "A Meta não concluiu a solicitação (HTTP $httpStatus, código $code).");
    }
    return $result;
}

function metaAdsPages(string $path, array $params, string $token): array
{
    $rows = [];
    $params['limit'] = 100;
    do {
        $page = metaAdsRequest($path, $params, $token);
        $rows = array_merge($rows, $page['data'] ?? []);
        $params['after'] = $page['paging']['cursors']['after'] ?? '';
    } while (!empty($page['paging']['next']) && $params['after'] !== '');
    return $rows;
}
