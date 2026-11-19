<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Execute pelo terminal.');
}

require_once __DIR__ . '/../public_html/db.php';

try {
    $pdo = conectarBanco();
    $colunaExiste = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'usuarios'
           AND COLUMN_NAME = 'Nivel_Usuario'"
    )->fetchColumn();

    if ((int) $colunaExiste === 0) {
        $pdo->exec(file_get_contents(__DIR__ . '/20261105_usuario_nivel.sql'));
        echo "Migração concluída: coluna Nivel_Usuario adicionada; registros existentes receberam nível 1.\n";
    } else {
        echo "Nenhuma alteração: a coluna Nivel_Usuario já existe.\n";
    }
} catch (Throwable $erro) {
    fwrite(STDERR, "Migração não concluída: {$erro->getMessage()}\n");
    exit(1);
}
