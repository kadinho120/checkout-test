<?php
/**
 * Send Message via Twilio WhatsApp API
 * 
 * @param string|null $accountSid Twilio Account SID (or fallback to ENV)
 * @param string|null $authToken Twilio Auth Token (or fallback to ENV)
 * @param string|null $from Twilio WhatsApp Sender Number (or fallback to ENV)
 * @param string $to Recipient phone number
 * @param string|null $contentSid Content Template SID (e.g., HX...)
 * @param string|array|null $contentVariables Template variables JSON or Array
 * @param string|null $body Freeform text message (used if ContentSid is empty)
 * @param string|null $mediaUrl Optional media/file URL
 * @return array ['success' => bool, 'http_code' => int, 'sid' => string|null, 'error' => string|null, 'response' => array|null]
 */
function sendTwilioMessage(
    $accountSid = null,
    $authToken = null,
    $from = null,
    $to = '',
    $contentSid = null,
    $contentVariables = null,
    $body = null,
    $mediaUrl = null
) {
    // 1. Resolve credentials (Parameters > getenv > $_ENV)
    $accountSid = !empty($accountSid) ? trim($accountSid) : (getenv('TWILIO_ACCOUNT_SID') ?: ($_ENV['TWILIO_ACCOUNT_SID'] ?? ''));
    $authToken = !empty($authToken) ? trim($authToken) : (getenv('TWILIO_AUTH_TOKEN') ?: ($_ENV['TWILIO_AUTH_TOKEN'] ?? ''));
    $from = !empty($from) ? trim($from) : (getenv('TWILIO_FROM') ?: (getenv('TWILIO_WHATSAPP_FROM') ?: ($_ENV['TWILIO_FROM'] ?? '')));

    if (empty($accountSid) || empty($authToken)) {
        return [
            'success' => false,
            'http_code' => 0,
            'sid' => null,
            'error' => 'Twilio Account SID ou Auth Token não configurados.',
            'response' => null
        ];
    }

    if (empty($from)) {
        return [
            'success' => false,
            'http_code' => 0,
            'sid' => null,
            'error' => 'Número de envio (From) da Twilio não configurado.',
            'response' => null
        ];
    }

    if (empty($to)) {
        return [
            'success' => false,
            'http_code' => 0,
            'sid' => null,
            'error' => 'Telefone do destinatário não informado.',
            'response' => null
        ];
    }

    // 2. Format recipient phone for WhatsApp (whatsapp:+55...)
    $cleanTo = trim($to);
    if (stripos($cleanTo, 'whatsapp:') === 0) {
        $cleanTo = substr($cleanTo, 9);
    }
    // Remove non-digit characters except +
    $cleanTo = preg_replace('/[^\d+]/', '', $cleanTo);
    $digitsOnlyTo = preg_replace('/\D/', '', $cleanTo);

    // If 10 or 11 digits (Brazilian phone without country code), prepend 55
    if (strlen($digitsOnlyTo) >= 10 && strlen($digitsOnlyTo) <= 11) {
        $cleanTo = '+55' . $digitsOnlyTo;
    } elseif (!str_starts_with($cleanTo, '+')) {
        $cleanTo = '+' . $cleanTo;
    }
    $formattedTo = 'whatsapp:' . $cleanTo;

    // 3. Format sender phone (whatsapp:+...)
    $cleanFrom = trim($from);
    if (stripos($cleanFrom, 'whatsapp:') === 0) {
        $cleanFrom = substr($cleanFrom, 9);
    }
    $cleanFrom = preg_replace('/[^\d+]/', '', $cleanFrom);
    if (!str_starts_with($cleanFrom, '+')) {
        $cleanFrom = '+' . $cleanFrom;
    }
    $formattedFrom = 'whatsapp:' . $cleanFrom;

    // 4. Build POST fields
    $postFields = [
        'To' => $formattedTo,
        'From' => $formattedFrom
    ];

    if (!empty($contentSid)) {
        $postFields['ContentSid'] = trim($contentSid);

        if (!empty($contentVariables)) {
            if (is_array($contentVariables)) {
                $postFields['ContentVariables'] = json_encode($contentVariables);
            } else {
                $postFields['ContentVariables'] = trim($contentVariables);
            }
        }
    } else {
        if (!empty($body)) {
            $postFields['Body'] = $body;
        }
        if (!empty($mediaUrl)) {
            $postFields['MediaUrl'] = $mediaUrl;
        }
    }

    // 5. Execute cURL
    $endpoint = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_USERPWD, "{$accountSid}:{$authToken}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $rawResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($rawResponse === false) {
        return [
            'success' => false,
            'http_code' => $httpCode ?: 0,
            'sid' => null,
            'error' => 'Falha na conexão cURL: ' . $curlError,
            'response' => null
        ];
    }

    $jsonResp = json_decode($rawResponse, true);
    $success = ($httpCode >= 200 && $httpCode < 300);
    $sid = $jsonResp['sid'] ?? null;
    $errorMessage = null;

    if (!$success) {
        $errorMessage = $jsonResp['message'] ?? ($jsonResp['detail'] ?? "Erro Twilio HTTP {$httpCode}");
        if (!empty($jsonResp['code'])) {
            $errorMessage .= " (Código: {$jsonResp['code']})";
        }
    }

    return [
        'success' => $success,
        'http_code' => $httpCode,
        'sid' => $sid,
        'error' => $errorMessage,
        'response' => $jsonResp
    ];
}
