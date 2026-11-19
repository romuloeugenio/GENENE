<?php

require_once __DIR__ . '/../biblioteca.php';

exigirLogin();

if (!ehAdministrador()) {
    http_response_code(403);
    exit('Apenas administradores podem consultar a disponibilidade dos hosts.');
}

$titulo = 'Disponibilidade Zabbix';
$inicio = '2026-11-01';
$fim = '2026-11-19';
$nomeTriggerFiltro = 'MikroTik: Unavailable by ICMP ping';
$pesquisado = $_SERVER['REQUEST_METHOD'] === 'POST';
$erro = '';
$aviso = '';
$linhasDisponibilidade = [];
$quantidadeTriggersCorrespondentes = 0;
$quantidadeEventos = 0;
$apiUrlUsaHttp = false;
$fimPeriodoLimitadoAgora = false;
$fimPeriodoEfetivo = null;

if ($pesquisado) {
    validarToken();

    $inicio = lerCampo($_POST, 'inicio', $inicio);
    $fim = lerCampo($_POST, 'fim', $fim);

    try {
        if (!dataValida($inicio) || !dataValida($fim) || $inicio > $fim) {
            throw new InvalidArgumentException('Informe datas válidas e confira se a data inicial não é posterior à data final.');
        }

        $inicioPeriodo = new DateTimeImmutable($inicio . ' 00:00:00');
        $fimPeriodoExclusivo = (new DateTimeImmutable($fim . ' 00:00:00'))->modify('+1 day');
        $agora = new DateTimeImmutable('now');
        $inicioPeriodoTimestamp = $inicioPeriodo->getTimestamp();
        $fimPeriodoTimestamp = min($fimPeriodoExclusivo->getTimestamp(), $agora->getTimestamp());
        $fimPeriodoLimitadoAgora = $fimPeriodoTimestamp < $fimPeriodoExclusivo->getTimestamp();
        if ($inicioPeriodoTimestamp >= $fimPeriodoTimestamp) {
            throw new InvalidArgumentException('O período selecionado ainda não começou ou não contém tempo monitorado até o momento.');
        }
        $fimPeriodoEfetivo = (new DateTimeImmutable('@' . $fimPeriodoTimestamp))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()));

        $arquivoConfiguracao = dirname(__DIR__, 2) . '/zabbix.json';
        if (!is_file($arquivoConfiguracao)) {
            throw new RuntimeException('Arquivo zabbix.json não encontrado. Configure o usuário e a senha do Zabbix.');
        }

        $conteudoConfiguracao = file_get_contents($arquivoConfiguracao);
        if ($conteudoConfiguracao === false) {
            throw new RuntimeException('Não foi possível ler o arquivo zabbix.json.');
        }

        try {
            $configuracaoCredenciais = json_decode($conteudoConfiguracao, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $excecao) {
            throw new RuntimeException('O arquivo zabbix.json contém JSON inválido.', 0, $excecao);
        }

        if (
            !is_array($configuracaoCredenciais)
            || !isset($configuracaoCredenciais['username'], $configuracaoCredenciais['password'])
            || !is_string($configuracaoCredenciais['username'])
            || !is_string($configuracaoCredenciais['password'])
            || trim($configuracaoCredenciais['username']) === ''
            || $configuracaoCredenciais['password'] === ''
        ) {
            throw new RuntimeException('Configure username e password válidos no arquivo zabbix.json.');
        }

        $usuarioZabbix = trim($configuracaoCredenciais['username']);
        $senhaZabbix = $configuracaoCredenciais['password'];
        $arquivoCredenciais = dirname(__DIR__, 2) . '/zabbix.credentials.php';
        if (!is_file($arquivoCredenciais)) {
            throw new RuntimeException('Arquivo zabbix.credentials.php não encontrado.');
        }

        $configuracaoZabbix = require $arquivoCredenciais;
        if (
            !is_array($configuracaoZabbix)
            || !isset($configuracaoZabbix['api_url'])
            || !is_string($configuracaoZabbix['api_url'])
        ) {
            throw new RuntimeException('Configure api_url no arquivo zabbix.credentials.php.');
        }

        $apiUrl = trim($configuracaoZabbix['api_url']);
        $partesUrl = parse_url($apiUrl);
        if (
            filter_var($apiUrl, FILTER_VALIDATE_URL) === false
            || !is_array($partesUrl)
            || !isset($partesUrl['scheme'], $partesUrl['host'])
            || !in_array(strtolower($partesUrl['scheme']), ['http', 'https'], true)
        ) {
            throw new RuntimeException('A URL da API do Zabbix deve ser válida e usar HTTP ou HTTPS.');
        }
        $apiUrlUsaHttp = strtolower($partesUrl['scheme']) === 'http';

        $idRequisicao = 0;
        $chamarApi = static function (string $metodo, array $parametros, ?string $token = null) use ($apiUrl, &$idRequisicao) {
            $idRequisicao++;
            $cabecalhos = [
                'Accept: application/json',
                'Content-Type: application/json-rpc'
            ];
            if ($token !== null) {
                $cabecalhos[] = 'Authorization: Bearer ' . $token;
            }

            try {
                $corpoRequisicao = json_encode([
                    'jsonrpc' => '2.0',
                    'method' => $metodo,
                    'params' => $parametros,
                    'id' => $idRequisicao
                ], JSON_THROW_ON_ERROR);
            } catch (JsonException $excecao) {
                throw new RuntimeException('Não foi possível codificar a requisição para a API do Zabbix.', 0, $excecao);
            }

            $opcoes = [
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", $cabecalhos),
                    'content' => $corpoRequisicao,
                    'timeout' => 90,
                    'ignore_errors' => true,
                    'follow_location' => 0
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true
                ]
            ];
            $avisoTransporte = '';
            set_error_handler(static function ($severidade, $mensagem) use (&$avisoTransporte) {
                $avisoTransporte = $mensagem;
                return true;
            });
            try {
                $corpoResposta = file_get_contents($apiUrl, false, stream_context_create($opcoes));
            } finally {
                restore_error_handler();
            }
            if ($corpoResposta === false) {
                throw new RuntimeException(
                    'Falha ao conectar à API do Zabbix.'
                    . ($avisoTransporte !== '' ? ' ' . $avisoTransporte : '')
                );
            }

            $codigoHttp = 0;
            foreach ($http_response_header ?? [] as $cabecalhoResposta) {
                if (preg_match('/^HTTP\/\S+\s+([0-9]{3})/', $cabecalhoResposta, $partesStatus)) {
                    $codigoHttp = (int) $partesStatus[1];
                }
            }
            if ($codigoHttp < 200 || $codigoHttp >= 300) {
                throw new RuntimeException('A API do Zabbix respondeu HTTP ' . $codigoHttp . '.');
            }

            try {
                $resposta = json_decode($corpoResposta, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $excecao) {
                throw new RuntimeException('A API do Zabbix retornou uma resposta JSON inválida.', 0, $excecao);
            }

            if (
                !is_array($resposta)
                || ($resposta['jsonrpc'] ?? null) !== '2.0'
                || ($resposta['id'] ?? null) !== $idRequisicao
            ) {
                throw new RuntimeException('A resposta da API do Zabbix está em formato inesperado.');
            }
            if (isset($resposta['error'])) {
                $mensagem = is_array($resposta['error'])
                    ? ($resposta['error']['message'] ?? 'Erro JSON-RPC sem descrição.')
                    : 'Erro JSON-RPC sem descrição.';
                throw new RuntimeException('A API do Zabbix recusou a operação: ' . $mensagem);
            }

            return $resposta['result'] ?? null;
        };

        $tokenApi = null;
        try {
            $resultadoLogin = $chamarApi('user.login', [
                'username' => $usuarioZabbix,
                'password' => $senhaZabbix
            ]);
            if (!is_string($resultadoLogin) || $resultadoLogin === '') {
                throw new RuntimeException('O Zabbix não retornou um token de autenticação válido.');
            }
            $tokenApi = $resultadoLogin;
            $senhaZabbix = '';

            $resultadoTriggers = $chamarApi('trigger.get', [
                'output' => ['triggerid', 'description', 'status', 'value'],
                'search' => ['description' => $nomeTriggerFiltro],
                'searchWildcardsEnabled' => true,
                'selectHosts' => ['hostid', 'host', 'name'],
                'sortfield' => 'description',
                'sortorder' => 'ASC'
            ], $tokenApi);
            if (!is_array($resultadoTriggers)) {
                throw new RuntimeException('A API do Zabbix retornou os triggers em formato inesperado.');
            }

            $triggersPorId = [];
            $hosts = [];
            $hostsPorTrigger = [];
            foreach ($resultadoTriggers as $trigger) {
                if (
                    !is_array($trigger)
                    || !isset($trigger['triggerid'], $trigger['description'], $trigger['status'], $trigger['value'])
                ) {
                    throw new RuntimeException('A API do Zabbix retornou um trigger sem os campos necessários.');
                }
                if (
                    strcasecmp(trim((string) $trigger['description']), $nomeTriggerFiltro) !== 0
                    || (string) $trigger['status'] !== '0'
                ) {
                    continue;
                }

                $triggerId = (string) $trigger['triggerid'];
                $triggersPorId[$triggerId] = [
                    'valor_atual' => (string) $trigger['value']
                ];
                $hostsPorTrigger[$triggerId] = [];
                foreach ($trigger['hosts'] ?? [] as $host) {
                    if (!is_array($host) || !isset($host['hostid'])) {
                        throw new RuntimeException('A API do Zabbix retornou um host sem identificador.');
                    }
                    $hostId = (string) $host['hostid'];
                    $nomeHost = $host['name'] ?? $host['host'] ?? '';
                    if (!is_string($nomeHost) || $nomeHost === '') {
                        throw new RuntimeException('A API do Zabbix retornou um host sem nome.');
                    }

                    $hosts[$hostId] = $hosts[$hostId] ?? [
                        'host' => $nomeHost,
                        'triggers' => [],
                        'intervalos' => [],
                        'historico_incompleto' => false
                    ];
                    $hosts[$hostId]['triggers'][$triggerId] = true;
                    $hostsPorTrigger[$triggerId][] = $hostId;
                }
            }
            $quantidadeTriggersCorrespondentes = count($triggersPorId);

            if ($triggersPorId !== []) {
                $idsTriggers = array_keys($triggersPorId);
                $resultadoEventos = $chamarApi('event.get', [
                    'output' => ['eventid', 'objectid', 'clock', 'value'],
                    'source' => 0,
                    'object' => 0,
                    'objectids' => $idsTriggers,
                    'time_till' => $fimPeriodoTimestamp,
                    'sortfield' => ['clock', 'eventid'],
                    'sortorder' => 'ASC'
                ], $tokenApi);
                if (!is_array($resultadoEventos)) {
                    throw new RuntimeException('A API do Zabbix retornou os eventos em formato inesperado.');
                }
                $quantidadeEventos = count($resultadoEventos);

                $eventosPorTrigger = [];
                foreach ($resultadoEventos as $evento) {
                    if (
                        !is_array($evento)
                        || !isset($evento['eventid'], $evento['objectid'], $evento['clock'], $evento['value'])
                        || !is_numeric($evento['clock'])
                    ) {
                        throw new RuntimeException('A API do Zabbix retornou um evento com dados inválidos.');
                    }
                    $triggerId = (string) $evento['objectid'];
                    if (!isset($triggersPorId[$triggerId])) {
                        continue;
                    }
                    $valorEvento = (string) $evento['value'];
                    if ($valorEvento !== '0' && $valorEvento !== '1') {
                        throw new RuntimeException('A API do Zabbix retornou um valor de evento desconhecido.');
                    }
                    $eventosPorTrigger[$triggerId][] = [
                        'eventid' => (string) $evento['eventid'],
                        'clock' => (int) $evento['clock'],
                        'value' => $valorEvento
                    ];
                }

                $adicionarIntervalo = static function (array $hostIds, int $inicioEvento, int $fimEvento) use (
                    &$hosts,
                    $inicioPeriodoTimestamp,
                    $fimPeriodoTimestamp
                ) {
                    $inicioIntervalo = max($inicioEvento, $inicioPeriodoTimestamp);
                    $fimIntervalo = min($fimEvento, $fimPeriodoTimestamp);
                    if ($fimIntervalo <= $inicioIntervalo) {
                        return;
                    }
                    foreach ($hostIds as $hostId) {
                        $hosts[$hostId]['intervalos'][] = [$inicioIntervalo, $fimIntervalo];
                    }
                };

                $estadoFinalPorTrigger = [];
                foreach ($triggersPorId as $triggerId => $dadosTrigger) {
                    $eventos = $eventosPorTrigger[$triggerId] ?? [];
                    usort($eventos, static function ($a, $b) {
                        return ($a['clock'] <=> $b['clock'])
                            ?: (strlen($a['eventid']) <=> strlen($b['eventid']))
                            ?: strcmp($a['eventid'], $b['eventid']);
                    });

                    $estaIndisponivel = false;
                    $inicioIndisponibilidade = null;
                    $hostIds = $hostsPorTrigger[$triggerId];
                    foreach ($eventos as $evento) {
                        if ($evento['value'] === '1') {
                            if (!$estaIndisponivel) {
                                $estaIndisponivel = true;
                                $inicioIndisponibilidade = $evento['clock'];
                            }
                            continue;
                        }

                        if ($estaIndisponivel && $inicioIndisponibilidade !== null) {
                            $adicionarIntervalo($hostIds, $inicioIndisponibilidade, $evento['clock']);
                            $estaIndisponivel = false;
                            $inicioIndisponibilidade = null;
                        } elseif (
                            $evento['clock'] >= $inicioPeriodoTimestamp
                            && $evento['clock'] < $fimPeriodoTimestamp
                        ) {
                            foreach ($hostIds as $hostId) {
                                $hosts[$hostId]['historico_incompleto'] = true;
                            }
                        }
                    }

                    if ($estaIndisponivel && $inicioIndisponibilidade !== null) {
                        $adicionarIntervalo($hostIds, $inicioIndisponibilidade, $fimPeriodoTimestamp);
                    }
                    $estadoFinalPorTrigger[$triggerId] = $estaIndisponivel;
                }

                if ($fimPeriodoLimitadoAgora) {
                    $resultadoEstadosAtuais = $chamarApi('trigger.get', [
                        'output' => ['triggerid', 'value'],
                        'triggerids' => $idsTriggers
                    ], $tokenApi);
                    if (!is_array($resultadoEstadosAtuais)) {
                        throw new RuntimeException('A API do Zabbix retornou os estados atuais dos triggers em formato inesperado.');
                    }
                    foreach ($resultadoEstadosAtuais as $estadoAtual) {
                        if (
                            !is_array($estadoAtual)
                            || !isset($estadoAtual['triggerid'], $estadoAtual['value'])
                        ) {
                            throw new RuntimeException('A API do Zabbix retornou um estado atual de trigger inválido.');
                        }
                        $triggerId = (string) $estadoAtual['triggerid'];
                        $valorAtual = (string) $estadoAtual['value'];
                        if (
                            isset($estadoFinalPorTrigger[$triggerId])
                            && ($valorAtual === '0' || $valorAtual === '1')
                            && (int) $valorAtual !== (int) $estadoFinalPorTrigger[$triggerId]
                        ) {
                            foreach ($hostsPorTrigger[$triggerId] as $hostId) {
                                $hosts[$hostId]['historico_incompleto'] = true;
                            }
                        }
                    }
                }

                $duracaoPeriodo = $fimPeriodoTimestamp - $inicioPeriodoTimestamp;
                foreach ($hosts as $dadosHost) {
                    $disponibilidade = null;
                    $tempoOnline = null;
                    $tempoIndisponivel = null;
                    if (!$dadosHost['historico_incompleto']) {
                        $intervalos = $dadosHost['intervalos'];
                        usort($intervalos, static function ($a, $b) {
                            return ($a[0] <=> $b[0]) ?: ($a[1] <=> $b[1]);
                        });

                        $intervalosUnidos = [];
                        foreach ($intervalos as $intervalo) {
                            $ultimoIndice = count($intervalosUnidos) - 1;
                            if ($ultimoIndice < 0 || $intervalo[0] > $intervalosUnidos[$ultimoIndice][1]) {
                                $intervalosUnidos[] = $intervalo;
                            } else {
                                $intervalosUnidos[$ultimoIndice][1] = max(
                                    $intervalosUnidos[$ultimoIndice][1],
                                    $intervalo[1]
                                );
                            }
                        }

                        $tempoIndisponivel = 0;
                        foreach ($intervalosUnidos as $intervalo) {
                            $tempoIndisponivel += $intervalo[1] - $intervalo[0];
                        }
                        $tempoOnline = max(0, $duracaoPeriodo - $tempoIndisponivel);
                        $disponibilidade = ($tempoOnline / $duracaoPeriodo) * 100;
                    }

                    $linhasDisponibilidade[] = [
                        'host' => $dadosHost['host'],
                        'disponibilidade' => $disponibilidade,
                        'tempo_online' => $tempoOnline,
                        'tempo_indisponivel' => $tempoIndisponivel,
                        'quantidade_triggers' => count($dadosHost['triggers']),
                        'historico_incompleto' => $dadosHost['historico_incompleto']
                    ];
                }
            }
        } finally {
            if ($tokenApi !== null) {
                try {
                    $chamarApi('user.logout', [], $tokenApi);
                } catch (RuntimeException $excecaoLogout) {
                    $aviso = 'Não foi possível encerrar a sessão da API do Zabbix. Encerre-a pelo painel do Zabbix se necessário.';
                }
            }
            $senhaZabbix = '';
            unset($configuracaoCredenciais, $configuracaoZabbix);
        }

        usort($linhasDisponibilidade, static function ($a, $b) {
            return strnatcasecmp($a['host'], $b['host']);
        });
    } catch (InvalidArgumentException | RuntimeException $excecao) {
        $erro = $excecao->getMessage();
        unset($configuracaoCredenciais, $configuracaoZabbix, $senhaZabbix);
    }
}

