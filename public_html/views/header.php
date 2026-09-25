<?php

if (!isset($titulo)) {
    http_response_code(403);
    exit('Acesse uma página do sistema.');
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= escapar($titulo) ?> - <?= escapar(NOME_EMPRESA) ?></title>
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
        <link rel="stylesheet" href="<?= escapar(endereco('assets/css/custom.css')) ?>">

        <?php if ($titulo === 'Dashboard'): ?>
            <script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
        <?php endif; ?>

        <script defer src="<?= escapar(endereco('assets/js/main.js')) ?>"></script>
    </head>
    <body>
        <header class="barra-superior">
            <div>
                <button type="button" id="alternar-menu" class="botao secundario" aria-controls="menu" aria-expanded="true">Menu</button>
                <strong><?= escapar(NOME_EMPRESA) ?></strong>
            </div>

            <div>
                <span><?= escapar($_SESSION['nome']) ?></span>
                <form method="post" action="<?= escapar(endereco('logout.php')) ?>">
                    <input type="hidden" name="token" value="<?= escapar(obterToken()) ?>">
                    <button type="submit" class="botao secundario">Sair</button>
                </form>
            </div>
        </header>

        <?php require __DIR__ . '/sidebar.php'; ?>

        <main class="conteudo">
            <h1><?= escapar($titulo) ?></h1>

            <?php if (lerCampo($_GET, 'msg') === 'sucesso'): ?>
                <div class="aviso sucesso" role="status">Operação realizada com sucesso.</div>
            <?php endif; ?>

            <?php if (lerCampo($_GET, 'error') === '1'): ?>
                <div class="aviso erro" role="alert">
                    <?= escapar($_SESSION['mensagem_erro'] ?? 'Não foi possível concluir a operação.') ?>
                </div>
                <?php unset($_SESSION['mensagem_erro']); ?>
            <?php endif; ?>
