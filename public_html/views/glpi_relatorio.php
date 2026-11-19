<?php

require_once __DIR__ . '/../biblioteca.php';

exigirLogin();

$LimiteParaMostrar = 0.16;
$titulo = 'KPI DO GLPI';
$carregarBibliotecaGraficos = true;
$meses = [
    '01' => 'Janeiro',
    '02' => 'Fevereiro',
    '03' => 'Março',
    '04' => 'Abril',
    '05' => 'Maio',
    '06' => 'Junho',
    '07' => 'Julho',
    '08' => 'Agosto',
    '09' => 'Setembro',
    '10' => 'Outubro',
    '11' => 'Novembro',
    '12' => 'Dezembro'
];
$relatorio = [];
$resumoMtfr = [];
$periodosRelatorio = [];
$agora = new DateTimeImmutable('now');
$fimPeriodoPadrao = $agora->setTime((int) $agora->format('H'), (int) $agora->format('i'), 0);
$agoraIso = $fimPeriodoPadrao->format('Y-m-d\TH:i');
$periodosRapidos = [
    '7d' => ['rotulo' => '7D', 'intervalo' => 'P7D'],
    '30d' => ['rotulo' => '30D', 'intervalo' => 'P30D'],
    '3m' => ['rotulo' => '3M', 'intervalo' => 'P3M'],
    '6m' => ['rotulo' => '6M', 'intervalo' => 'P6M'],
    '1a' => ['rotulo' => '1A', 'intervalo' => 'P1Y']
];
$periodoSelecionado = lerCampo($_GET, 'periodo');
$dataInicialReparo = lerCampo($_GET, 'inicio', $fimPeriodoPadrao->sub(new DateInterval('P1Y'))->format('Y-m-d\TH:i'));
$dataFinalReparo = lerCampo($_GET, 'fim', $agoraIso);
if (isset($periodosRapidos[$periodoSelecionado])) {
    $dataFinalReparo = $agoraIso;
    $dataInicialReparo = $fimPeriodoPadrao
        ->sub(new DateInterval($periodosRapidos[$periodoSelecionado]['intervalo']))
        ->format('Y-m-d\TH:i');
}
$converterDataHora = static function ($valor) {
    if (!is_string($valor)) {
        return null;
    }

    $data = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $valor);
    $erros = DateTimeImmutable::getLastErrors();

    return $data !== false
        && ($erros === false || ($erros['warning_count'] === 0 && $erros['error_count'] === 0))
        && $data->format('Y-m-d\TH:i') === $valor
        ? $data
        : null;
};
$inicioPeriodoReparo = null;
$fimPeriodoReparo = null;
$agrupamentoReparo = 'mes';
$ordensReparo = [];
$reparosSemInicio = 0;
$reparosInvalidos = 0;
$reparosAbaixoLimite = 0;
$dadosGraficoReparo = ['rotulos' => [], 'medias' => []];
$dadosGraficoSla = ['rotulos' => [], 'medias' => []];
$dadosGraficoMtfr = ['rotulos' => [], 'medias' => []];
$dadosGraficoIndicadores = ['rotulos' => [], 'sla' => [], 'mtfr' => [], 'mttr' => []];
$chamadosMtfr = [];
$tratativasSemInicioMtfr = 0;
$tratativasInvalidasMtfr = 0;
$tratativasAbaixoLimiteMtfr = 0;
$slasAbaixoLimite = 0;
$erroReparo = '';
$inicioPeriodoReparo = $converterDataHora($dataInicialReparo);
$fimDataReparo = $converterDataHora($dataFinalReparo);
if ($inicioPeriodoReparo === null || $fimDataReparo === null || $inicioPeriodoReparo > $fimDataReparo) {
    $erroReparo = 'Informe datas e horários válidos e confira se o início não é posterior ao fim.';
} elseif ($fimDataReparo > $agora) {
    $erroReparo = 'A data e hora final não podem ser posteriores ao momento atual.';
} else {
    $fimPeriodoReparo = $fimDataReparo->add(new DateInterval('PT1M'));
    $duracaoDiasReparo = (int) $inicioPeriodoReparo->diff($fimDataReparo)->days;
    $agrupamentoReparo = $duracaoDiasReparo <= 31 ? 'dia' : ($duracaoDiasReparo <= 93 ? 'semana' : 'mes');

    $mesPeriodo = new DateTimeImmutable($inicioPeriodoReparo->format('Y-m-01'));
    $ultimoMesPeriodo = new DateTimeImmutable($fimDataReparo->format('Y-m-01'));
    while ($mesPeriodo <= $ultimoMesPeriodo) {
        $chaveMes = $mesPeriodo->format('Y-m');
        $periodosRelatorio[$chaveMes] = $meses[$mesPeriodo->format('m')] . ' ' . $mesPeriodo->format('Y');
        $relatorio[$chaveMes] = ['quantidade' => 0, 'soma_horas' => 0, 'solucionados' => 0, 'media_horas' => null];
        $resumoMtfr[$chaveMes] = ['soma_horas' => 0, 'quantidade' => 0, 'media_horas' => null];
        $mesPeriodo = $mesPeriodo->add(new DateInterval('P1M'));
    }
}
$erro = '';
$arquivoCredenciais = dirname(__DIR__, 2) . '/glpi.credentials.php';
$ehLista = static function (array $valores) {
    return $valores === [] || array_keys($valores) === range(0, count($valores) - 1);
};
$obterTextoCampo = static function ($valor) use (&$obterTextoCampo) {
    if (is_scalar($valor)) {
        return trim((string) $valor);
    }

    if (is_array($valor)) {
        foreach (['completename', 'name', 'realname', 'username', 'id'] as $campo) {
            if (isset($valor[$campo])) {
                $texto = $obterTextoCampo($valor[$campo]);
                if ($texto !== '') {
                    return $texto;
                }
            }
        }

        foreach ($valor as $item) {
            $texto = $obterTextoCampo($item);
            if ($texto !== '') {
                return $texto;
            }
        }
    }

    return '';
};
try {
    if ($erroReparo !== '') {
        throw new RuntimeException($erroReparo);
    }

    if (!is_file($arquivoCredenciais)) {
        throw new RuntimeException('Arquivo local de credenciais do GLPI não encontrado.');
    }

    $credenciais = require $arquivoCredenciais;
    if (!is_array($credenciais)) {
        throw new RuntimeException('O arquivo local de credenciais do GLPI está inválido.');
    }

    foreach (['api_url', 'glpi_client_id', 'glpi_client_secret', 'glpi_username', 'glpi_password'] as $campo) {
        if (!isset($credenciais[$campo]) || !is_string($credenciais[$campo]) || trim($credenciais[$campo]) === '') {
            throw new RuntimeException('Preencha api_url, glpi_client_id, glpi_client_secret, glpi_username e glpi_password no arquivo glpi.credentials.php.');
        }
    }

    $apiUrl = rtrim($credenciais['api_url'], '/');
    if (!in_array(parse_url($apiUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
        throw new RuntimeException('A URL configurada deve usar HTTP ou HTTPS.');
    }

    $executarRequisicao = static function ($url, $metodo, array $cabecalhos, $corpo = null) {
        $opcoes = [
            'http' => [
                'method' => $metodo,
                'header' => implode("\r\n", $cabecalhos),
                'timeout' => 45,
                'ignore_errors' => true,
                'follow_location' => 0
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];
        if ($corpo !== null) {
            $opcoes['http']['content'] = $corpo;
        }

        $aviso = '';
        set_error_handler(static function ($severidade, $mensagem) use (&$aviso) {
            $aviso = $mensagem;
            return true;
        });
        try {
            $resposta = file_get_contents($url, false, stream_context_create($opcoes));
        } finally {
            restore_error_handler();
        }

        if ($resposta === false) {
            throw new RuntimeException('Falha de conexão com o GLPI: ' . ($aviso !== '' ? $aviso : 'erro de transporte não identificado.'));
        }

        $status = 0;
        foreach ($http_response_header as $cabecalho) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $cabecalho, $correspondencia)) {
                $status = (int) $correspondencia[1];
            }
        }

        $dados = json_decode($resposta, true);
        if (!is_array($dados)) {
            throw new RuntimeException('O GLPI retornou uma resposta JSON inválida (HTTP ' . $status . ').');
        }

        return ['status' => $status, 'dados' => $dados];
    };

    $respostaToken = $executarRequisicao(
        $apiUrl . '/token',
        'POST',
        ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
        http_build_query([
            'grant_type' => 'password',
            'client_id' => $credenciais['glpi_client_id'],
            'client_secret' => $credenciais['glpi_client_secret'],
            'username' => $credenciais['glpi_username'],
            'password' => $credenciais['glpi_password'],
            'scope' => 'api'
        ])
    );
    if ($respostaToken['status'] < 200 || $respostaToken['status'] >= 300) {
        throw new RuntimeException('O GLPI recusou a autenticação OAuth (HTTP ' . $respostaToken['status'] . '). Confira o cliente OAuth, usuário, senha e escopo "api".');
    }

    $tokenAcesso = $respostaToken['dados']['access_token'] ?? '';
    if (!is_string($tokenAcesso) || $tokenAcesso === '') {
        throw new RuntimeException('O GLPI não retornou um token OAuth válido.');
    }

    $parametrosCategorias = http_build_query(['start' => 0, 'limit' => 1000]);
    $paginaCategorias = $executarRequisicao(
        $apiUrl . '/v2.3/Dropdowns/ITILCategory?' . $parametrosCategorias,
        'GET',
        [
            'Accept: application/json',
            'Authorization: Bearer ' . $tokenAcesso
        ]
    );
    if ($paginaCategorias['status'] < 200 || $paginaCategorias['status'] >= 300) {
        throw new RuntimeException('A API do GLPI respondeu com HTTP ' . $paginaCategorias['status'] . ' ao consultar as categorias.');
    }

    $categorias = $paginaCategorias['dados'];
    if (!$ehLista($categorias) && isset($categorias['data']) && is_array($categorias['data'])) {
        $categorias = $categorias['data'];
    }
    if (!$ehLista($categorias)) {
        throw new RuntimeException('A resposta de categorias do GLPI está em formato inesperado.');
    }

    $categoriaAlvoId = null;
    foreach ($categorias as $categoria) {
        if (!is_array($categoria)) {
            continue;
        }

        $caminhoCategoria = preg_replace('/\s+/', ' ', trim($obterTextoCampo($categoria)));
        if (
            !is_string($caminhoCategoria)
            || strcasecmp($caminhoCategoria, 'clientes > offline') !== 0
        ) {
            continue;
        }

        $idCategoria = filter_var($categoria['id'] ?? null, FILTER_VALIDATE_INT);
        if ($idCategoria === false || $idCategoria < 1) {
            throw new RuntimeException('A categoria Clientes > Offline não possui um ID válido no GLPI.');
        }
        if ($categoriaAlvoId !== null && $categoriaAlvoId !== $idCategoria) {
            throw new RuntimeException('Há mais de uma categoria com o caminho Clientes > Offline; não é possível determinar qual usar.');
        }
        $categoriaAlvoId = $idCategoria;
    }
    if ($categoriaAlvoId === null) {
        throw new RuntimeException('A categoria Clientes > Offline não foi encontrada no GLPI.');
    }

    $ehCategoriaRelatorio = static function (array $chamado) use ($categoriaAlvoId) {
        $idCategoria = $chamado['category']['id'] ?? null;

        return is_scalar($idCategoria) && (string) $idCategoria === (string) $categoriaAlvoId;
    };

    $inicio = 0;
    $limite = 100;
    $filtroPeriodo = 'date=ge=' . $inicioPeriodoReparo->format('Y-m-d H:i:s')
        . ';date=lt=' . $fimPeriodoReparo->format('Y-m-d H:i:s');
    do {
        $parametros = http_build_query([
            'filter' => $filtroPeriodo,
            'start' => $inicio,
            'limit' => $limite
        ]);
        $pagina = $executarRequisicao(
            $apiUrl . '/v2.3/Assistance/Ticket?' . $parametros,
            'GET',
            [
                'Accept: application/json',
                'Authorization: Bearer ' . $tokenAcesso
            ]
        );
        if ($pagina['status'] < 200 || $pagina['status'] >= 300) {
            if ($pagina['status'] === 404) {
                throw new RuntimeException('A rota de chamados da API v2.3 não foi encontrada. Confira a URL base e a versão habilitada no GLPI.');
            }
            throw new RuntimeException('A API de chamados do GLPI respondeu com HTTP ' . $pagina['status'] . '. Confira o escopo do cliente e as permissões do usuário.');
        }

        $itens = $pagina['dados'];
        if (!$ehLista($itens) && isset($itens['data']) && is_array($itens['data'])) {
            $itens = $itens['data'];
        }
        if (!$ehLista($itens)) {
            throw new RuntimeException('A resposta de chamados do GLPI está em formato inesperado.');
        }

        foreach ($itens as $chamado) {
            if (!is_array($chamado) || !isset($chamado['date']) || !is_string($chamado['date'])) {
                throw new RuntimeException('A API do GLPI não retornou a data de abertura de um chamado.');
            }
            if (!$ehCategoriaRelatorio($chamado)) {
                continue;
            }

            $dataAbertura = $chamado['date'];
            try {
                $abertura = new DateTimeImmutable($dataAbertura);
            } catch (Exception $excecao) {
                throw new RuntimeException('O GLPI retornou uma data inválida em um chamado.');
            }
            if ($abertura < $inicioPeriodoReparo || $abertura >= $fimPeriodoReparo) {
                continue;
            }

            $mes = substr($dataAbertura, 0, 7);
            if (!isset($relatorio[$mes])) {
                continue;
            }
            $relatorio[$mes]['quantidade']++;

            $dataPrimeiroAtendimento = $chamado['take_into_account_date']
                ?? $chamado['takeintoaccountdate']
                ?? null;
            if (
                $dataPrimeiroAtendimento === null
                || !is_string($dataPrimeiroAtendimento)
                || trim($dataPrimeiroAtendimento) === ''
                || strpos($dataPrimeiroAtendimento, '0000-00-00') === 0
            ) {
                $tratativasSemInicioMtfr++;
            } else {
                try {
                    $primeiroAtendimento = new DateTimeImmutable($dataPrimeiroAtendimento);
                } catch (Exception $excecao) {
                    throw new RuntimeException('O GLPI retornou um horário inválido de primeiro atendimento.');
                }

                try {
                    $dataAberturaComercial = $abertura
                        ->setTimezone(new DateTimeZone(date_default_timezone_get()))
                        ->format('Y-m-d H:i:s');
                    $aberturaMtfr = new DateTimeImmutable(dthora_comercial($dataAberturaComercial));
                } catch (Exception $excecao) {
                    throw new RuntimeException('O GLPI retornou uma data de abertura inválida para o cálculo do MTFR.');
                }

                if ($primeiroAtendimento < $aberturaMtfr) {
                    $tratativasInvalidasMtfr++;
                } else {
                    $horasMtfr = ($primeiroAtendimento->getTimestamp() - $aberturaMtfr->getTimestamp()) / 3600;
                    if ($horasMtfr < $LimiteParaMostrar) {
                        $tratativasAbaixoLimiteMtfr++;
                    } else {
                        $resumoMtfr[$mes]['soma_horas'] += $horasMtfr;
                        $resumoMtfr[$mes]['quantidade']++;
                        $chamadosMtfr[] = [
                            'id' => isset($chamado['id']) ? (string) $chamado['id'] : '—',
                            'assunto' => $obterTextoCampo($chamado['name'] ?? $chamado['subject'] ?? $chamado['title'] ?? null),
                            'abertura' => $aberturaMtfr->format('d/m/Y H:i'),
                            'abertura_ordem' => $aberturaMtfr->getTimestamp(),
                            'primeiro_atendimento' => $primeiroAtendimento->format('d/m/Y H:i'),
                            'horas' => $horasMtfr
                        ];
                    }
                }
            }

            $dataSolucao = $chamado['date_solve'] ?? null;
            if (!is_string($dataSolucao) || trim($dataSolucao) === '') {
                continue;
            }

            try {
                $solucao = new DateTimeImmutable($dataSolucao);
            } catch (Exception $excecao) {
                throw new RuntimeException('O GLPI retornou uma data inválida em um chamado.');
            }

            if ($solucao < $abertura || $solucao >= $fimPeriodoReparo) {
                continue;
            }

            $horasSla = ($solucao->getTimestamp() - $abertura->getTimestamp()) / 3600;
            if ($horasSla < $LimiteParaMostrar) {
                $slasAbaixoLimite++;
                continue;
            }

            $relatorio[$mes]['soma_horas'] += $horasSla;
            $relatorio[$mes]['solucionados']++;
        }

        $quantidadePagina = count($itens);
        $inicio += $quantidadePagina;
    } while ($quantidadePagina === $limite);

    usort($chamadosMtfr, static function ($a, $b) {
        return $b['abertura_ordem'] <=> $a['abertura_ordem'];
    });

    foreach ($relatorio as &$dadosMes) {
        $dadosMes['media_horas'] = $dadosMes['solucionados'] > 0
            ? $dadosMes['soma_horas'] / $dadosMes['solucionados']
            : null;
    }
    unset($dadosMes);

    foreach ($periodosRelatorio as $mes => $nome) {
        $dadosGraficoSla['rotulos'][] = $nome;
        $dadosGraficoSla['medias'][] = $relatorio[$mes]['media_horas'];

        $resumoMtfr[$mes]['media_horas'] = $resumoMtfr[$mes]['quantidade'] > 0
            ? $resumoMtfr[$mes]['soma_horas'] / $resumoMtfr[$mes]['quantidade']
            : null;
        $dadosGraficoMtfr['rotulos'][] = $nome;
        $dadosGraficoMtfr['medias'][] = $resumoMtfr[$mes]['media_horas'];
    }

    if ($inicioPeriodoReparo !== null) {
        try {
            $inicio = 0;
            $filtroPeriodoReparo = 'date_solve=ge=' . $inicioPeriodoReparo->format('Y-m-d H:i:s')
                . ';date_solve=lt=' . $fimPeriodoReparo->format('Y-m-d H:i:s');
            do {
                $parametros = http_build_query([
                    'filter' => $filtroPeriodoReparo,
                    'start' => $inicio,
                    'limit' => $limite
                ]);
                $pagina = $executarRequisicao(
                    $apiUrl . '/v2.3/Assistance/Ticket?' . $parametros,
                    'GET',
                    [
                        'Accept: application/json',
                        'Authorization: Bearer ' . $tokenAcesso
                    ]
                );
                if ($pagina['status'] < 200 || $pagina['status'] >= 300) {
                    throw new RuntimeException('A API do GLPI respondeu com HTTP ' . $pagina['status'] . ' ao consultar as ordens concluídas no período.');
                }

                $itens = $pagina['dados'];
                if (!$ehLista($itens) && isset($itens['data']) && is_array($itens['data'])) {
                    $itens = $itens['data'];
                }
                if (!$ehLista($itens)) {
                    throw new RuntimeException('A resposta de ordens concluídas do GLPI está em formato inesperado.');
                }

                foreach ($itens as $chamado) {
                    if (!is_array($chamado)) {
                        throw new RuntimeException('A API do GLPI retornou uma ordem em formato inválido.');
                    }
                    if (!$ehCategoriaRelatorio($chamado)) {
                        continue;
                    }

                    $dataFim = '';
                    foreach (['date_close', 'closedate', 'date_solve'] as $campo) {
                        if (isset($chamado[$campo]) && is_string($chamado[$campo]) && trim($chamado[$campo]) !== '') {
                            $dataFim = $chamado[$campo];
                            break;
                        }
                    }
                    if ($dataFim === '') {
                        continue;
                    }

                    try {
                        $fimReparo = new DateTimeImmutable($dataFim);
                    } catch (Exception $excecao) {
                        throw new RuntimeException('O GLPI retornou um horário inválido em uma ordem concluída.');
                    }

                    if ($fimReparo < $inicioPeriodoReparo || $fimReparo >= $fimPeriodoReparo) {
                        continue;
                    }

                    $dataInicio = '';
                    foreach (['take_into_account_date', 'takeintoaccountdate', 'date_assign', 'date_takeintoaccount', 'date_assigned'] as $campo) {
                        if (isset($chamado[$campo]) && is_string($chamado[$campo]) && trim($chamado[$campo]) !== '') {
                            $dataInicio = $chamado[$campo];
                            break;
                        }
                    }
                    if ($dataInicio === '') {
                        $reparosSemInicio++;
                        continue;
                    }

                    try {
                        $inicioReparo = new DateTimeImmutable($dataInicio);
                    } catch (Exception $excecao) {
                        throw new RuntimeException('O GLPI retornou um horário inválido em uma ordem concluída.');
                    }

                    if ($fimReparo < $inicioReparo) {
                        $reparosInvalidos++;
                        continue;
                    }

                    $horasReparo = ($fimReparo->getTimestamp() - $inicioReparo->getTimestamp()) / 3600;
                    if ($horasReparo < $LimiteParaMostrar) {
                        $reparosAbaixoLimite++;
                        continue;
                    }

                    $ordensReparo[] = [
                        'id' => isset($chamado['id']) ? (string) $chamado['id'] : '—',
                        'cliente' => $obterTextoCampo(
                            $chamado['client'] ?? $chamado['customer'] ?? $chamado['requester']
                            ?? $chamado['entities_id'] ?? $chamado['users_id_requester']
                            ?? $chamado['users_id_recipient'] ?? null
                        ),
                        'assunto' => $obterTextoCampo($chamado['name'] ?? $chamado['subject'] ?? $chamado['title'] ?? null),
                        'inicio' => $inicioReparo->format('d/m/Y H:i'),
                        'fim' => $fimReparo->format('d/m/Y H:i'),
                        'horas' => $horasReparo,
                        'data_fim' => $fimReparo
                    ];
                }

                $quantidadePagina = count($itens);
                $inicio += $quantidadePagina;
            } while ($quantidadePagina === $limite);

            $agrupamentosReparo = [];
            foreach ($ordensReparo as $ordem) {
                $data = $ordem['data_fim'];
                if ($agrupamentoReparo === 'dia') {
                    $chave = $data->format('Y-m-d');
                    $rotulo = $data->format('d/m');
                } elseif ($agrupamentoReparo === 'semana') {
                    $inicioSemana = $data->modify('monday this week');
                    $chave = $inicioSemana->format('o-W');
                    $rotulo = $inicioSemana->format('d/m');
                } else {
                    $chave = $data->format('Y-m');
                    $rotulo = $data->format('m/Y');
                }

                if (!isset($agrupamentosReparo[$chave])) {
                    $agrupamentosReparo[$chave] = ['rotulo' => $rotulo, 'soma_horas' => 0, 'quantidade' => 0];
                }
                $agrupamentosReparo[$chave]['soma_horas'] += $ordem['horas'];
                $agrupamentosReparo[$chave]['quantidade']++;
            }
            ksort($agrupamentosReparo);
            foreach ($agrupamentosReparo as $agrupamento) {
                $dadosGraficoReparo['rotulos'][] = $agrupamento['rotulo'];
                $dadosGraficoReparo['medias'][] = $agrupamento['soma_horas'] / $agrupamento['quantidade'];
            }
            usort($ordensReparo, static function ($a, $b) {
                return $b['data_fim'] <=> $a['data_fim'];
            });
        } catch (Throwable $excecaoReparo) {
            $erroReparo = $excecaoReparo->getMessage();
        }
    }
} catch (Throwable $excecao) {
    $erro = $excecao->getMessage();
}

