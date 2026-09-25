<?php

// Exercita a migração com dados antigos em um banco temporário isolado.
if (PHP_SAPI !== 'cli') {
    exit('Execute pelo terminal.');
}

$pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$banco = 'gerene_migracao_' . bin2hex(random_bytes(5));
$verificacoes = 0;

/**
 * Confere uma condição esperada após a migração do banco.
 * @param bool $condicao Resultado esperado como verdadeiro.
 * @param string $mensagem Descrição do comportamento validado.
 * @return void Imprime sucesso ou lança exceção quando a condição falhar.
 */
function conferirMigracao($condicao, $mensagem)
{
    global $verificacoes;

    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }

    $verificacoes++;
    echo 'OK: ' . $mensagem . PHP_EOL;
}

try {
    $pdo->exec("CREATE DATABASE `$banco` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$banco`");
    $pdo->exec('CREATE TABLE tecnicos (id INT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE usinas (id INT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO usinas VALUES (7)');
    $pdo->exec(
        'CREATE TABLE despesas_material (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            usina_id INT UNSIGNED NOT NULL,
            data DATE NOT NULL,
            valor_despesa DECIMAL(12,2) UNSIGNED NOT NULL,
            descricao TEXT NOT NULL,
            categoria VARCHAR(100) NOT NULL,
            FOREIGN KEY (usina_id) REFERENCES usinas(id)
        ) ENGINE=InnoDB'
    );
    $consulta = $pdo->prepare('INSERT INTO despesas_material VALUES (?, 7, ?, ?, ?, ?)');
    $consulta->execute([11, '2026-09-01', '12.34', 'Cabos', 'Elétrica']);
    $consulta->execute([12, '2026-09-02', '10.50', 'Disjuntor', 'Elétrica']);
    $consulta->execute([13, '2026-09-03', '20.00', 'Óleo', 'Manutenção']);
    $pdo->exec(file_get_contents(__DIR__ . '/../migrations/20260924_despesas_categoria.sql'));

    $tabelas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    conferirMigracao(in_array('despesas', $tabelas, true) && !in_array('despesas_material', $tabelas, true), 'Tabela renomeada');
    conferirMigracao((int) $pdo->query('SELECT COUNT(*) FROM despesas')->fetchColumn() === 3, 'Todos os lançamentos preservados');
    conferirMigracao((int) $pdo->query('SELECT COUNT(*) FROM categoria')->fetchColumn() === 2, 'Categorias repetidas unificadas');
    conferirMigracao($pdo->query('SELECT SUM(valor_despesa) FROM despesas')->fetchColumn() === '42.84', 'Valores monetários preservados');
    $registro = $pdo->query('SELECT d.*, c.descricao_categoria FROM despesas d JOIN categoria c ON c.id_categoria=d.categoria WHERE d.id=11')->fetch(PDO::FETCH_ASSOC);
    conferirMigracao($registro['descricao_categoria'] === 'Elétrica' && (int) $registro['usina_id'] === 7, 'Categoria e usina preservadas pelo ID');
    conferirMigracao($registro['tecnico'] === null, 'Nenhum técnico fictício atribuído');
    conferirMigracao((int) $pdo->query('SELECT COUNT(*) FROM categoria WHERE Centro_de_custo IS NULL')->fetchColumn() === 2, 'Centros desconhecidos permanecem vazios');
    try {
        $pdo->exec('UPDATE despesas SET categoria=999 WHERE id=11');
        throw new RuntimeException('FK categoria não aplicada');
    } catch (PDOException $erro) {
        conferirMigracao($erro->getCode() === '23000', 'FK impede categoria inexistente');
    }
    try {
        $pdo->exec('UPDATE despesas SET tecnico=999 WHERE id=11');
        throw new RuntimeException('FK técnico não aplicada');
    } catch (PDOException $erro) {
        conferirMigracao($erro->getCode() === '23000', 'FK impede técnico inexistente');
    }

    echo $verificacoes . ' verificações de migração aprovadas.' . PHP_EOL;
} finally {
    $pdo->exec("DROP DATABASE IF EXISTS `$banco`");
}