$formatarDuracao = static function (?int $segundos) {
    if ($segundos === null) {
        return 'Histórico insuficiente';
    }
    $dias = intdiv($segundos, 86400);
    $restante = $segundos % 86400;
    $horas = intdiv($restante, 3600);
    $minutos = intdiv($restante % 3600, 60);
    $segundosRestantes = $restante % 60;

    return ($dias > 0 ? $dias . ' d ' : '')
        . sprintf('%02d:%02d:%02d', $horas, $minutos, $segundosRestantes);
};

$linhasComDisponibilidade = array_values(array_filter($linhasDisponibilidade, static function ($linha) {
    return $linha['disponibilidade'] !== null;
}));
$quantidadeClientes = count($linhasDisponibilidade);
$quantidadeLinksCalculaveis = count($linhasComDisponibilidade);
$quantidadeLinksAbaixo99 = count(array_filter($linhasComDisponibilidade, static function ($linha) {
    return $linha['disponibilidade'] < 99;
}));
$percentualLinksAbaixo99 = $quantidadeLinksCalculaveis > 0
    ? number_format(($quantidadeLinksAbaixo99 / $quantidadeLinksCalculaveis) * 100, 2, ',', '.') . '%'
    : '—';

$linksParaGrafico = array_values(array_filter($linhasComDisponibilidade, static function ($linha) {
    return $linha['disponibilidade'] > 30 && $linha['disponibilidade'] < 96;
}));
usort($linksParaGrafico, static function ($a, $b) {
    return ($a['disponibilidade'] <=> $b['disponibilidade'])
        ?: strnatcasecmp($a['host'], $b['host']);
});
$quantidadeLinksElegiveisGrafico = count($linksParaGrafico);
$dadosGrafico = array_slice($linksParaGrafico, 0, 40);
$carregarBibliotecaGraficos = $dadosGrafico !== [];

