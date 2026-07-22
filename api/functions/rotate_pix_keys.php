<?php
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/log_activity.php';
require_once __DIR__ . '/woovi_create_pix_key.php';
require_once __DIR__ . '/woovi_set_default_pix_key.php';
require_once __DIR__ . '/woovi_delete_pix_key.php';

/**
 * Orquestra o ciclo completo de rotação de chaves Pix na Woovi:
 * 1. Gera uma nova chave Pix aleatória (EVP) na API da Woovi.
 * 2. Define a nova chave como a chave padrão da conta.
 * 3. Registra a nova chave na tabela pix_key_rotations no SQLite.
 * 4. Exclui da Woovi e marca como DELETED as chaves antigas com mais de 30 minutos.
 *
 * @return array Resultado da operação de rotação
 */
function rotate_pix_keys()
{
    log_activity("Iniciando ciclo de rotação de chaves Pix...", 'woovi_pix_key_rotation.log', __DIR__ . '/..');

    try {
        $database = new Database();
        $db = $database->getConnection();

        // 1. Gerar nova chave aleatória (EVP) na Woovi
        $create_res = woovi_create_pix_key('EVP');

        if (!$create_res['success']) {
            $msg = "Falha ao criar nova chave Pix na Woovi: " . ($create_res['error'] ?? 'Erro desconhecido');
            log_activity($msg, 'woovi_pix_key_rotation.log', __DIR__ . '/..');
            return [
                'success' => false,
                'error' => $msg
            ];
        }

        // Extrair chave criada do response (Suporta diferentes estruturas de resposta da Woovi API)
        $new_key = null;
        if (isset($create_res['data']['pixKey']['pixKey'])) {
            $new_key = $create_res['data']['pixKey']['pixKey'];
        } elseif (isset($create_res['data']['pixKey']['key'])) {
            $new_key = $create_res['data']['pixKey']['key'];
        } elseif (isset($create_res['data']['pixKey']) && is_string($create_res['data']['pixKey'])) {
            $new_key = $create_res['data']['pixKey'];
        } elseif (isset($create_res['data']['key'])) {
            $new_key = $create_res['data']['key'];
        } elseif (is_string($create_res['data'] ?? null)) {
            $new_key = $create_res['data'];
        }

        if (empty($new_key)) {
            $msg = "Chave Pix não encontrada na resposta da Woovi: " . json_encode($create_res['data']);
            log_activity($msg, 'woovi_pix_key_rotation.log', __DIR__ . '/..');
            return [
                'success' => false,
                'error' => $msg,
                'raw_response' => $create_res['data']
            ];
        }

        log_activity("Nova chave Pix criada com sucesso: {$new_key}", 'woovi_pix_key_rotation.log', __DIR__ . '/..');

        // 2. Definir a nova chave criada como padrão na Woovi
        $default_res = woovi_set_default_pix_key($new_key);
        if (!$default_res['success']) {
            log_activity("Aviso ao definir chave {$new_key} como padrão: " . ($default_res['error'] ?? ''), 'woovi_pix_key_rotation.log', __DIR__ . '/..');
        }

        // 3. Salvar nova chave no banco de dados SQLite e remover status de padrão das antigas
        $db->exec("UPDATE pix_key_rotations SET is_default = 0 WHERE is_default = 1");

        $ins = $db->prepare("
            INSERT INTO pix_key_rotations (pix_key, type, is_default, status) 
            VALUES (:pix_key, 'EVP', 1, 'ACTIVE')
        ");
        $ins->execute([':pix_key' => $new_key]);

        // 4. Buscar e excluir chaves antigas (com 30 minutos ou mais de criação)
        $deleted_keys = [];
        $failed_deletions = [];

        $stmt_old = $db->prepare("
            SELECT id, pix_key, created_at 
            FROM pix_key_rotations 
            WHERE status = 'ACTIVE' 
              AND pix_key != :new_key
              AND created_at <= datetime('now', '-30 minutes')
            ORDER BY created_at ASC
        ");
        $stmt_old->execute([':new_key' => $new_key]);
        $old_keys = $stmt_old->fetchAll(PDO::FETCH_ASSOC);

        foreach ($old_keys as $old) {
            $key_to_delete = $old['pix_key'];
            log_activity("Tentando excluir chave antiga: {$key_to_delete} (criada em: {$old['created_at']})", 'woovi_pix_key_rotation.log', __DIR__ . '/..');

            $del_res = woovi_delete_pix_key($key_to_delete);

            // Considera sucesso se foi excluída (2xx) ou se não foi encontrada (404)
            if ($del_res['success'] || ($del_res['http_code'] ?? 0) === 404) {
                $upd = $db->prepare("
                    UPDATE pix_key_rotations 
                    SET status = 'DELETED', is_default = 0, deleted_at = CURRENT_TIMESTAMP 
                    WHERE id = :id
                ");
                $upd->execute([':id' => $old['id']]);
                $deleted_keys[] = $key_to_delete;
                log_activity("Chave antiga {$key_to_delete} marcada como DELETED no banco.", 'woovi_pix_key_rotation.log', __DIR__ . '/..');
            } else {
                $failed_deletions[] = $key_to_delete;
                log_activity("Falha ao excluir chave antiga {$key_to_delete}: " . ($del_res['error'] ?? 'Erro desconhecido'), 'woovi_pix_key_rotation.log', __DIR__ . '/..');
            }
        }

        $success_msg = "Rotação concluída com sucesso! Nova chave padrão: {$new_key}";
        log_activity($success_msg, 'woovi_pix_key_rotation.log', __DIR__ . '/..');

        return [
            'success' => true,
            'message' => $success_msg,
            'new_pix_key' => $new_key,
            'deleted_keys' => $deleted_keys,
            'failed_deletions' => $failed_deletions
        ];

    } catch (Throwable $e) {
        $msg = "Exceção ao rotacionar chaves Pix: " . $e->getMessage();
        log_activity($msg, 'woovi_pix_key_rotation.log', __DIR__ . '/..');
        return [
            'success' => false,
            'error' => $msg
        ];
    }
}
