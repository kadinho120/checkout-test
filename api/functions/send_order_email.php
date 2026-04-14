<?php
/**
 * Envia e-mail formatado para o cliente
 */
require_once __DIR__ . '/../email-sender-class.php'; // Vou mover a classe SimpleSMTP para este arquivo novo

function sendOrderEmail($to, $subject, $body)
{
    if (empty($to) || empty($subject) || empty($body)) {
        return ['success' => false, 'error' => 'Missing email fields'];
    }

    // Credentials from config.php
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $username = SMTP_USER;
    $password = SMTP_PASS;

    $smtp = new SimpleSMTP($host, $port, $username, $password);
    return $smtp->send($to, $subject, $body, 'InstaBoost Suporte');
}
