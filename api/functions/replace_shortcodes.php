<?php
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
        '{pix_copia_cola}' => $pixCode,
        '{nome_do_produto}' => $customer['product_name'] ?? 'seu pedido'
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $text);
}
