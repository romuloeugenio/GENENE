<?php

// Migração local por terminal. O script pode ser executado novamente com segurança.
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
           AND COLUMN_NAME = 'administrador'"
    )->fetchColumn();

    if ((int) $colunaExiste === 0) {
        $pdo->exec(file_get_contents(__DIR__ . '/20260925_usuario_administrador.sql'));
        echo "Migração concluída: coluna administrador adicionada; registros existentes receberam 'N'.\n";
    } else {
        echo "Nenhuma alteração: a coluna administrador já existe.\n";
    }
} catch (Throwable $erro) {
    fwrite(STDERR, "Migração não concluída: {$erro->getMessage()}\n");
    exit(1);
}