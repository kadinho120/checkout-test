<?php
/**
 * Send SMS via DisparoPro API (POST /mt)
 * 
 * @param string|null $apiKey DisparoPro API Token (or fallback to ENV DISPAROPRO_API_KEY)
 * @param string $phone Recipient phone number (e.g., 5511999887744 or with masks)
 * @param string $message Text content of the SMS
 * @param string|null $partnerId Optional partner identifier (max 20 chars)
 * @param string|null $url Optional separate URL to be shortened by DisparoPro
 * @return array ['success' => bool, 'http_code' => int, 'response' => array|null, 'error' => string|null, 'detail' => array|null]
 */
function sendSmsDisparoPro($apiKey = null, $phone = '', $message = '', $partnerId = null, $url = null)
{
    // 1. Resolve API Key (Parameter > getenv > $_ENV)
    $apiKey = !empty($apiKey) ? trim($apiKey) : (getenv('DISPAROPRO_API_KEY') ?: ($_ENV['DISPAROPRO_API_KEY'] ?? ''));

    if (empty($apiKey)) {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => null,
            'error' => 'Chave de API da DisparoPro não configurada.',
            'detail' => null
        ];
    }

    if (empty($phone)) {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => null,
            'error' => 'Telefone do destinatário não informado.',
            'detail' => null
        ];
    }

    if (empty(trim($message)) && empty($url)) {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => null,
            'error' => 'Mensagem de SMS não pode ser vazia.',
            'detail' => null
        ];
    }

    // 2. Format phone number for DisparoPro (55 + DDD + number)
    $cleanPhone = preg_replace('/\D/', '', $phone);
    if (strlen($cleanPhone) >= 10 && strlen($cleanPhone) <= 11) {
        $cleanPhone = '55' . $cleanPhone;
    }

    if (strlen($cleanPhone) < 12 || strlen($cleanPhone) > 14) {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => null,
            'error' => 'Número de telefone inválido para envio de SMS: ' . $phone,
            'detail' => null
        ];
    }

    // 3. Extract URL if embedded in message or passed as separate argument
    $targetUrl = !empty($url) ? trim($url) : null;
    $cleanMessage = trim($message);

    if (empty($targetUrl)) {
        // Automatically extract any http/https URL present in the message text
        if (preg_match('/(https?:\/\/[^\s]+)/i', $cleanMessage, $matches)) {
            $targetUrl = $matches[1];
            // Remove the raw URL from the message body so DisparoPro attaches its shortened version
            $cleanMessage = trim(str_replace($targetUrl, '', $cleanMessage));
            $cleanMessage = preg_replace('/\s+/', ' ', $cleanMessage);
        }
    }

    // 4. Generate or sanitize partner ID
    if (empty($partnerId)) {
        $partnerId = substr(md5(uniqid(mt_rand(), true)), 0, 10);
    } else {
        $partnerId = substr(preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$partnerId), 0, 20);
    }

    // 5. Build payload with separate 'url' field for DisparoPro smart shortening
    $itemPayload = [
        'numero' => $cleanPhone,
        'servico' => 'short',
        'mensagem' => $cleanMessage,
        'parceiro_id' => $partnerId,
        'codificacao' => '0'
    ];

    if (!empty($targetUrl)) {
        $itemPayload['url'] = $targetUrl;
    }

    $payload = [$itemPayload];

    $endpoint = 'https://apihttp.disparopro.com.br/mt';

    // 5. Execute cURL
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);

    $rawResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($rawResponse === false) {
        return [
            'success' => false,
            'http_code' => $httpCode ?: 0,
            'response' => null,
            'error' => 'Falha de conexão com a API DisparoPro: ' . $curlError,
            'detail' => null
        ];
    }

    $jsonResp = json_decode($rawResponse, true);
    $firstDetail = $jsonResp['detail'][0] ?? null;

    // Check success condition:
    // HTTP 200/201 and codigo_status 02 (ACCEPTED/SENT) or 03 (DELIVERED) or descricao_detalhe 'Message Sent'
    $statusCode = $firstDetail['codigo_status'] ?? null;
    $detailCode = $firstDetail['codigo_detalhe'] ?? null;

    $isHttpOk = ($httpCode >= 200 && $httpCode < 300);
    $isDetailOk = false;

    if ($isHttpOk) {
        if ($statusCode === '02' || $statusCode === '03' || $statusCode === 2 || $statusCode === 3) {
            $isDetailOk = true;
        } elseif ($detailCode === '000' || $detailCode === '100' || $detailCode === '200') {
            $isDetailOk = true;
        } elseif (isset($firstDetail['status']) && in_array(strtoupper($firstDetail['status']), ['ACCEPTED', 'SENT', 'DELIVERED', 'OK'])) {
            $isDetailOk = true;
        }
    }

    $success = ($isHttpOk && $isDetailOk);
    $errorMessage = null;

    if (!$success) {
        if (!empty($firstDetail['descricao_detalhe'])) {
            $errorMessage = $firstDetail['descricao_detalhe'];
            if (!empty($firstDetail['codigo_detalhe'])) {
                $errorMessage .= " (Código: {$firstDetail['codigo_detalhe']})";
            }
        } elseif (!empty($jsonResp['title'])) {
            $errorMessage = $jsonResp['title'] . (!empty($jsonResp['detail']) && is_string($jsonResp['detail']) ? ': ' . $jsonResp['detail'] : '');
        } else {
            $errorMessage = "Erro DisparoPro HTTP {$httpCode}";
        }
    }

    return [
        'success' => $success,
        'http_code' => $httpCode,
        'response' => $jsonResp,
        'error' => $errorMessage,
        'detail' => $firstDetail
    ];
}
