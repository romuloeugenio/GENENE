<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Execute pelo terminal.');
}

require_once __DIR__ . '/../public_html/db.php';

try {
    $pdo = conectarBanco();
    $tabelaExiste = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'producao_estimada_usina'"
    )->fetchColumn();

    if ((int) $tabelaExiste === 0) {
        $pdo->exec(file_get_contents(__DIR__ . '/20261022_producao_estimada_usina.sql'));
        echo "Migração concluída: tabela producao_estimada_usina criada.\n";
    } else {
        echo "Nenhuma alteração: a tabela producao_estimada_usina já existe.\n";
    }
} catch (Throwable $erro) {
    fwrite(STDERR, "Migração não concluída: {$erro->getMessage()}\n");
    exit(1);
}
