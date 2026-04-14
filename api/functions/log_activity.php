<?php
/**
 * Função unificada para registro de logs em arquivo
 */
function log_activity($message, $filename = 'activity.log', $directory = __DIR__ . '/../logs')
{
    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }

    $timestamp = date('[Y-m-d H:i:s]');
    $formatted_message = "{$timestamp} {$message}" . PHP_EOL;
    $filepath = rtrim($directory, '/') . '/' . $filename;

    @file_put_contents($filepath, $formatted_message, FILE_APPEND);
}
