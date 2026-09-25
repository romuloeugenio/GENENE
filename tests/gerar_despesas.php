<?php

// Execute pelo terminal: C:\xampp\php\php.exe tests\gerar_despesas.php
// Acrescenta 50 despesas por usina, sem alterar lançamentos existentes.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Execute a geração pelo terminal.');
}

require_once __DIR__ . '/../public_html/db.php';

$pdo = conectarBanco();
$hoje = new DateTimeImmutable('today');
$primeiroMes = $hoje->modify('first day of this month')->modify('-11 months');
$lote = 'TESTE-DESPESAS-' . $hoje->format('Ymd');
$usinas = $pdo->query('SELECT id, nome FROM usinas ORDER BY id')->fetchAll();
$categorias = $pdo->query('SELECT id_categoria, descricao_categoria FROM categoria ORDER BY id_categoria')->fetchAll();
$tecnicos = $pdo->query('SELECT id FROM tecnicos ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);

if (!$usinas || !$categorias) {
    exit("Cadastre ao menos uma usina e uma categoria antes de gerar despesas.\n");
}

$inserir = $pdo->prepare(
    'INSERT INTO despesas (usina_id, data, valor_despesa, descricao, categoria, tecnico)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$verificar = $pdo->prepare(
    'SELECT COUNT(*) AS quantidade, COUNT(DISTINCT DATE_FORMAT(data, \'%Y-%m\')) AS meses,
            MIN(valor_despesa) AS minimo, MAX(valor_despesa) AS maximo,
            SUM(valor_despesa) AS total
     FROM despesas WHERE usina_id = ? AND descricao LIKE ?'
);

try {
    $pdo->beginTransaction();

    $consulta = $pdo->prepare('SELECT COUNT(*) FROM despesas WHERE descricao LIKE ?');
    $consulta->execute(['[' . $lote . ']%']);

    if ((int) $consulta->fetchColumn() > 0) {
        throw new RuntimeException('Este lote diário já existe. Nenhuma despesa foi acrescentada.');
    }

    $quantidadeAntes = (int) $pdo->query('SELECT COUNT(*) FROM despesas')->fetchColumn();
    $resumo = [];

    foreach ($usinas as $usina) {
        for ($numero = 0; $numero < 50; $numero++) {
            // O módulo distribui quatro ou cinco lançamentos por mês.
            $mes = $primeiroMes->modify('+' . ($numero % 12) . ' months');
            $ultimoDia = (int) $mes->format('t');

            if ($mes->format('Y-m') === $hoje->format('Y-m')) {
                $ultimoDia = (int) $hoje->format('d');
            }

            $dia = random_int(1, $ultimoDia);
            $data = $mes->setDate((int) $mes->format('Y'), (int) $mes->format('m'), $dia);
            $centavos = random_int(10000, 100000);
            $valor = number_format($centavos / 100, 2, '.', '');
            $categoria = $categorias[$numero % count($categorias)];
            $tecnico = $tecnicos ? $tecnicos[$numero % count($tecnicos)] : null;
            $descricao = '[' . $lote . '] Despesa de teste ' . ($numero + 1);
            $descricao .= '/50 - ' . $categoria['descricao_categoria'];

            $inserir->execute([
                $usina['id'],
                $data->format('Y-m-d'),
                $valor,
                $descricao,
                $categoria['id_categoria'],
                $tecnico
            ]);
        }

        $verificar->execute([$usina['id'], '[' . $lote . ']%']);
        $resultado = $verificar->fetch();

        if ((int) $resultado['quantidade'] !== 50 || (int) $resultado['meses'] !== 12
            || (float) $resultado['minimo'] < 100 || (float) $resultado['maximo'] > 1000) {
            throw new RuntimeException('A massa de teste não passou na verificação da usina ' . $usina['nome']);
        }

        $resumo[] = ['usina' => $usina['nome']] + $resultado;
    }

    $quantidadeDepois = (int) $pdo->query('SELECT COUNT(*) FROM despesas')->fetchColumn();

    if ($quantidadeDepois !== $quantidadeAntes + count($usinas) * 50) {
        throw new RuntimeException('A quantidade final de despesas não corresponde ao esperado.');
    }

    $pdo->commit();

    echo json_encode([
        'lote' => $lote,
        'periodo' => [$primeiroMes->format('Y-m-d'), $hoje->format('Y-m-d')],
        'existentes_preservados' => $quantidadeAntes,
        'inseridos' => count($usinas) * 50,
        'total_no_banco' => $quantidadeDepois,
        'usinas' => $resumo
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, $erro->getMessage() . PHP_EOL);
    exit(1);
}
