<?php

require_once __DIR__ . '/../biblioteca.php';

exigirLogin();

$titulo = 'Problemas Abertos no Zabbix';
$erro = '';
$aviso = '';
$problemas = [];
$pesquisado = false;
$inicio = date('Y-m-01');
$fim = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarToken();

    $inicio = lerCampo($_POST, 'inicio', $inicio);
    $fim = lerCampo($_POST, 'fim', $fim);
    $pesquisado = true;

    try {
        if (!dataValida($inicio) || !dataValida($fim) || $inicio > $fim) {
            throw new InvalidArgumentException('Informe datas válidas e confira se a data inicial não é posterior à data final.');
        }
        if (!extension_loaded('curl')) {
            throw new RuntimeException('A extensão cURL do PHP não está habilitada.');
        }

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

        $idRequisicao = 0;
        $chamarApi = static function (string $metodo, $parametros, ?string $token = null) use ($apiUrl, &$idRequisicao) {
            $idRequisicao++;
            $cabecalhos = [
                'Accept: application/json',
                'Content-Type: application/json-rpc'
            ];
            if ($token !== null) {
                $cabecalhos[] = 'Authorization: Bearer ' . $token;
            }

            $curl = curl_init($apiUrl);
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'jsonrpc' => '2.0',
                    'method' => $metodo,
                    'params' => $parametros,
                    'id' => $idRequisicao
                ], JSON_THROW_ON_ERROR),
                CURLOPT_HTTPHEADER => $cabecalhos,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);

            $corpoResposta = curl_exec($curl);
            $erroCurl = curl_error($curl);
            $codigoHttp = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($corpoResposta === false) {
                throw new RuntimeException('Falha ao conectar à API do Zabbix: ' . $erroCurl);
            }
            if ($codigoHttp < 200 || $codigoHttp >= 300) {
                throw new RuntimeException('A API do Zabbix respondeu HTTP ' . $codigoHttp . '.');
            }

            try {
                $resposta = json_decode($corpoResposta, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $excecao) {
                throw new RuntimeException('A API do Zabbix retornou uma resposta JSON inválida.');
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
        $erroLogout = '';
        try {
            $resultadoLogin = $chamarApi('user.login', [
                'username' => $usuarioZabbix,
                'password' => $senhaZabbix
            ]);
            if (!is_string($resultadoLogin) || $resultadoLogin === '') {
                throw new RuntimeException('O Zabbix não retornou um token de autenticação válido.');
            }
            $tokenApi = $resultadoLogin;

            $dataInicial = new DateTimeImmutable($inicio . ' 00:00:00');
            $dataFinal = new DateTimeImmutable($fim . ' 23:59:59');
            $resultadoProblemas = $chamarApi('problem.get', [
                'output' => ['eventid', 'name', 'clock', 'severity', 'acknowledged', 'objectid'],
                'time_from' => $dataInicial->getTimestamp(),
                'time_till' => $dataFinal->getTimestamp(),
                'sortfield' => 'eventid',
                'sortorder' => 'DESC'
            ], $tokenApi);

            if (!is_array($resultadoProblemas)) {
                throw new RuntimeException('A API do Zabbix retornou a lista de problemas em formato inesperado.');
            }

            $idsTriggers = array_values(array_unique(array_column($resultadoProblemas, 'objectid')));
            $nomesHostsPorTrigger = [];
            if ($idsTriggers !== []) {
                $resultadoTriggers = $chamarApi('trigger.get', [
                    'output' => ['triggerid'],
                    'triggerids' => $idsTriggers,
                    'selectHosts' => ['host', 'name']
                ], $tokenApi);

                if (!is_array($resultadoTriggers)) {
                    throw new RuntimeException('A API do Zabbix retornou os hosts em formato inesperado.');
                }

                foreach ($resultadoTriggers as $trigger) {
                    if (!is_array($trigger) || !isset($trigger['triggerid'])) {
                        throw new RuntimeException('A API do Zabbix retornou um trigger sem identificador.');
                    }

                    $nomesHostsPorTrigger[(string) $trigger['triggerid']] = [];
                    foreach ($trigger['hosts'] ?? [] as $host) {
                        if (!is_array($host)) {
                            continue;
                        }

                        $nomeHost = $host['name'] ?? $host['host'] ?? '';
                        if (is_string($nomeHost) && $nomeHost !== '') {
                            $nomesHostsPorTrigger[(string) $trigger['triggerid']][] = $nomeHost;
                        }
                    }
                }
            }

            foreach ($resultadoProblemas as $problema) {
                if (
                    !is_array($problema)
                    || !isset($problema['eventid'], $problema['name'], $problema['clock'])
                    || !is_numeric($problema['clock'])
                ) {
                    throw new RuntimeException('A API do Zabbix retornou um problema com dados inválidos.');
                }

                $problema['_hostNames'] = $nomesHostsPorTrigger[(string) ($problema['objectid'] ?? '')] ?? [];
                $problemas[] = $problema;
            }
        } finally {
            if ($tokenApi !== null) {
                try {
                    $chamarApi('user.logout', [], $tokenApi);
                } catch (Throwable $excecaoLogout) {
                    $erroLogout = 'Não foi possível encerrar a sessão da API do Zabbix. Encerre-a pelo painel do Zabbix se necessário.';
                }
            }
        }

        $senhaZabbix = '';
        unset($configuracaoCredenciais);
        if ($erroLogout !== '') {
            $aviso = $erroLogout;
        }
    } catch (Throwable $excecao) {
        unset($senhaZabbix, $configuracaoCredenciais);
        $erro = $excecao->getMessage();
    }
}

