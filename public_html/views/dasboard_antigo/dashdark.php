<?php

require_once __DIR__ . '/../biblioteca.php';

exigirLogin();

$pdo = conectarBanco();
$aviso = '';
$inicio = lerCampo($_GET, 'inicio', '2026-01-01');
$fim = lerCampo($_GET, 'fim', date('Y-m-d'));

if (!dataValida($inicio) || !dataValida($fim) || $inicio > $fim) {
    $aviso = 'Período inválido. Exibindo o período padrão a partir de 01/01/2026.';
    $inicio = '2026-01-01';
    $fim = date('Y-m-d');
}

// Carrega no máximo 365 dias até a data final. Os botões de período navegam nesse intervalo.
$limiteInicial = (new DateTimeImmutable($fim))->modify('-364 days')->format('Y-m-d');
$limiteInicial = max('1000-01-01', $limiteInicial);

if ($inicio < $limiteInicial) {
    $inicio = $limiteInicial;
    $aviso = 'O intervalo foi ajustado ao limite de um ano (365 dias).';
}

$consulta = $pdo->query('SELECT id, nome, tipo, capacidade_kw, status FROM usinas ORDER BY id');
$usinas = $consulta->fetchAll();
$idRecebido = lerCampo($_GET, 'usina_id', (string) ($usinas[0]['id'] ?? '0'));
$usinaSelecionada = null;

foreach ($usinas as $usina) {
    if ((string) $usina['id'] === $idRecebido) {
        $usinaSelecionada = $usina;
        break;
    }
}

if ($usinas && !$usinaSelecionada) {
    http_response_code(400);
    $aviso = 'Selecione uma usina válida para consultar os gráficos.';
}

$energia = [];
$horas = [];
$diarias = [];
$despesas = [];
$registrosProducao = [];

if ($usinaSelecionada) {
    $parametros = [$usinaSelecionada['id'], $limiteInicial, $fim];

    // Cada consulta soma apenas uma tabela para evitar duplicação de valores.
    $consulta = $pdo->prepare(
        'SELECT data_producao, SUM(kwh_gerado) AS total
         FROM producao_energia
         WHERE usina_id = ? AND data_producao BETWEEN ? AND ?
         GROUP BY data_producao ORDER BY data_producao'
    );
    $consulta->execute($parametros);
    $energia = $consulta->fetchAll(PDO::FETCH_KEY_PAIR);

    $consulta = $pdo->prepare(
        'SELECT d.data, SUM(d.valor_despesa) AS total
         FROM despesas d
         INNER JOIN categoria c ON c.id_categoria = d.categoria
         WHERE d.usina_id = ? AND d.data BETWEEN ? AND ?
           AND UPPER(TRIM(c.descricao_categoria)) = ?
         GROUP BY d.data ORDER BY d.data'
    );
    $consulta->execute([$usinaSelecionada['id'], $limiteInicial, $fim, 'HORAS EXTRAS']);
    $horas = $consulta->fetchAll(PDO::FETCH_KEY_PAIR);
    $consulta = $pdo->prepare(
        'SELECT d.data, SUM(d.valor_despesa) AS total
         FROM despesas d
         INNER JOIN categoria c ON c.id_categoria = d.categoria
         WHERE d.usina_id = ? AND d.data BETWEEN ? AND ?
           AND UPPER(TRIM(c.descricao_categoria)) = ?
         GROUP BY d.data ORDER BY d.data'
    );
    $consulta->execute([$usinaSelecionada['id'], $limiteInicial, $fim, 'DIARIAS']);
    $diarias = $consulta->fetchAll(PDO::FETCH_KEY_PAIR);
    $consulta = $pdo->prepare(
        'SELECT data, SUM(valor_despesa) AS total
         FROM despesas
         WHERE usina_id = ? AND data BETWEEN ? AND ?
         GROUP BY data ORDER BY data'
    );
    $consulta->execute($parametros);
    $despesas = $consulta->fetchAll(PDO::FETCH_KEY_PAIR);

    $consulta = $pdo->prepare(
        'SELECT id, data_producao, kwh_gerado
         FROM producao_energia
         WHERE usina_id = ? AND data_producao BETWEEN ? AND ?
         ORDER BY data_producao DESC, id DESC'
    );
    $consulta->execute($parametros);
    $registrosProducao = $consulta->fetchAll();
}