require __DIR__ . '/../views/header.php';

?>
            <section class="painel">
                <details class="zabbix-observacao">
                    <summary class="botao secundario pequeno">Observação</summary>
                    <p>A disponibilidade é calculada a partir dos eventos de problema e recuperação do trigger <strong><?= escapar($nomeTriggerFiltro) ?></strong>. Quando mais de um trigger corresponde ao mesmo host, os intervalos de indisponibilidade são unidos para evitar dupla contagem.</p>
                    <p>O cálculo considera o tempo corrido do período, sem excluir janelas de manutenção ou horários programados. Se a data final for hoje ou futura, o período termina no momento da consulta.</p>
                    <?php if ($apiUrlUsaHttp): ?>
                        <div class="aviso" role="note">A URL configurada para o Zabbix usa HTTP sem criptografia. Use HTTPS sempre que possível.</div>
                    <?php endif; ?>
                </details>

                <?php if ($erro !== ''): ?>
                    <div class="aviso erro" role="alert"><?= escapar($erro) ?></div>
                <?php endif; ?>

                <?php if ($aviso !== ''): ?>
                    <div class="aviso" role="status"><?= escapar($aviso) ?></div>
                <?php endif; ?>

                <form method="post" class="filtro-periodo">
                    <input type="hidden" name="token" value="<?= escapar(obterToken()) ?>">
                    <div class="filtro-periodo__campo">
                        <label for="inicio">Data inicial</label>
                        <input type="date" id="inicio" name="inicio" value="<?= escapar($inicio) ?>" required>
                    </div>
                    <div class="filtro-periodo__campo">
                        <label for="fim">Data final</label>
                        <input type="date" id="fim" name="fim" value="<?= escapar($fim) ?>" required>
                    </div>
                    <button type="submit" class="botao">Consultar disponibilidade</button>
                </form>
            </section>

            <?php if ($pesquisado && $erro === ''): ?>
                <?php if ($quantidadeTriggersCorrespondentes === 0): ?>
                    <section class="painel">
                        <p>Nenhum trigger habilitado com esse nome foi encontrado. Confirme se a conta do Zabbix tem permissão para consultar os triggers.</p>
                    </section>
                <?php elseif ($linhasDisponibilidade === []): ?>
                    <section class="painel">
                        <p>Os triggers foram encontrados, mas nenhum host associado pôde ser consultado.</p>
                    </section>
                <?php else: ?>
                    <section class="painel">
                        <h2>Resumo de disponibilidade — <?= escapar(formatarData($inicio)) ?> a <?= escapar(formatarData($fim)) ?></h2>
                        <div class="zabbix-painel-kpis">
                            <article class="zabbix-kpi zabbix-kpi--clientes">
                                <h3>CLIENTES</h3>
                                <p class="zabbix-kpi__valor"><?= escapar((string) $quantidadeClientes) ?></p>
                                <p class="zabbix-kpi__descricao">Hosts associados aos triggers consultados</p>
                            </article>
                            <article class="zabbix-kpi zabbix-kpi--abaixo">
                                <h3>Links abaixo de 99%</h3>
                                <p class="zabbix-kpi__valor"><?= escapar($percentualLinksAbaixo99) ?></p>
                                <p class="zabbix-kpi__descricao">
                                    <?= escapar((string) $quantidadeLinksAbaixo99) ?> de
                                    <?= escapar((string) $quantidadeLinksCalculaveis) ?> links com disponibilidade calculada
                                </p>
                            </article>
                            <article class="zabbix-kpi zabbix-kpi--meta">
                                <h3>Meta de disponibilidade</h3>
                                <p class="zabbix-kpi__valor">KPI ≥ 99%</p>
                                <p class="zabbix-kpi__descricao">Disponibilidade mínima esperada por link</p>
                            </article>
                        </div>
                        <?php if ($quantidadeLinksCalculaveis < $quantidadeClientes): ?>
                            <p class="aviso" role="note">
                                <?= escapar((string) ($quantidadeClientes - $quantidadeLinksCalculaveis)) ?>
                                host(s) com histórico insuficiente não entram nos percentuais dos links.
                            </p>
                        <?php endif; ?>
                    </section>

                    <section class="painel">
                        <h2>Até 40 links com disponibilidade abaixo de 96%</h2>
                        <p>Exibidos em ordem da menor disponibilidade. Para este gráfico, links entre 0% e 30% são excluídos; essa exclusão não afeta os KPIs nem a tabela.</p>
                        <?php if ($dadosGrafico === []): ?>
                            <p>Nenhum link atende ao filtro do gráfico (acima de 30% e abaixo de 96%).</p>
                        <?php else: ?>
                            <div class="zabbix-grafico-disponibilidade">
                                <canvas id="grafico-disponibilidade-zabbix" role="img" aria-label="Disponibilidade dos primeiros 40 links entre 30% e 96%"></canvas>
                            </div>
                            <p id="erro-grafico-disponibilidade-zabbix" class="aviso erro" role="alert" hidden>Não foi possível carregar o gráfico. Consulte os valores na tabela abaixo.</p>
                        <?php endif; ?>
                    </section>

                    <section class="painel">
                        <h2>Disponibilidade por host</h2>
                        <div class="zabbix-eventos-resumo">
                            <button
                                type="button"
                                id="alternar-quantidade-eventos"
                                class="botao secundario pequeno"
                                aria-expanded="false"
                                aria-controls="quantidade-eventos-zabbix"
                            >Visualizar quantidade de eventos</button>
                            <p class="aviso zabbix-nota-periodo" role="note">
                                <?php if ($fimPeriodoLimitadoAgora && $fimPeriodoEfetivo instanceof DateTimeImmutable): ?>
                                    O período foi limitado ao momento da consulta: <?= escapar($fimPeriodoEfetivo->format('d/m/Y H:i:s')) ?>.
                                <?php else: ?>
                                    Período consultado: <?= escapar(formatarData($inicio)) ?> a <?= escapar(formatarData($fim)) ?>.
                                <?php endif; ?>
                                Links elegíveis para o gráfico: <?= escapar((string) $quantidadeLinksElegiveisGrafico) ?> (máximo de 40 exibidos).
                            </p>
                        </div>
                        <div class="acoes">
                            <button
                                type="button"
                                id="alternar-tabela-disponibilidade"
                                class="link-tabela-zabbix"
                                aria-expanded="false"
                                aria-controls="tabela-disponibilidade-zabbix"
                            >Consulta valores da tabela de disponibilidade</button>
                        </div>
                        <p id="quantidade-eventos-zabbix" class="aviso" role="status" hidden>
                            Foram considerados <?= escapar((string) $quantidadeTriggersCorrespondentes) ?> triggers e
                            <?= escapar((string) $quantidadeEventos) ?> eventos disponíveis até o fim do período.
                        </p>
                        <div id="tabela-disponibilidade-zabbix" hidden>
                            <div class="tabela-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Host</th>
                                            <th>Disponibilidade</th>
                                            <th>Tempo online</th>
                                            <th>Tempo indisponível</th>
                                            <th>Triggers considerados</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($linhasDisponibilidade as $linha): ?>
                                            <tr>
                                                <td><?= escapar($linha['host']) ?></td>
                                                <td>
                                                    <?= $linha['disponibilidade'] === null
                                                        ? 'Histórico insuficiente'
                                                        : escapar(number_format($linha['disponibilidade'], 2, ',', '.') . '%') ?>
                                                </td>
                                                <td><?= escapar($formatarDuracao($linha['tempo_online'])) ?></td>
                                                <td><?= escapar($formatarDuracao($linha['tempo_indisponivel'])) ?></td>
                                                <td><?= escapar((string) $linha['quantidade_triggers']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="aviso" role="note">“Histórico insuficiente” indica que houve uma recuperação sem o respectivo início registrado no período disponível, ou que o estado do trigger não coincidiu com o último evento consultado. O percentual desse host não é estimado.</p>
                        </div>
                    </section>

                    <script>
                        (function () {
                            const eventToggle = document.getElementById('alternar-quantidade-eventos');
                            const eventCount = document.getElementById('quantidade-eventos-zabbix');
                            if (eventToggle && eventCount) {
                                eventToggle.addEventListener('click', function () {
                                    eventCount.hidden = !eventCount.hidden;
                                    eventToggle.setAttribute('aria-expanded', String(!eventCount.hidden));
                                    eventToggle.textContent = eventCount.hidden
                                        ? 'Visualizar quantidade de eventos'
                                        : 'Ocultar quantidade de eventos';
                                });
                            }

                            const toggle = document.getElementById('alternar-tabela-disponibilidade');
                            const table = document.getElementById('tabela-disponibilidade-zabbix');
                            if (toggle && table) {
                                toggle.addEventListener('click', function () {
                                    table.hidden = !table.hidden;
                                    toggle.setAttribute('aria-expanded', String(!table.hidden));
                                    toggle.textContent = table.hidden
                                        ? 'Consulta valores da tabela de disponibilidade'
                                        : 'Ocultar valores da tabela de disponibilidade';
                                });
                            }

                            const canvas = document.getElementById('grafico-disponibilidade-zabbix');
                            if (!canvas) {
                                return;
                            }
                            const dados = <?= json_encode([
                                'hosts' => array_column($dadosGrafico, 'host'),
                                'disponibilidades' => array_map(
                                    static function ($linha) {
                                        return (float) $linha['disponibilidade'];
                                    },
                                    $dadosGrafico
                                )
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
                            const alturaGrafico = Math.max(360, dados.hosts.length * 28 + 80);
                            canvas.parentElement.style.height = alturaGrafico + 'px';

                            const fonteChart = window.glpiChartReady || Promise.resolve(window.Chart);
                            fonteChart.then(function (ChartBiblioteca) {
                                let gradienteLaranja = null;
                                let areaGradienteLaranja = '';
                                new ChartBiblioteca(canvas, {
                                    type: 'bar',
                                    data: {
                                        labels: dados.hosts,
                                        datasets: [{
                                            label: 'Disponibilidade',
                                            data: dados.disponibilidades,
                                            backgroundColor: function (contexto) {
                                                const grafico = contexto.chart;
                                                const area = grafico.chartArea;
                                                if (!area) {
                                                    return '#f97316';
                                                }
                                                const identificadorArea = [
                                                    area.left,
                                                    area.right,
                                                    area.top,
                                                    area.bottom
                                                ].join(':');
                                                if (identificadorArea !== areaGradienteLaranja) {
                                                    gradienteLaranja = grafico.ctx.createLinearGradient(area.left, 0, area.right, 0);
                                                    gradienteLaranja.addColorStop(0, '#fed7aa');
                                                    gradienteLaranja.addColorStop(0.55, '#fb923c');
                                                    gradienteLaranja.addColorStop(1, '#ea580c');
                                                    areaGradienteLaranja = identificadorArea;
                                                }
                                                return gradienteLaranja;
                                            },
                                            borderColor: '#c2410c',
                                            borderWidth: 1,
                                            borderRadius: 4
                                        }]
                                    },
                                    options: {
                                        indexAxis: 'y',
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: { display: false },
                                            tooltip: {
                                                callbacks: {
                                                    label: function (contexto) {
                                                        return 'Disponibilidade: '
                                                            + Number(contexto.raw).toLocaleString('pt-BR', {
                                                                minimumFractionDigits: 2,
                                                                maximumFractionDigits: 2
                                                            }) + '%';
                                                    }
                                                }
                                            }
                                        },
                                        scales: {
                                            x: {
                                                min: 0,
                                                max: 100,
                                                title: { display: true, text: 'Disponibilidade (%)' },
                                                ticks: {
                                                    callback: function (valor) {
                                                        return valor + '%';
                                                    }
                                                }
                                            },
                                            y: {
                                                ticks: { autoSkip: false }
                                            }
                                        }
                                    }
                                });
                            }).catch(function () {
                                const erroGrafico = document.getElementById('erro-grafico-disponibilidade-zabbix');
                                if (erroGrafico) {
                                    erroGrafico.hidden = false;
                                }
                            });
                        })();
                    </script>
                <?php endif; ?>
            <?php endif; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
