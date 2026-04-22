<?php
/**
 * Função unificada para registro de logs em arquivo
 */
function log_activity($message, $filename = 'activity.log', $directory = null)
{
    // Se não for passado diretório, usa a pasta logs na raiz da api
    if ($directory === null) {
        $directory = __DIR__ . '/../logs';
    }

    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }

    $timestamp = date('[Y-m-d H:i:s]');
    $formatted_message = "{$timestamp} {$message}" . PHP_EOL;
    
    // Garante que o caminho do arquivo seja absoluto e limpo
    $filepath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    @file_put_contents($filepath, $formatted_message, FILE_APPEND);
}