// A série diária mantém os zeros. O JavaScript agrupa por mês ao ampliar o período.
$serie = [];
$totais = ['energia' => 0, 'horas' => 0, 'despesas' => 0, 'diarias' => 0];
$cursor = new DateTimeImmutable($limiteInicial);
$dataFinal = new DateTimeImmutable($fim);

while ($cursor <= $dataFinal) {
    $data = $cursor->format('Y-m-d');
    $dia = [
        'data' => $data,
        'energia' => (float) ($energia[$data] ?? 0),
        'diarias' => (float) ($diarias[$data] ?? 0),
        'horas' => (float) ($horas[$data] ?? 0),
        'despesas' => (float) ($despesas[$data] ?? 0)
    ];
    $serie[] = $dia;

    if ($data >= $inicio) {
        foreach ($totais as $chave => $total) {
            $totais[$chave] += $dia[$chave];
        }
    }

    $cursor = $cursor->modify('+1 day');
}

// Uma consulta traz a produção de todas as usinas, independentemente da seleção do topo.
$consulta = $pdo->prepare(
    'SELECT usina_id, data_producao, SUM(kwh_gerado) AS total
     FROM producao_energia
     WHERE data_producao BETWEEN ? AND ?
     GROUP BY usina_id, data_producao'
);
$consulta->execute([$limiteInicial, $fim]);
$producaoTodas = [];

foreach ($usinas as $usina) {
    $producaoTodas[$usina['id']] = ['nome' => $usina['nome'], 'valores' => []];
}

foreach ($consulta as $registro) {
    $producaoTodas[$registro['usina_id']]['valores'][$registro['data_producao']] = (float) $registro['total'];
}

$dados = [
    'inicio' => $inicio,
    'fim' => $fim,
    'limiteInicial' => $limiteInicial,
    'usinaId' => $usinaSelecionada ? (int) $usinaSelecionada['id'] : null,
    'serie' => $serie,
    'producao' => $registrosProducao,
    'todasUsinas' => array_values($producaoTodas)
];

$diasVisiveis = (new DateTimeImmutable($inicio))->diff($dataFinal)->days + 1;
$links = [
    ['DashDark', 'views/dashdark.php', '◈'],
    ['Dashboard', 'views/dashboard.php', '▦'],
    ['Usinas', 'views/usinas/index_usinas.php', '☀'],
    ['Técnicos', 'views/tecnicos/index_tecnicos.php', '♙'],
    ['Produção de Energia', 'views/producao/index_producao.php', 'ϟ'],
    ['Produção Estimada', 'views/producao_estimada/index_producao_estimada.php', '◷'],
    ['Despesas', 'views/despesas/index_despesas.php', '▤'],
    ['Categorias', 'views/categoria/index_categoria.php', '▣']
];