$reparosPorMes = [];
$quantidadeSlaSelecionada = 0;
$quantidadeMtfrSelecionada = 0;
foreach ($ordensReparo as $ordem) {
    $mesReparo = $ordem['data_fim']->format('Y-m');
    if (!isset($reparosPorMes[$mesReparo])) {
        $reparosPorMes[$mesReparo] = ['soma_horas' => 0, 'quantidade' => 0];
    }

    $reparosPorMes[$mesReparo]['soma_horas'] += $ordem['horas'];
    $reparosPorMes[$mesReparo]['quantidade']++;
}
foreach ($periodosRelatorio as $mes => $nome) {
    $dadosGraficoIndicadores['rotulos'][] = $nome;
    $dadosGraficoIndicadores['sla'][] = $relatorio[$mes]['media_horas'];
    $dadosGraficoIndicadores['mtfr'][] = $resumoMtfr[$mes]['media_horas'];
    $quantidadeSlaSelecionada += $relatorio[$mes]['solucionados'];
    $quantidadeMtfrSelecionada += $resumoMtfr[$mes]['quantidade'];
    $dadosGraficoIndicadores['mttr'][] = isset($reparosPorMes[$mes])
        ? $reparosPorMes[$mes]['soma_horas'] / $reparosPorMes[$mes]['quantidade']
        : null;
}
$quantidadeMttrSelecionada = count($ordensReparo);

