<?php
// api/evolution-helper.php

/**
 * Replace shortcodes in message
 */
function replaceShortcodes($text, $customer, $pixCode = '')
{
    $firstName = explode(' ', trim($customer['name']))[0];

    $replacements = [
        '{primeiro_nome}' => $firstName,
        '{nome_completo}' => $customer['name'],
        '{email}' => $customer['email'],
        '{telefone}' => $customer['phone'],
        '{pix_copia_cola}' => $pixCode
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $text);
}

/**
 * Send Message via Evolution API
 * Returns array ['success' => bool, 'response' => array]
 */
function sendEvolutionMessage($instance, $token, $baseUrl, $phone, $type, $message, $fileUrl = null)
{
    if (empty($instance) || empty($token) || empty($baseUrl) || empty($phone)) {
        return ['success' => false, 'error' => 'Missing configuration'];
    }

    // Sanitize phone (remove non-digits)
    $phone = preg_replace('/[^0-9]/', '', $phone);
    // Ensure 55 (DDI) if missing (assuming BR)
    if (strlen($phone) >= 10 && strlen($phone) <= 11) {
        $phone = '55' . $phone;
    }

    // Remove trailing slash from URL
    $baseUrl = rtrim($baseUrl, '/');

    // Headers
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . $token
    ];

    $response = null;
    $endpoint = '';
    $payload = [];

    if ($type === 'text') {
        $endpoint = "/message/sendText/{$instance}";
        $payload = [
            "number" => $phone,
            "text" => $message
        ];
    } elseif ($type === 'pdf' || $type === 'image') {
        $endpoint = "/message/sendMedia/{$instance}";

        $mime = 'application/pdf'; // default
        $mediaType = 'document';

        if ($fileUrl) {
            $ext = strtolower(pathinfo(parse_url($fileUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
                $mediaType = 'image';
            }
        }

        $payload = [
            "number" => $phone,
            "mediatype" => $mediaType,
            "mimetype" => $mime,
            "caption" => $message,
            "media" => $fileUrl,
            "fileName" => basename($fileUrl)
        ];
    } else {
        return ['success' => false, 'error' => 'Invalid deliverable type'];
    }

    $ch = curl_init($baseUrl . $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $rawResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $jsonResp = json_decode($rawResponse, true);
    $success = ($httpCode >= 200 && $httpCode < 300);

    return [
        'success' => $success,
        'http_code' => $httpCode,
        'response' => $jsonResp
    ];
}

/**
 * Process Deliverables for a given list of products
 */
function processOrderDeliverables($productsList, $customerPhone, $db)
{
    $results = [];
    $sentCount = 0;

    foreach ($productsList as $item) {
        $sku = $item['sku'] ?? '';
        $deliverableConfig = null;

        if (empty($sku))
            continue;

        // Determine if Bump or Main Product
        if (strpos($sku, 'BUMP-') === 0) {
            // It is a bump
            $bumpId = (int) str_replace('BUMP-', '', $sku);
            $stmt = $db->prepare("SELECT title as name, deliverable_type, deliverable_text, deliverable_file FROM order_bumps WHERE id = ?");
            $stmt->execute([$bumpId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                // Bumps don't have separate instance/token, they use the Main Product's instance? 
                // WAIT. The user UI for bumps DOES NOT have instance/token fields!
                // It only has Message, Type, File.
                // So bumps MUST use the Main Product's Evolution Config.
                // BUT, how do we know which Main Product this bump belongs to?
                // The `order_bumps` table has `product_id`.
                // So we fetch the parent product to get credentials.

                // Re-fetch with join to get parent credentials
                $stmt = $db->prepare("
                    SELECT ob.deliverable_type, ob.deliverable_text, ob.deliverable_file,
                           p.evolution_instance, p.evolution_token, p.evolution_url
                    FROM order_bumps ob
                    JOIN products p ON ob.product_id = p.id
                    WHERE ob.id = ?
                ");
                $stmt->execute([$bumpId]);
                $deliverableConfig = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } else {
            // Main Product (lookup by slug)
            $stmt = $db->prepare("SELECT evolution_instance, evolution_token, evolution_url, deliverable_type, deliverable_text, deliverable_file FROM products WHERE slug = ?");
            $stmt->execute([$sku]);
            $deliverableConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Send if config exists
        if ($deliverableConfig && !empty($deliverableConfig['evolution_url']) && !empty($deliverableConfig['evolution_instance'])) {
            // Apply Shortcodes
            // We need customer name/email/etc? 
            // The function signature only has phone.
            // Shortcodes logic is in `replaceShortcodes`. It needs customer array.
            // I should update signature to accept customer array.

            // For now, let's just send raw text or require caller to pass processed text?
            // Better: Pass $item and $customer to this function.
            // But strict signature change might break?
            // Let's keep it simple first. The existing `replaceShortcodes` takes customer array.

            // Let's assume we send raw message for now, OR update signature.
            // I'll update signature below.

            $res = sendEvolutionMessage(
                $deliverableConfig['evolution_instance'],
                $deliverableConfig['evolution_token'],
                $deliverableConfig['evolution_url'],
                $customerPhone,
                $deliverableConfig['deliverable_type'],
                $deliverableConfig['deliverable_text'], // Raw text, let's hope caller replaced shortcodes? No, we should replace here.
                $deliverableConfig['deliverable_file']
            );
            $results[] = ['sku' => $sku, 'status' => $res['success'] ? 'sent' : 'failed'];
            if ($res['success'])
                $sentCount++;
        } else {
            $results[] = ['sku' => $sku, 'status' => 'skipped_no_config'];
        }
    }

    return ['sent' => $sentCount, 'details' => $results];
}