if (podeEditar() && podeExcluir()) {
    $links[] = ['Usuários', 'views/usuarios/index_usuarios.php', '♙'];
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="theme-color" content="#10121b">
        <title>DashDark - GERENE</title>
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
        <script defer src="<?= escapar(endereco('assets/js/dashdark.js')) ?>"></script>
    </head>
    <body class="min-h-screen bg-[#10121b] font-sans text-sm text-slate-200 antialiased [color-scheme:dark]">
        <!-- Layout independente: o dashboard clássico mantém os estilos anteriores. -->
        <aside id="menu-dark" class="fixed inset-y-0 left-0 z-40 hidden w-60 flex-col border-r border-white/5 bg-[#151722] px-4 py-7 lg:flex">
            <button id="fechar-menu-dark" type="button" class="mb-5 self-end rounded-lg border border-white/10 px-3 py-2 text-xs lg:hidden">Fechar menu</button>
            <a href="<?= escapar(endereco('views/dashdark.php')) ?>" class="mb-10 flex items-center gap-3 px-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-500 text-xl font-bold text-white">ϟ</span>
                <span class="text-xl font-bold tracking-widest text-white">GERENE</span>
            </a>
            <p class="mb-3 px-3 text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">Workspace / energia</p>
            <nav aria-label="Menu principal" class="space-y-1 overflow-y-auto">
                <?php foreach ($links as $link): ?>
                    <a
                        href="<?= escapar(endereco($link[1])) ?>"
                        class="flex items-center gap-3 rounded-lg px-3 py-3 text-xs font-medium transition <?= $link[0] === 'DashDark' ? 'bg-violet-500/15 text-violet-300' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>"
                        <?= $link[0] === 'DashDark' ? 'aria-current="page"' : '' ?>
                    >
                        <span class="w-5 text-center text-lg" aria-hidden="true"><?= $link[2] ?></span>
                        <?= escapar($link[0]) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="mt-auto border-t border-white/5 px-3 pt-6 text-xs text-slate-500">
                <p class="mb-2 flex items-center gap-2 text-slate-300"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>Gestão de usinas</p>
                Fotovoltaicas & hidrelétricas
            </div>
        </aside>

        <div class="lg:ml-60">
            <header class="flex min-h-20 items-center justify-between gap-4 border-b border-white/5 px-5 lg:px-9">
                <div class="flex items-center gap-3">
                    <button id="botao-menu-dark" type="button" class="rounded-lg border border-white/10 px-3 py-2 lg:hidden" aria-controls="menu-dark" aria-expanded="false">Menu</button>
                    <p class="text-xs text-slate-500">Painéis <span class="mx-2">/</span> <span class="text-slate-200">DashDark</span></p>
                </div>
                <div class="flex items-center gap-4">
                    <span class="hidden text-xs text-slate-400 sm:block"><?= escapar($_SESSION['nome']) ?></span>
                    <form method="post" action="<?= escapar(endereco('logout.php')) ?>">
                        <input type="hidden" name="token" value="<?= escapar(obterToken()) ?>">
                        <button type="submit" class="rounded-lg border border-white/10 px-3 py-2 text-xs text-slate-300 hover:bg-white/5">Sair ↗</button>
                    </form>
                </div>
            </header>

            <main class="mx-auto max-w-[1700px] px-5 py-8 lg:px-9">
                <div class="mb-7 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="mb-2 text-[10px] font-semibold uppercase tracking-[0.2em] text-violet-400">Inteligência da operação</p>
                        <h1 class="text-2xl font-semibold tracking-tight text-white sm:text-3xl">Visão geral da usina</h1>
                        <p class="mt-2 text-xs text-slate-400">Produção, equipe e despesas no mesmo período.</p>
                    </div>
                    <span class="rounded-full border border-violet-400/20 bg-violet-400/5 px-4 py-2 text-xs text-violet-300">◈ DashDark</span>
                </div>

                <?php if ($aviso !== ''): ?>
                    <p class="mb-5 rounded-xl border border-amber-400/20 bg-amber-400/10 p-4 text-amber-200" role="alert"><?= escapar($aviso) ?></p>
                <?php endif; ?>

                <form id="filtro-dark" method="get" class="mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-white/5 bg-[#191c29] p-5">
                    <div>
                        <label for="inicio" class="mb-2 block text-xs text-slate-400">Data inicial</label>
                        <input id="inicio" name="inicio" type="date" value="<?= escapar($inicio) ?>" min="1000-01-01" max="9999-12-31" required class="rounded-lg border border-white/10 bg-[#121520] px-3 py-2.5 text-xs outline-none focus:border-violet-400">
                    </div>
                    <div>
                        <label for="fim" class="mb-2 block text-xs text-slate-400">Data final</label>
                        <input id="fim" name="fim" type="date" value="<?= escapar($fim) ?>" min="1000-01-01" max="9999-12-31" required class="rounded-lg border border-white/10 bg-[#121520] px-3 py-2.5 text-xs outline-none focus:border-violet-400">
                    </div>
                    <div class="min-w-48 flex-1">
                        <label for="usina_id" class="mb-2 block text-xs text-slate-400">Usina</label>
                        <select id="usina_id" name="usina_id" required class="w-full rounded-lg border border-white/10 bg-[#121520] px-3 py-2.5 text-xs outline-none focus:border-violet-400">
                            <?php if (!$usinaSelecionada): ?>
                                <option value="">Selecione uma usina</option>
                            <?php endif; ?>
                            <?php foreach ($usinas as $usina): ?>
                                <option value="<?= (int) $usina['id'] ?>" <?= (string) $usina['id'] === $idRecebido ? 'selected' : '' ?>><?= escapar($usina['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="rounded-lg bg-violet-500 px-5 py-2.5 text-xs font-semibold text-white hover:bg-violet-400">Aplicar período</button>
                </form>

                <?php if (!$usinaSelecionada): ?>
                    <section class="rounded-xl border border-dashed border-white/15 p-12 text-center">
                        <h2 class="text-lg text-white"><?= $usinas ? 'Escolha a usina para analisar' : 'Sua operação começa aqui' ?></h2>
                        <p class="mt-3 text-slate-400"><?= $usinas ? 'Use a seleção acima e aplique o período.' : 'Cadastre uma usina e seus lançamentos para visualizar os gráficos.' ?></p>
                        <?php if (!$usinas && podeEditar()): ?>
                            <a href="<?= escapar(endereco('views/usinas/usinas.php')) ?>" class="mt-6 inline-block rounded-lg bg-violet-500 px-5 py-3 text-white">Incluir Usina</a>
                        <?php endif; ?>
                    </section>
                <?php else: ?>
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <h2 class="font-medium text-white"><?= escapar($usinaSelecionada['nome']) ?> <span class="ml-2 text-xs font-normal text-slate-500"><?= escapar($usinaSelecionada['tipo']) ?></span></h2>
                        <p id="periodo-visivel" class="text-xs text-slate-400" aria-live="polite"><?= formatarData($inicio) ?> — <?= formatarData($fim) ?> · <?= $diasVisiveis ?> dias</p>
                    </div>

                    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <section class="rounded-xl border border-white/5 bg-[#191c29] p-5">
                            <p class="text-xs text-slate-400">Produção de energia <span class="float-right text-violet-400">ϟ</span></p>
                            <p class="mt-4 text-2xl font-semibold text-white"><span id="total-energia"><?= formatarNumero($totais['energia']) ?></span> <span class="text-xs font-normal text-slate-500">kWh</span></p>
                            <p class="mt-2 text-[11px] text-slate-500">Energia gerada no período</p>
                        </section>
                        <section class="rounded-xl border border-white/5 bg-[#191c29] p-5">
                            <p class="text-xs text-slate-400">Horas extras <span class="float-right text-cyan-300">◷</span></p>
                            <p class="mt-4 text-2xl font-semibold text-white"><span id="total-horas"><?= formatarNumero($totais['horas']) ?></span> <span class="text-xs font-normal text-slate-500">R$</span></p>
                            <p class="mt-2 text-[11px] text-slate-500">Despesas da categoria HORAS EXTRAS</p>
                        </section>
                        <section class="rounded-xl border border-white/5 bg-[#191c29] p-5">
                            <p class="text-xs text-slate-400">Despesas <span class="float-right text-amber-300">▤</span></p>
                            <p class="mt-4 text-2xl font-semibold text-white"><span class="text-xs font-normal text-slate-500">R$</span> <span id="total-despesas"><?= formatarNumero($totais['despesas']) ?></span></p>
                            <p class="mt-2 text-[11px] text-slate-500">Despesas no período</p>
                        </section>
                        <section class="rounded-xl border border-white/5 bg-[#191c29] p-5">
                            <p class="text-xs text-slate-400">Diárias <span class="float-right text-emerald-300">▤</span></p>
                            <p class="mt-4 text-2xl font-semibold text-white"><span class="text-xs font-normal text-slate-500">R$</span> <span id="total-diarias"><?= formatarNumero($totais['diarias']) ?></span></p>
                            <p class="mt-2 text-[11px] text-slate-500">Despesas da categoria DIARIAS</p>
                        </section>
                    </div>

                    <p id="falha-graficos" hidden class="mb-5 rounded-xl bg-amber-400/10 p-4 text-amber-200" role="alert">Não foi possível carregar os gráficos. A tabela de produção e os indicadores continuam disponíveis.</p>

                    <section class="mb-6 rounded-xl border border-white/5 bg-[#191c29] p-5 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="font-semibold text-white">Produção da usina</h2>
                                <p class="mt-1 text-xs text-slate-500">Energia gerada · kWh</p>
                            </div>
                            <div class="flex flex-wrap gap-1 rounded-lg bg-[#121520] p-1" aria-label="Selecionar duração do período">
                                <?php foreach ([7 => '7D', 30 => '30D', 90 => '3M', 180 => '6M', 365 => '1A'] as $duracao => $rotulo): ?>
                                    <button type="button" data-dias="<?= $duracao ?>" aria-pressed="false" class="rounded-md px-3 py-1.5 text-[11px] text-slate-400 hover:bg-white/5 aria-pressed:bg-violet-500/20 aria-pressed:text-violet-300"><?= $rotulo ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div id="area-producao" tabindex="0" aria-label="Gráfico de produção. Use os botões de período ou as teclas mais e menos." class="relative mt-6 h-80 rounded-lg focus-visible:outline-2 focus-visible:outline-violet-400 sm:h-96">
                            <canvas id="grafico-energia" role="img" aria-label="Produção de energia da usina em kWh"></canvas>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-white/5 pt-4 text-[11px] text-slate-500">
                            <p>Selecione 7D, 30D, 3M, 6M ou 1A para ajustar o período dos gráficos.</p>
                            <span id="agrupamento"><?= $diasVisiveis > 62 ? 'Valores por mês' : 'Valores por dia' ?></span>
                        </div>
                    </section>

                    <section class="mb-6 rounded-xl border border-white/5 bg-[#191c29] p-5 sm:p-6">
                        <h2 class="font-semibold text-white">Produção de todas as usinas</h2>
                        <p class="mt-2 text-xs text-slate-400">Uma linha por usina · kWh · Mesmo período selecionado acima. Clique na legenda para ocultar ou exibir uma usina.</p>
                        <div class="relative mt-5 h-80 sm:h-96">
                            <canvas id="grafico-usinas" role="img" aria-label="Comparação da produção de energia de todas as usinas em linhas, em kWh"></canvas>
                        </div>
                    </section>

                    <section class="mb-6 rounded-xl border border-white/5 bg-[#191c29] p-5 sm:p-6">
                        <h2 class="font-semibold text-white">Horas extras e diárias</h2>
                        <p class="mt-2 text-xs text-slate-400">Linha: HORAS EXTRAS (eixo direito). Barras: DIARIAS (eixo esquerdo). Escalas independentes em reais.</p>
                        <div class="relative mt-5 h-80">
                            <canvas id="grafico-comparativo" role="img" aria-label="Horas extras em linha e diárias em barras, com eixos independentes em reais"></canvas>
                        </div>
                    </section>
                    <section class="mb-6 rounded-xl border border-white/5 bg-[#191c29] p-5 sm:p-6">
                        <h2 class="font-semibold text-white">Despesas totais</h2>
                        <p class="mt-2 text-xs text-slate-400">Todas as categorias, na mesma usina e no mesmo período dos demais gráficos.</p>
                        <div class="relative mt-5 h-80">
                            <canvas id="grafico-despesas" role="img" aria-label="Despesas totais em reais"></canvas>
                        </div>
                    </section>

                    <details class="rounded-xl border border-white/5 bg-[#191c29] p-5 sm:p-6">
                        <summary class="cursor-pointer text-sm font-medium text-violet-300">Consultar valores da tabela de produção de energia</summary>
                        <p class="mt-3 text-xs text-slate-500">Lançamentos da usina e do período exibidos nos gráficos.</p>
                        <div class="mt-5 max-h-[400px] overflow-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="sticky top-0 bg-[#222636] text-slate-400">
                                    <tr><th class="px-4 py-3">Registro</th><th class="px-4 py-3">Data</th><th class="px-4 py-3 text-right">Energia gerada (kWh)</th></tr>
                                </thead>
                                <tbody id="tabela-producao">
                                    <?php $quantidade = 0; ?>
                                    <?php foreach ($registrosProducao as $registro): ?>
                                        <?php if ($registro['data_producao'] >= $inicio): ?>
                                            <?php $quantidade++; ?>
                                            <tr class="border-b border-white/5">
                                                <td class="px-4 py-3 text-slate-500">#<?= (int) $registro['id'] ?></td>
                                                <td class="px-4 py-3"><?= formatarData($registro['data_producao']) ?></td>
                                                <td class="px-4 py-3 text-right"><?= formatarNumero($registro['kwh_gerado']) ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <?php if ($quantidade === 0): ?>
                                        <tr><td colspan="3" class="px-4 py-8 text-center text-slate-500">Nenhum lançamento de produção neste período.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>
                    <noscript><p class="mt-5 text-amber-200">Ative o JavaScript para os gráficos e os botões de período. Os filtros e a tabela funcionam sem JavaScript.</p></noscript>
                <?php endif; ?>

                <footer class="mt-8 flex justify-between text-[10px] text-slate-600"><span>GERENE · Gestão de energia</span><span>DashDark</span></footer>
            </main>
        </div>

        <script id="dados-dark" type="application/json"><?= json_encode($dados, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    </body>
</html>
