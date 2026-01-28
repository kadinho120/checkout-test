<?php
// admin/salvar_token_backend.php

if (isset($_POST['token'])) {
    $token = $_POST['token'];
    
    // Opcional: Trocar esse token de curto prazo (2 horas) por um de longo prazo (60 dias)
    // Para simplificar, vamos salvar direto. O ideal seria fazer a troca via API.
    
    // Conteúdo do arquivo de configuração
    $configContent = "<?php\n";
    $configContent .= "define('META_ACCESS_TOKEN', '$token');\n";
    $configContent .= "define('LAST_UPDATED', '" . date('Y-m-d H:i:s') . "');\n";
    // Mantenha as outras configs fixas ou leia de um arquivo anterior
    $configContent .= "define('AD_ACCOUNT_ID', 'act_SEU_ID_DA_CONTA_AQUI'); // Edite manualmente ou puxe via API\n"; 
    $configContent .= "?>";
    
    // Salva no arquivo que o CRON usa
    file_put_contents('config_meta.php', $configContent);
    
    echo "Sucesso";
} else {
    echo "Erro: Token não recebido.";
}
?>
