<?php

// Execute este arquivo pelo terminal: C:\xampp\php\php.exe instalar_local.php
// A instalação cria apenas o banco gerene_local e não apaga dados existentes.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Execute a instalação pelo terminal.');
}

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec('CREATE DATABASE IF NOT EXISTS gerene_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE gerene_local');
$tabelas = $pdo->query('SHOW TABLES')->fetchAll();

if (count($tabelas) === 0) {
    $sql = file_get_contents(__DIR__ . '/public_html/schema.sql');
    $pdo->exec($sql);
    echo "Banco gerene_local instalado.\n";
} else {
    echo "O banco gerene_local já possui tabelas. Nenhuma tabela foi alterada.\n";
}

echo "Acesso: http://localhost/gerene/\n";
