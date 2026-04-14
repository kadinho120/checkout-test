<?php
/**
 * Normaliza e hash os dados do usuário conforme requisitos da Meta (SHA-256)
 */
function normalizeAndHash($data, $key)
{
    if (empty($data))
        return null;
    $data = strtolower(trim($data));

    // Normalizações específicas
    if ($key === 'ph') {
        // Remover tudo que não for número
        $data = preg_replace('/[^0-9]/', '', $data);
        // Remover 0 à esquerda
        $data = ltrim($data, '0');
        // Adicionar código do país se faltar (assumindo BR +55 para números de 10-11 digitos)
        if (strlen($data) >= 10 && strlen($data) <= 11) {
            $data = '55' . $data;
        }
    }

    return hash('sha256', $data);
}