require __DIR__ . '/../views/header.php';

?>
            <section class="painel">
                <div class="aviso" role="note">A URL configurada para o Zabbix usa HTTP. Usuário e senha trafegam sem criptografia entre o servidor do GERENE e o Zabbix. Use somente em uma rede confiável; HTTPS é recomendado.</div>

                <?php if ($erro !== ''): ?>
                    <div class="aviso erro" role="alert"><?= escapar($erro) ?></div>
                <?php endif; ?>

                <?php if ($aviso !== ''): ?>
                    <div class="aviso" role="status"><?= escapar($aviso) ?></div>
                <?php endif; ?>

                <p>Consulte os problemas ainda não resolvidos cuja ocorrência esteja entre as datas selecionadas. O usuário e a senha configurados em zabbix.json são usados somente nesta consulta.</p>

                <form method="post" class="filtro-periodo">
                    <input type="hidden" name="token" value="<?= escapar(obterToken()) ?>">
                    <div>
                        <label for="inicio">Data inicial</label>
                        <input type="date" id="inicio" name="inicio" value="<?= escapar($inicio) ?>" required>
                    </div>
                    <div>
                        <label for="fim">Data final</label>
                        <input type="date" id="fim" name="fim" value="<?= escapar($fim) ?>" required>
                    </div>
                    <button type="submit" class="botao">Consultar problemas</button>
                </form>

                <?php if ($pesquisado && $erro === ''): ?>
                    <h2>Problemas abertos de <?= escapar(formatarData($inicio)) ?> a <?= escapar(formatarData($fim)) ?></h2>
                    <?php if ($problemas === []): ?>
                        <p>Nenhum problema aberto encontrado no período.</p>
                    <?php else: ?>
                        <div class="tabela-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Data e hora</th>
                                        <th>Host</th>
                                        <th>Problema</th>
                                        <th>Severidade</th>
                                        <th>Reconhecido</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($problemas as $problema): ?>
                                        <?php
                                        $severidades = [
                                            '0' => 'Não classificado',
                                            '1' => 'Informação',
                                            '2' => 'Aviso',
                                            '3' => 'Média',
                                            '4' => 'Alta',
                                            '5' => 'Desastre'
                                        ];
                                        $severidade = $severidades[(string) ($problema['severity'] ?? '')] ?? 'Desconhecida';
                                        ?>
                                        <tr>
                                            <td><?= escapar(date('d/m/Y H:i:s', (int) $problema['clock'])) ?></td>
                                            <td><?= escapar($problema['_hostNames'] === [] ? 'Host não informado' : implode(', ', $problema['_hostNames'])) ?></td>
                                            <td><?= escapar($problema['name']) ?></td>
                                            <td><?= escapar($severidade) ?></td>
                                            <td><?= ($problema['acknowledged'] ?? '0') === '1' ? 'Sim' : 'Não' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
<?php require __DIR__ . '/../views/footer.php'; ?>
