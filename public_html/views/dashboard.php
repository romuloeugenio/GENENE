<?php

require_once __DIR__ . '/../biblioteca.php';

exigirLogin();

$pdo = conectarBanco();
$inicio = lerCampo($_GET, 'inicio', '2026-01-01');
$fim = lerCampo($_GET, 'fim', date('Y-m-d'));
$erro = '';

// Atalhos mantêm a data final e aplicam o mesmo período a todos os gráficos.
$duracao = filter_var(lerCampo($_GET, 'dias'), FILTER_VALIDATE_INT);

if (in_array($duracao, [7, 30, 90, 180, 365], true) && dataValida($fim)) {
    $inicio = (new DateTimeImmutable($fim))->modify('-' . ($duracao - 1) . ' days')->format('Y-m-d');
}

// Valida o intervalo antes de executar as consultas.
if (!dataValida($inicio) || !dataValida($fim) || $inicio > $fim) {
    $erro = 'Informe um período válido. Exibindo o período padrão a partir de 01/01/2026.';
    $inicio = '2026-01-01';
    $fim = date('Y-m-d');
}

$dataInicial = new DateTime($inicio);
$dataFinal = new DateTime($fim);
$dias = $dataInicial->diff($dataFinal)->days;

if ($dias > 3660) {
    $erro = 'O período máximo é de 3.660 dias. Exibindo o período padrão a partir de 01/01/2026.';
    $inicio = '2026-01-01';
    $fim = date('Y-m-d');
    $dataInicial = new DateTime($inicio);
    $dataFinal = new DateTime($fim);
    $dias = $dataInicial->diff($dataFinal)->days;
}

$porMes = $dias > 62;
$formatoSql = $porMes ? '%Y-%m' : '%Y-%m-%d';
$formatoChave = $porMes ? 'Y-m' : 'Y-m-d';
$formatoRotulo = $porMes ? 'm/Y' : 'd/m/Y';
$passo = $porMes ? '+1 month' : '+1 day';
$cursor = new DateTime($porMes ? substr($inicio, 0, 7) . '-01' : $inicio);
$periodos = [];
$rotulos = [];

// Cria todos os períodos, inclusive os que não possuem lançamentos.
while ($cursor <= $dataFinal) {
    $periodos[] = $cursor->format($formatoChave);
    $rotulos[] = $cursor->format($formatoRotulo);
    $cursor->modify($passo);
}

$consulta = $pdo->query('SELECT id, nome, tipo FROM usinas ORDER BY id LIMIT 4');
$usinas = $consulta->fetchAll();
$graficos = [];

foreach ($usinas as $usina) {
    // As quatro consultas são separadas para não multiplicar valores por JOIN.
    $consulta = $pdo->prepare(
        "SELECT DATE_FORMAT(data_producao, '$formatoSql') AS periodo, SUM(kwh_gerado) AS total
         FROM producao_energia
         WHERE usina_id = ? AND data_producao BETWEEN ? AND ?
         GROUP BY periodo ORDER BY periodo"
    );
    $consulta->execute([$usina['id'], $inicio, $fim]);
    $energia = $consulta->fetchAll(PDO::FETCH_KEY_PAIR);

    $consulta = $pdo->prepare(
        "SELECT DATE_FORMAT(d.data, '$formatoSql') AS periodo, SUM(d.valor_despesa) AS total
         FROM despesas d
         INNER JOIN categoria c ON c.id_categoria = d.categoria
         WHERE d.usina_id = ? AND d.data BETWEEN ? AND ?
           AND UPPER(TRIM(c.descricao_categoria)) = ?
         GROUP BY periodo ORDER BY periodo"
    );
    $consulta->execute([$usina['id'], $inicio, $fim, 'HORAS EXTRAS']);
    $horas = $consulta->fetchAll(PDO::FETCH_KEY_PAIR);
    $consulta = $pdo->prepare(
        "SELECT DATE_FORMAT(d.data, '$formatoSql') AS periodo, SUM(d.valor_despesa) AS total
         FROM despesas d
         INNER JOIN categoria c ON c.id_categoria = d.categoria
         WHERE d.usina_id = ? AND d.data BETWEEN ? AND ?
           AND UPPER(TRIM(c.descricao_categoria)) = ?
         GROUP BY periodo ORDER BY periodo"
    );
    $consulta->execute([$usina['id'], $inicio, $fim, 'DIARIAS']);
    $diarias = $consulta->fetchAll(PDO::FETCH_KEY_PAIR);
    $consulta = $pdo->prepare(
        "SELECT DATE_FORMAT(data, '$formatoSql') AS periodo, SUM(valor_despesa) AS total
         FROM despesas
         WHERE usina_id = ? AND data BETWEEN ? AND ?
         GROUP BY periodo ORDER BY periodo"
    );
    $consulta->execute([$usina['id'], $inicio, $fim]);
    $despesas = $consulta->fetchAll(PDO::FETCH_KEY_PAIR);

    $grafico = [
        'id' => (int) $usina['id'],
        'nome' => $usina['nome'],
        'tipo' => $usina['tipo'],
        'rotulos' => $rotulos,
        'energia' => [],
        'horas' => [],
        'diarias' => [],
        'despesas' => []
    ];

    foreach ($periodos as $periodo) {
        $grafico['energia'][] = (float) ($energia[$periodo] ?? 0);
        $grafico['diarias'][] = (float) ($diarias[$periodo] ?? 0);
        $grafico['horas'][] = (float) ($horas[$periodo] ?? 0);
        $grafico['despesas'][] = (float) ($despesas[$periodo] ?? 0);
    }

    $graficos[] = $grafico;
}

