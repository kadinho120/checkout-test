<?php
// api/appmax-installments.php
// Retorna a tabela de parcelamento calculada para um valor específico

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions/appmax_request.php';

header('Content-Type: application/json');

$value_cents = isset($_GET['value']) ? (int) $_GET['value'] : 0;
if ($value_cents <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valor inválido.']);
    exit;
}

// Tabela de taxas padrão do lojista na Appmax (fallback caso a API oscile)
$merchant_rates = [
    1 => 0.0,
    2 => 5.98,
    3 => 8.97,
    4 => 11.96,
    5 => 14.95,
    6 => 17.94,
    7 => 20.93,
    8 => 23.92,
    9 => 26.91,
    10 => 29.90,
    11 => 32.89,
    12 => 35.88
];

$parcels = [];

// Tenta consultar a API de parcelas da Appmax
$inst_res = appmax_request('https://api.appmax.com.br/v1/payments/installments', 'POST', [
    'total_value' => $value_cents,
    'installments' => 12,
    'settings' => true
]);

if ($inst_res['http_code'] >= 200 && $inst_res['http_code'] < 300 && !empty($inst_res['data']['data']['parcels'])) {
    $apiParcels = $inst_res['data']['data']['parcels'];
    foreach ($apiParcels as $num => $total_val) {
        $num_int = (int) $num;
        $total_float = (float) $total_val;
        $parcel_float = round($total_float / $num_int, 2);
        $interest_rate = $merchant_rates[$num_int] ?? 0;
        
        $parcels[] = [
            'installment' => $num_int,
            'parcel_value' => $parcel_float,
            'total_value' => $total_float,
            'interest_rate' => $interest_rate,
            'text' => $num_int === 1 
                ? "1x de R$ " . number_format($parcel_float, 2, ',', '.') . " (sem juros)"
                : "{$num_int}x de R$ " . number_format($parcel_float, 2, ',', '.') . " (R$ " . number_format($total_float, 2, ',', '.') . ")"
        ];
    }
} else {
    // Fallback: cálculo com base nas taxas do lojista
    $base_val = $value_cents / 100;
    foreach ($merchant_rates as $num => $rate) {
        $total_float = round($base_val * (1 + ($rate / 100)), 2);
        $parcel_float = round($total_float / $num, 2);

        $parcels[] = [
            'installment' => $num,
            'parcel_value' => $parcel_float,
            'total_value' => $total_float,
            'interest_rate' => $rate,
            'text' => $num === 1 
                ? "1x de R$ " . number_format($parcel_float, 2, ',', '.') . " (sem juros)"
                : "{$num}x de R$ " . number_format($parcel_float, 2, ',', '.') . " (R$ " . number_format($total_float, 2, ',', '.') . ")"
        ];
    }
}

echo json_encode([
    'success' => true,
    'total_value' => $value_cents / 100,
    'installments' => $parcels
]);