$rotuloPeriodo = $inicioPeriodoReparo !== null
    ? $inicioPeriodoReparo->format('d/m/Y H:i') . ' a ' . $fimDataReparo->format('d/m/Y H:i')
    : $dataInicialReparo . ' a ' . $dataFinalReparo;

require __DIR__ . '/../views/header.php';

?>
            <div id="glpi-loading-overlay" class="loading-overlay" role="status" aria-live="polite" hidden>
                <div class="loading-overlay-conteudo">
                    <span class="loading-overlay-spinner" aria-hidden="true"></span>
                    <span>Carregando relatório GLPI...</span>
                </div>
            </div>
            <script>
                const overlayInicialGlpi = document.getElementById('glpi-loading-overlay');
                overlayInicialGlpi.hidden = false;
                document.body.setAttribute('aria-busy', 'true');
                window.addEventListener('load', function () {
                    overlayInicialGlpi.hidden = true;
                    document.body.removeAttribute('aria-busy');
                }, {once: true});
            </script>
            <section class="painel" aria-label="Seleção do período do relatório">
                <form method="get" action="<?= escapar(endereco('views/glpi_relatorio.php')) ?>" class="filtro-periodo">
                    <div class="w-60">
                        <label for="inicio-relatorio">Data e hora inicial</label>
                        <input type="datetime-local" id="inicio-relatorio" name="inicio" value="<?= escapar($dataInicialReparo) ?>" min="1000-01-01T00:00" max="<?= escapar($agoraIso) ?>" required>
                    </div>
                    <div class="w-60">
                        <label for="fim-relatorio">Data e hora final</label>
                        <input type="datetime-local" id="fim-relatorio" name="fim" value="<?= escapar($dataFinalReparo) ?>" min="1000-01-01T00:00" max="<?= escapar($agoraIso) ?>" required>
                    </div>
                    <button type="submit" class="botao">Aplicar período</button>
                    <?php foreach ($periodosRapidos as $atalho => $periodo): ?>
                        <button type="submit" name="periodo" value="<?= escapar($atalho) ?>" class="botao secundario" formnovalidate><?= escapar($periodo['rotulo']) ?></button>
                    <?php endforeach; ?>
                </form>
            </section>

            <section class="painel" aria-labelledby="titulo-grafico-indicadores">
                <h2 id="titulo-grafico-indicadores">INDICADORES DE ATENDIMENTO E REPAROS</h2>
                <p>SLA e MTFR são agrupados pelo mês de abertura; MTTR, pelo mês de conclusão. Valores em horas. Não são considerados Tickets abaixo de 10 minutos</p>
                <?php if ($erro !== ''): ?>
                    <div class="aviso" role="note">Não foi possível gerar o gráfico porque a consulta principal do GLPI falhou.</div>
                <?php elseif ($erroReparo !== ''): ?>
                    <div class="aviso" role="note">Não foi possível gerar o gráfico: <?= escapar($erroReparo) ?></div>
                <?php elseif ($dadosGraficoIndicadores['rotulos'] === []): ?>
                    <div class="aviso" role="note">Selecione um período válido para visualizar os indicadores.</div>
                <?php else: ?>
                    <div class="grafico">
                        <canvas id="grafico-indicadores" role="img" aria-label="Gráfico de área mensal com as séries de SLA, MTFR e MTTR, em horas"></canvas>
                    </div>
                    <script>
                        window.glpiChartReady.then(function () {
                            const canvas = document.getElementById('grafico-indicadores');
                            const contexto = canvas.getContext('2d');
                            const criarDegrade = function (cor, opacidade) {
                                const degrade = contexto.createLinearGradient(0, 0, 0, canvas.clientHeight || 360);
                                degrade.addColorStop(0, 'rgba(' + cor + ', ' + opacidade + ')');
                                degrade.addColorStop(1, 'rgba(' + cor + ', 0.02)');
                                return degrade;
                            };
                            const dados = <?= json_encode($dadosGraficoIndicadores, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
                            new Chart(canvas, {
                                type: 'line',
                                data: {
                                    labels: dados.rotulos,
                                    datasets: [
                                        {
                                            label: 'SLA',
                                            data: dados.sla,
                                            borderColor: '#4285F4',
                                            backgroundColor: criarDegrade('66, 133, 244', 0.3),
                                            fill: true,
                                            tension: 0.35,
                                            pointRadius: 3,
                                            spanGaps: false
                                        },
                                        {
                                            label: 'MTFR',
                                            data: dados.mtfr,
                                            borderColor: '#34A853',
                                            backgroundColor: criarDegrade('52, 168, 83', 0.3),
                                            fill: true,
                                            tension: 0.35,
                                            pointRadius: 3,
                                            spanGaps: false
                                        },
                                        {
                                            label: 'MTTR',
                                            data: dados.mttr,
                                            borderColor: '#f97316',
                                            backgroundColor: criarDegrade('249, 115, 22', 0.35),
                                            fill: true,
                                            tension: 0.35,
                                            pointRadius: 3,
                                            spanGaps: false
                                        }
                                    ]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    interaction: {mode: 'index', intersect: false},
                                    plugins: {
                                        legend: {position: 'bottom'},
                                        tooltip: {
                                            callbacks: {
                                                label: function (contextoTooltip) {
                                                    return contextoTooltip.dataset.label + ': ' + Number(contextoTooltip.raw).toLocaleString('pt-BR', {maximumFractionDigits: 2}) + ' h';
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            title: {display: true, text: 'Horas'}
                                        }
                                    }
                                }
                            });
                        }, function () {
                            document.getElementById('grafico-indicadores').insertAdjacentHTML('afterend', '<div class="aviso erro" role="alert">Não foi possível carregar a biblioteca do gráfico.</div>');
                        });
                    </script>
                <?php endif; ?>
            </section>

            <section class="painel">
                <?php if ($erro !== ''): ?>
                    <div class="aviso erro" role="alert"><?= escapar($erro) ?></div>
                <?php endif; ?>

                <?php if ($erro === ''): ?>
                    <h2 id="titulo-sla">SLA MÉDIO POR MÊS</h2>
                    <details>
                        <summary class="botao secundario">Como ler esse KPI</summary>
                        <div class="aviso" role="note">Chamados da categoria Clientes &gt; Offline abertos de <?= escapar($rotuloPeriodo) ?>, agrupados pelo mês de abertura. O horário final selecionado inclui o minuto inteiro. O SLA médio é o tempo corrido entre abertura e solução; chamados sem solução até o horário final selecionado não entram na média.</div>
                    </details>
                    <div class="grafico">
                        <canvas id="grafico-sla" role="img" aria-label="SLA médio mensal entre abertura e solução, em horas corridas"></canvas>
                    </div>
                    <details>
                        <summary>Consultar os valores dos gráficos</summary>
                        <div class="tabela-scroll max-h-[600px] overflow-y-auto">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Mês</th>
                                        <th>Chamados abertos</th>
                                        <th>SLA médio até solução (horas corridas)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_reverse($periodosRelatorio, true) as $mes => $nome): ?>
                                        <?php $dadosMes = $relatorio[$mes]; ?>
                                        <tr>
                                            <td><?= escapar($nome) ?></td>
                                            <td><?= (int) $dadosMes['quantidade'] ?></td>
                                            <td><?= $dadosMes['media_horas'] === null ? '—' : escapar(number_format($dadosMes['media_horas'], 2, ',', '.')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>
                    <script>
                        window.glpiChartReady.then(function () {
                            const canvas = document.getElementById('grafico-sla');
                            const contexto = canvas.getContext('2d');
                            const degrade = contexto.createLinearGradient(0, 0, 0, canvas.clientHeight || 360);
                            degrade.addColorStop(0, 'rgba(37, 99, 235, 0.38)');
                            degrade.addColorStop(1, 'rgba(37, 99, 235, 0.02)');
                            const dados = <?= json_encode($dadosGraficoSla, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
                            new Chart(canvas, {
                                type: 'line',
                                data: {
                                    labels: dados.rotulos,
                                    datasets: [{
                                        label: 'SLA médio',
                                        data: dados.medias,
                                        borderColor: '#2563eb',
                                        backgroundColor: degrade,
                                        fill: true,
                                        tension: 0.35,
                                        pointRadius: 3
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        tooltip: {
                                            callbacks: {
                                                label: function (contextoTooltip) {
                                                    return contextoTooltip.dataset.label + ': ' + Number(contextoTooltip.raw).toLocaleString('pt-BR', {maximumFractionDigits: 2}) + ' h';
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            title: {display: true, text: 'Horas corridas'},
                                            ticks: {
                                                callback: function (valor) {
                                                    return valor + ' h';
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                        }, function () {
                            document.getElementById('grafico-sla').insertAdjacentHTML('afterend', '<div class="aviso erro" role="alert">Não foi possível carregar a biblioteca do gráfico.</div>');
                        });
                    </script>
                <?php endif; ?>

                <section aria-labelledby="titulo-mttr">
                    <h2 id="titulo-mttr">Tempo Médio de Reparo (MTTR)</h2>
                    <details>
                        <summary class="botao secundario">Como ler esse KPI</summary>
                        <div class="aviso" role="note">Média de horas entre o início do atendimento pelo técnico e a baixa da ordem. O período é definido pela data e hora de conclusão; ordens sem data e hora de início do atendimento não entram no cálculo.</div>
                    </details>

                    <?php if ($erro === ''): ?>
                        <?php if ($erroReparo !== ''): ?>
                            <div class="aviso erro" role="alert"><?= escapar($erroReparo) ?></div>
                        <?php else: ?>
                        <div class="grafico">
                            <canvas id="grafico-mttr" role="img" aria-label="Tempo médio de reparo por período, em horas"></canvas>
                        </div>

                        <details>
                            <summary>Consultar os valores dos gráficos</summary>
                            <div class="tabela-scroll max-h-[600px] overflow-y-auto">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Cliente</th>
                                            <th>Assunto</th>
                                            <th>Hora inicial</th>
                                            <th>Hora final</th>
                                            <th>Tempo (horas)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($ordensReparo === []): ?>
                                            <tr><td colspan="6">Nenhuma ordem concluída com horário de atendimento no período.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($ordensReparo as $ordem): ?>
                                                <tr>
                                                    <td><?= escapar($ordem['id']) ?></td>
                                                    <td><?= escapar($ordem['cliente'] !== '' ? $ordem['cliente'] : '—') ?></td>
                                                    <td><?= escapar($ordem['assunto'] !== '' ? $ordem['assunto'] : '—') ?></td>
                                                    <td><?= escapar($ordem['inicio']) ?></td>
                                                    <td><?= escapar($ordem['fim']) ?></td>
                                                    <td><?= escapar(number_format($ordem['horas'], 2, ',', '.')) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </details>
                        <script>
                            window.glpiChartReady.then(function () {
                                const canvas = document.getElementById('grafico-mttr');
                                const contexto = canvas.getContext('2d');
                                const degrade = contexto.createLinearGradient(0, 0, 0, canvas.clientHeight || 360);
                                degrade.addColorStop(0, 'rgba(249, 115, 22, 0.38)');
                                degrade.addColorStop(1, 'rgba(249, 115, 22, 0.02)');
                                const dados = <?= json_encode($dadosGraficoReparo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
                                new Chart(canvas, {
                                    type: 'line',
                                    data: {
                                        labels: dados.rotulos,
                                        datasets: [{
                                            label: 'Tempo médio de reparo',
                                            data: dados.medias,
                                            borderColor: '#f97316',
                                            backgroundColor: degrade,
                                            fill: true,
                                            tension: 0.35,
                                            pointRadius: 3
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            tooltip: {
                                                callbacks: {
                                                    label: function (contextoTooltip) {
                                                        return contextoTooltip.dataset.label + ': ' + Number(contextoTooltip.raw).toLocaleString('pt-BR', {maximumFractionDigits: 2}) + ' h';
                                                    }
                                                }
                                            }
                                        },
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                title: {display: true, text: 'Horas gastas'},
                                                ticks: {
                                                    callback: function (valor) {
                                                        return valor + ' h';
                                                    }
                                                }
                                            }
                                        }
                                    }
                                });
                            }, function () {
                                document.getElementById('grafico-mttr').insertAdjacentHTML('afterend', '<div class="aviso erro" role="alert">Não foi possível carregar a biblioteca do gráfico.</div>');
                            });
                        </script>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            </section>
            <section class="painel" aria-labelledby="titulo-mtfr">
                <h2 id="titulo-mtfr">Tempo Médio de Início de Tratativa (MTFR)</h2>
                <details>
                    <summary class="botao secundario">Como ler esse KPI</summary>
                    <div class="aviso" role="note">Tempo corrido entre a abertura ajustada para o próximo horário comercial e o primeiro atendimento técnico, calculado em horas e frações. O período e o agrupamento são definidos pela data e hora originais de abertura.</div>
                </details>

                <?php if ($erro !== ''): ?>
                    <div class="aviso" role="note">Não foi possível carregar os dados de MTFR porque a consulta principal do GLPI falhou.</div>
                <?php else: ?>
                    <div class="grafico">
                        <canvas id="grafico-mtfr" role="img" aria-label="Tempo médio entre abertura e primeiro atendimento técnico por mês, em horas"></canvas>
                    </div>

                    <details>
                        <summary>Consultar os valores dos gráficos</summary>
                        <div class="tabela-scroll max-h-[600px] overflow-y-auto">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Assunto</th>
                                        <th>Abertura considerada</th>
                                        <th>Primeiro atendimento</th>
                                        <th>MTFR (horas)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($chamadosMtfr === []): ?>
                                        <tr><td colspan="5">Nenhum chamado com data de primeiro atendimento no período.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($chamadosMtfr as $chamado): ?>
                                            <tr>
                                                <td><?= escapar($chamado['id']) ?></td>
                                                <td><?= escapar($chamado['assunto'] !== '' ? $chamado['assunto'] : '—') ?></td>
                                                <td><?= escapar($chamado['abertura']) ?></td>
                                                <td><?= escapar($chamado['primeiro_atendimento']) ?></td>
                                                <td><?= escapar(number_format($chamado['horas'], 2, ',', '.')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>
                    <script>
                        window.glpiChartReady.then(function () {
                            const canvas = document.getElementById('grafico-mtfr');
                            const contexto = canvas.getContext('2d');
                            const degrade = contexto.createLinearGradient(0, 0, 0, canvas.clientHeight || 360);
                            degrade.addColorStop(0, 'rgba(52, 168, 83, 0.38)');
                            degrade.addColorStop(1, 'rgba(52, 168, 83, 0.02)');
                            const dados = <?= json_encode($dadosGraficoMtfr, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
                            new Chart(canvas, {
                                type: 'line',
                                data: {
                                    labels: dados.rotulos,
                                    datasets: [{
                                        label: 'Tempo médio de início de tratativa',
                                        data: dados.medias,
                                        borderColor: '#34A853',
                                        backgroundColor: degrade,
                                        fill: true,
                                        tension: 0.35,
                                        pointRadius: 3
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        tooltip: {
                                            callbacks: {
                                                label: function (contextoTooltip) {
                                                    return contextoTooltip.dataset.label + ': ' + Number(contextoTooltip.raw).toLocaleString('pt-BR', {maximumFractionDigits: 2}) + ' h';
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            title: {display: true, text: 'Horas corridas'},
                                            ticks: {
                                                callback: function (valor) {
                                                    return valor + ' h';
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                        }, function () {
                            document.getElementById('grafico-mtfr').insertAdjacentHTML('afterend', '<div class="aviso erro" role="alert">Não foi possível carregar a biblioteca do gráfico.</div>');
                        });
                    </script>
                <?php endif; ?>
            </section>
            <section class="painel" aria-label="Quantidades de registros incluídos e excluídos">
                <div class="acoes">
                    <button
                        type="button"
                        id="mostrar-quantidades-registros"
                        class="botao secundario"
                        aria-expanded="false"
                        aria-controls="quantidades-registros"
                    >Mostrar quantidades de registros selecionados</button>
                    <button
                        type="button"
                        id="mostrar-registros-excluidos"
                        class="botao secundario"
                        aria-expanded="false"
                        aria-controls="registros-excluidos"
                    >Mostrar registros excluídos</button>
                </div>
                <div id="quantidades-registros" class="aviso" role="status" hidden>
                    <p>Registros incluídos nos cálculos do período selecionado:</p>
                    <ul>
                        <li>SLA: <?= (int) $quantidadeSlaSelecionada ?> registro(s)</li>
                        <li>MTFR: <?= (int) $quantidadeMtfrSelecionada ?> registro(s)</li>
                        <li>MTTR: <?= (int) $quantidadeMttrSelecionada ?> registro(s)</li>
                    </ul>
                </div>
                <div id="registros-excluidos" class="aviso" role="status" hidden>
                    <p>Registros excluídos dos cálculos:</p>
                    <ul>
                        <?php if ($slasAbaixoLimite > 0): ?>
                            <li><?= (int) $slasAbaixoLimite ?> registro(s) de SLA abaixo de <?= escapar(number_format($LimiteParaMostrar, 2, ',', '.')) ?> h foram excluídos do gráfico e da média.</li>
                        <?php endif; ?>
                        <?php if ($reparosSemInicio > 0): ?>
                            <li><?= (int) $reparosSemInicio ?> ordem(ns) concluída(s) não possuem horário de início do atendimento e foram excluídas do cálculo.</li>
                        <?php endif; ?>
                        <?php if ($reparosInvalidos > 0): ?>
                            <li><?= (int) $reparosInvalidos ?> ordem(ns) têm baixa anterior ao início do atendimento e foram excluídas do MTTR. Verifique os horários no GLPI.</li>
                        <?php endif; ?>
                        <?php if ($reparosAbaixoLimite > 0): ?>
                            <li><?= (int) $reparosAbaixoLimite ?> ordem(ns) com MTTR abaixo de <?= escapar(number_format($LimiteParaMostrar, 2, ',', '.')) ?> h foram excluídas do gráfico, da média e da tabela.</li>
                        <?php endif; ?>
                        <?php if ($tratativasSemInicioMtfr > 0): ?>
                            <li><?= (int) $tratativasSemInicioMtfr ?> chamado(s) não possuem data e hora de primeiro atendimento e foram excluídos do cálculo.</li>
                        <?php endif; ?>
                        <?php if ($tratativasInvalidasMtfr > 0): ?>
                            <li><?= (int) $tratativasInvalidasMtfr ?> chamado(s) têm o primeiro atendimento registrado antes da abertura e foram excluídos do MTFR. Verifique os horários no GLPI.</li>
                        <?php endif; ?>
                        <?php if ($tratativasAbaixoLimiteMtfr > 0): ?>
                            <li><?= (int) $tratativasAbaixoLimiteMtfr ?> chamado(s) com MTFR abaixo de <?= escapar(number_format($LimiteParaMostrar, 2, ',', '.')) ?> h foram excluídos do gráfico, da média e da tabela.</li>
                        <?php endif; ?>
                        <?php if (
                            $slasAbaixoLimite === 0
                            && $reparosSemInicio === 0
                            && $reparosInvalidos === 0
                            && $reparosAbaixoLimite === 0
                            && $tratativasSemInicioMtfr === 0
                            && $tratativasInvalidasMtfr === 0
                            && $tratativasAbaixoLimiteMtfr === 0
                        ): ?>
                            <li>Nenhum registro foi excluído do cálculo.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </section>
<?php require __DIR__ . '/../views/footer.php'; ?>
