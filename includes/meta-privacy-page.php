<?php

$operator = trim(getenv('META_ADS_PRIVACY_OPERATOR') ?: 'ABR NEGÓCIOS DIGITAIS LTDA');
$email = trim(getenv('META_ADS_PRIVACY_EMAIL') ?: 'boostpayoficial@gmail.com');
if ($operator === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(503);
    exit('Página de privacidade aguardando configuração.');
}

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$isDeletion = $page === 'deletion';
$title = $isDeletion ? 'Solicitar exclusão de dados' : 'Política de Privacidade do app Meta Ads';
$contact = '<a href="mailto:' . $escape($email) . '">' . $escape($email) . '</a>';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($title) ?></title>
    <style>
        body { margin: 0; padding: 2rem 1rem; background: #f8fafc; color: #172033; font: 1rem/1.65 system-ui, sans-serif; }
        main { max-width: 48rem; margin: auto; padding: clamp(1rem, 4vw, 2.5rem); background: white; border: 1px solid #dbe4ee; border-radius: 1rem; }
        h1, h2 { line-height: 1.25; }
        h2 { font-size: 1.25rem; margin-top: 1.75rem; }
        a { color: #1d4ed8; overflow-wrap: anywhere; }
    </style>
</head>
<body>
<main>
    <h1><?= $escape($title) ?></h1>
    <p>Responsável pelo app: <?= $escape($operator) ?>.</p>
    <?php if ($isDeletion): ?>
        <p>Para solicitar a exclusão dos seus dados associados à integração Meta Ads, envie um e-mail para <?= $contact ?> com o assunto “Exclusão de dados do app Meta Ads”. Informe o e-mail que você usa no painel e, se souber, o ID da conta de anúncios vinculada. Nunca envie sua senha ou seu token.</p>
        <p>Podemos pedir informações adicionais para confirmar sua identidade antes de processar o pedido. Responderemos pelo mesmo canal com o resultado e, se houver dados que precisem ser retidos, o motivo.</p>
        <p>Para encerrar a conexão da sessão atual, use <strong>Admin → Meta Ads → Desconectar</strong>. Você também pode revogar o acesso do app nas configurações da sua conta Meta.</p>
        <p><a href="politica-de-privacidade.php">Consultar a Política de Privacidade</a></p>
    <?php else: ?>
        <p>Esta política descreve como o app Meta Ads do checkout usa os dados associados à conexão com a Meta.</p>
        <h2>Dados e finalidade</h2>
        <p>Ao autorizar a integração, o app recebe um token de acesso e verifica as permissões concedidas. O painel consulta nomes e identificadores de portfólios, contas de anúncios, campanhas, conjuntos e anúncios, além de seus status e métricas de desempenho. Com a permissão de gerenciamento, o app envia à Meta as alterações de status que você confirma no painel.</p>
        <p>O checkout usa identificadores de campanha, conjunto e anúncio recebidos pela URL para relacionar pedidos pagos à origem do tráfego. O painel mostra contagens e valores agregados por item.</p>
        <h2>Armazenamento e acesso</h2>
        <p>O token fica na sessão do servidor e é removido da sessão atual quando você usa <strong>Desconectar</strong>. Os identificadores de origem recebidos pelo checkout podem permanecer associados aos pedidos. O painel exige login administrativo, e o token é enviado à Meta para executar as operações autorizadas.</p>
        <h2>Contato e exclusão</h2>
        <p>Para dúvidas sobre os dados da integração, escreva para <?= $contact ?>. Consulte as <a href="exclusao-de-dados.php">instruções para solicitar a exclusão de dados</a>.</p>
    <?php endif; ?>
</main>
</body>
</html>
