<?php

// Migração local por terminal. O backup deve ser feito antes de executar.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Execute pelo terminal.');
}

require_once __DIR__ . '/../public_html/db.php';

$pdo = conectarBanco();
$tabelas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

if (in_array('despesas', $tabelas, true) || in_array('categoria', $tabelas, true)) {
    exit("Migração não executada: despesas ou categoria já existe. Confira o estado do banco.\n");
}

if (!in_array('despesas_material', $tabelas, true)) {
    exit("Tabela despesas_material não encontrada.\n");
}

$pdo->exec(file_get_contents(__DIR__ . '/20260924_despesas_categoria.sql'));
echo "Migração concluída: despesas, categoria e vínculo com técnicos.\n";
