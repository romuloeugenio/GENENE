<?php

// Gera 100 despesas no total, distribuídas pelas usinas e pelos nove meses.
// Execute: C:\xampp\php\php.exe tests\gerar_despesas_horas_extras.php
if (PHP_SAPI !== 'cli') {
    exit('Execute pelo terminal.');
}

require_once __DIR__ . '/../public_html/db.php';

$pdo = conectarBanco();
$lote = '[TESTE-HORAS-EXTRAS-2026-JAN-SET]';
$usinas = $pdo->query('SELECT id, nome FROM usinas ORDER BY id')->fetchAll();
$tecnicos = $pdo->query('SELECT id FROM tecnicos ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$consulta = $pdo->prepare('SELECT id_categoria FROM categoria WHERE descricao_categoria = ?');
$consulta->execute(['HORAS EXTRAS']);
$categoria = $consulta->fetchColumn();

if (!$usinas || !$categoria) {
    exit("Cadastre usinas e a categoria HORAS EXTRAS antes de executar.\n");
}

try {
    $pdo->beginTransaction();
    $consulta = $pdo->prepare('SELECT COUNT(*) FROM despesas WHERE descricao LIKE ?');
    $consulta->execute([$lote . '%']);

    if ((int) $consulta->fetchColumn() > 0) {
        throw new RuntimeException('Lote já existente. Nenhum registro inserido.');
    }

    $antes = (int) $pdo->query('SELECT COUNT(*) FROM despesas')->fetchColumn();
    $inserir = $pdo->prepare(
        'INSERT INTO despesas (usina_id, data, valor_despesa, descricao, categoria, tecnico)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    for ($indice = 0; $indice < 100; $indice++) {
        $usina = $usinas[$indice % count($usinas)];
        $sequenciaUsina = intdiv($indice, count($usinas));
        $mes = ($sequenciaUsina % 9) + 1;
        $inicioMes = new DateTimeImmutable(sprintf('2026-%02d-01', $mes));
        $dia = random_int(1, (int) $inicioMes->format('t'));
        $data = sprintf('2026-%02d-%02d', $mes, $dia);
        $valor = number_format(random_int(10000, 100000) / 100, 2, '.', '');
        $tecnico = $tecnicos ? $tecnicos[random_int(0, count($tecnicos) - 1)] : null;

        $inserir->execute([
            $usina['id'],
            $data,
            $valor,
            $lote . ' Despesa de horas extras - lançamento ' . ($indice + 1),
            $categoria,
            $tecnico
        ]);
    }

    $consulta = $pdo->prepare(
        'SELECT u.nome, COUNT(*) AS quantidade, COUNT(DISTINCT MONTH(d.data)) AS meses,
                MIN(d.data) AS primeira_data, MAX(d.data) AS ultima_data,
                MIN(d.valor_despesa) AS menor_valor, MAX(d.valor_despesa) AS maior_valor
         FROM despesas d JOIN usinas u ON u.id = d.usina_id
         WHERE d.descricao LIKE ? GROUP BY u.id, u.nome ORDER BY u.id'
    );
    $consulta->execute([$lote . '%']);
    $resumo = $consulta->fetchAll();
    $depois = (int) $pdo->query('SELECT COUNT(*) FROM despesas')->fetchColumn();

    if ($depois !== $antes + 100 || count($resumo) !== count($usinas)) {
        throw new RuntimeException('Quantidade de registros ou cobertura de usinas inválida.');
    }

    $pdo->commit();
    echo json_encode([
        'inseridos' => 100,
        'categoria' => (int) $categoria,
        'registros_anteriores_preservados' => $antes,
        'usinas' => $resumo
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, $erro->getMessage() . PHP_EOL);
    exit(1);
}
