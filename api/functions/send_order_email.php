<?php
/**
 * Envia e-mail formatado para o cliente
 */
require_once __DIR__ . '/send_gmail_api_email.php';

function sendOrderEmail($to, $subject, $body)
{
    if (empty($to) || empty($subject) || empty($body)) {
        return ['success' => false, 'error' => 'Missing email fields'];
    }

    return sendGmailApiEmail($to, $subject, $body, 'InstaBoost Suporte');
}