// Compara todas as usinas, usando os mesmos períodos dos painéis individuais.
$todasUsinas = $pdo->query('SELECT id, nome FROM usinas ORDER BY id')->fetchAll();
$consulta = $pdo->prepare(
    "SELECT usina_id, DATE_FORMAT(data_producao, '$formatoSql') AS periodo, SUM(kwh_gerado) AS total
     FROM producao_energia
     WHERE data_producao BETWEEN ? AND ?
     GROUP BY usina_id, periodo"
);
$consulta->execute([$inicio, $fim]);
$producaoTodas = [];

foreach ($consulta as $registro) {
    $producaoTodas[$registro['usina_id']][$registro['periodo']] = (float) $registro['total'];
}

$comparacaoUsinas = ['rotulos' => $rotulos, 'usinas' => []];

foreach ($todasUsinas as $usina) {
    $valores = [];

    foreach ($periodos as $periodo) {
        $valores[] = $producaoTodas[$usina['id']][$periodo] ?? 0;
    }

    $comparacaoUsinas['usinas'][] = ['nome' => $usina['nome'], 'energia' => $valores];
}

$titulo = 'Dashboard';
require __DIR__ . '/header.php';

?>
            <?php if ($erro !== ''): ?>
                <div class="aviso erro" role="alert"><?= escapar($erro) ?></div>
            <?php endif; ?>

            <section class="painel">
                <form method="get" class="filtro-periodo">
                    <div>
                        <label for="inicio">Data Inicial</label>
                        <input type="date" id="inicio" name="inicio" value="<?= escapar($inicio) ?>" required>
                    </div>
                    <div>
                        <label for="fim">Data Final</label>
                        <input type="date" id="fim" name="fim" value="<?= escapar($fim) ?>" required>
                    </div>
                    <button type="submit" class="botao">Pesquisar</button>
                    <?php foreach ([7 => '7D', 30 => '30D', 90 => '3M', 180 => '6M', 365 => '1A'] as $diasBotao => $rotuloBotao): ?>
                        <button type="submit" name="dias" value="<?= $diasBotao ?>" class="botao secundario"><?= $rotuloBotao ?></button>
                    <?php endforeach; ?>
                </form>
                <p>Comparação de todas as usinas e painéis individuais das primeiras quatro. Valores por <?= $porMes ? 'mês' : 'dia' ?>.</p>
            </section>

            <?php if ($todasUsinas): ?>
                <section class="painel">
                    <h2>Produção de todas as usinas</h2>
                    <p>Uma linha por usina · kWh · Período de <?= formatarData($inicio) ?> a <?= formatarData($fim) ?>. Clique na legenda para ocultar ou exibir uma usina.</p>
                    <div class="grafico grafico-todas-usinas">
                        <canvas id="producao-todas-usinas" role="img" aria-label="Comparação da produção de todas as usinas em linhas, em kWh"></canvas>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (empty($usinas)): ?>
                <section class="painel">
                    <p>Nenhuma usina cadastrada. Cadastre uma usina para começar.</p>
                    <?php if (podeEditar()): ?>
                        <a class="botao" href="<?= escapar(endereco('views/usinas/usinas.php')) ?>">Incluir Usina</a>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <div class="grade-usinas">
                <?php foreach ($graficos as $grafico): ?>
                    <section class="painel">
                        <h2><?= escapar($grafico['nome']) ?></h2>
                        <p><?= escapar($grafico['tipo']) ?></p>

                        <h3>Produção de energia</h3>
                        <div class="grafico">
                            <canvas id="energia-<?= $grafico['id'] ?>" role="img" aria-label="Produção de energia"></canvas>
                        </div>

                        <h3>Horas extras e diárias (R$)</h3>
                        <p>Linha: HORAS EXTRAS (eixo direito). Barras: DIARIAS (eixo esquerdo). Valores em reais.</p>
                        <div class="grafico">
                            <canvas id="comparativo-<?= $grafico['id'] ?>" role="img" aria-label="Horas extras em linha e diárias em barras, com eixos independentes em reais"></canvas>
                        </div>
                        <h3>Despesas totais (R$)</h3>
                        <p>Todas as categorias da usina no período selecionado.</p>
                        <div class="grafico">
                            <canvas id="despesas-<?= $grafico['id'] ?>" role="img" aria-label="Despesas totais de todas as categorias"></canvas>
                        </div>

                        <details>
                            <summary>Consultar os valores dos gráficos</summary>
                            <div class="tabela-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Período</th>
                                            <th>Energia (kWh)</th>
                                            <th>Horas extras (R$)</th>
                                            <th>Diárias (R$)</th>
                                            <th>Despesas (R$)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rotulos as $posicao => $rotulo): ?>
                                            <tr>
                                                <td><?= escapar($rotulo) ?></td>
                                                <td><?= formatarNumero($grafico['energia'][$posicao]) ?></td>
                                                <td><?= formatarNumero($grafico['horas'][$posicao]) ?></td>
                                                <td><?= formatarNumero($grafico['diarias'][$posicao]) ?></td>
                                                <td><?= formatarNumero($grafico['despesas'][$posicao]) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    </section>
                <?php endforeach; ?>
            </div>

            <div id="erro-graficos" class="aviso erro" hidden>
                Os gráficos não carregaram. Consulte os valores nas tabelas acima.
            </div>

            <noscript>
                <p>Ative o JavaScript para ver os gráficos. As tabelas continuam disponíveis.</p>
            </noscript>

            <script type="application/json" id="dados-todas-usinas">
                <?= json_encode($comparacaoUsinas, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            </script>
            <script type="application/json" id="dados-graficos">
                <?= json_encode($graficos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            </script>
<?php require __DIR__ . '/footer.php'; ?>
