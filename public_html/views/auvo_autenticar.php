<?php

// Carrega as funções compartilhadas pela aplicação, incluindo sessão, validação e escape HTML.
require_once __DIR__ . '/../biblioteca.php';

// Esta página só pode ser acessada por usuários autenticados com perfil de administrador.
exigirLogin();

if (!ehAdministrador()) {
    http_response_code(403);
    exit('Apenas administradores podem autenticar a integração Auvo.');
}

// Define o estado inicial da página e os caminhos dos arquivos de configuração fora da pasta pública.
$titulo = 'Autenticar Auvo';
$erro = '';
$sucesso = '';
$tickets = [];
$inicio = date('Y-m-d');
$fim = date('Y-m-d');
$pastaProjeto = dirname(__DIR__, 2);
$arquivoCredenciais = $pastaProjeto . '/auvo.credentials.php';
$arquivoTokens = $pastaProjeto . '/auvo.tokens.json';
$urlBaseAuvo = 'https://api.auvo.com.br/v2';
$tokenSalvo = false;

// Apenas verifica se existe um token salvo; o valor nunca é mostrado na página.
if (is_file($arquivoTokens)) {
    $tokensSalvos = json_decode((string) file_get_contents($arquivoTokens), true);
    $tokenSalvo = is_array($tokensSalvos)
        && isset($tokensSalvos['token'])
        && is_string($tokensSalvos['token'])
        && $tokensSalvos['token'] !== '';
}

// A autenticação e as consultas são executadas somente após o envio do formulário.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Impede o envio do formulário sem o token de proteção contra requisições forjadas.
    validarToken();

    // Lê e valida os valores submetidos, preservando as datas para exibição em caso de erro.
    $inicio = lerCampo($_POST, 'inicio', $inicio);
    $fim = lerCampo($_POST, 'fim', $fim);

    try {
        // Verifica o formato das datas e garante que o intervalo esteja em ordem cronológica.
        if (!dataValida($inicio) || !dataValida($fim) || $inicio > $fim) {
            throw new InvalidArgumentException('Informe datas válidas e confira se a data inicial não é posterior à data final.');
        }

        // Carrega as credenciais privadas do arquivo local, que não deve ficar na pasta pública.
        if (!is_file($arquivoCredenciais)) {
            throw new RuntimeException('Arquivo auvo.credentials.php não encontrado.');
        }

        $credenciais = require $arquivoCredenciais;
        if (!is_array($credenciais)) {
            throw new RuntimeException('O arquivo auvo.credentials.php está inválido.');
        }

        // Confirma que os dois valores exigidos pela API foram configurados.
        foreach (['api_key', 'api_token'] as $campo) {
            if (
                !isset($credenciais[$campo])
                || !is_string($credenciais[$campo])
                || trim($credenciais[$campo]) === ''
            ) {
                throw new RuntimeException('Preencha api_key e api_token no arquivo auvo.credentials.php.');
            }
        }

        // Centraliza as chamadas HTTP para a autenticação e para as consultas à API.
        $enviarRequisicao = static function ($url, $metodo, array $cabecalhos, $corpo = null) {
            // Define método, cabeçalhos, timeout e corpo opcional da requisição.
            $opcoesHttp = [
                'method' => $metodo,
                'header' => implode("\r\n", $cabecalhos),
                'timeout' => 30,
                'ignore_errors' => true,
                'follow_location' => 0
            ];
            if ($corpo !== null) {
                $opcoesHttp['content'] = $corpo;
            }

            // Mantém a validação do certificado TLS ativa ao conectar-se à Auvo.
            $opcoes = [
                'http' => $opcoesHttp,
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true
                ]
            ];
            $aviso = '';
            // Captura avisos de transporte para incluí-los em uma exceção útil se a chamada falhar.
            set_error_handler(static function ($severidade, $mensagem) use (&$aviso) {
                $aviso = $mensagem;
                return true;
            });
            try {
                $resposta = file_get_contents($url, false, stream_context_create($opcoes));
            } finally {
                restore_error_handler();
            }

            // Não trata uma falha de transporte como se fosse uma resposta válida da API.
            if ($resposta === false) {
                throw new RuntimeException(
                    'Não foi possível conectar à API Auvo.'
                    . ($aviso !== '' ? ' ' . $aviso : '')
                );
            }

            // Extrai o código HTTP dos cabeçalhos devolvidos pelo stream do PHP.
            $status = 0;
            foreach ($http_response_header as $cabecalho) {
                if (preg_match('/^HTTP\/\S+\s+([0-9]{3})/', $cabecalho, $partes)) {
                    $status = (int) $partes[1];
                }
            }

            return ['status' => $status, 'resposta' => $resposta];
        };

        // Solicita à Auvo um token de acesso usando apiKey e apiToken em JSON.
        $respostaLogin = $enviarRequisicao(
            $urlBaseAuvo . '/login',
            'POST',
            ['Accept: application/json', 'Content-Type: application/json'],
            json_encode([
                'apiKey' => $credenciais['api_key'],
                'apiToken' => $credenciais['api_token']
            ], JSON_THROW_ON_ERROR)
        );
        // Aceita o nome de token documentado no retorno principal e a estrutura alternativa.
        $dadosResposta = json_decode($respostaLogin['resposta'], true);
        $token = is_array($dadosResposta)
            ? ($dadosResposta['token'] ?? $dadosResposta['result']['accessToken'] ?? '')
            : '';

        // Interrompe a operação se o login falhar ou não retornar um token utilizável.
        if ($respostaLogin['status'] < 200 || $respostaLogin['status'] >= 300 || !is_string($token) || $token === '') {
            throw new RuntimeException(
                'A Auvo não autenticou a integração (HTTP ' . $respostaLogin['status'] . ').'
            );
        }

        // Salva o token e sua data de criação no arquivo local, sem expô-lo na interface.
        $dadosTokens = json_encode([
            'token' => $token,
            'createdAt' => date(DATE_ATOM)
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (file_put_contents($arquivoTokens, $dadosTokens . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Autenticação aceita, mas não foi possível salvar auvo.tokens.json.');
        }
        $tokenSalvo = true;

        // Consulta os tickets abertos dentro do intervalo selecionado, incluindo o dia final completo.
        $filtroTickets = json_encode([
            'startDate' => $inicio . 'T00:00:00',
            'endDate' => $fim . 'T23:59:59'
        ], JSON_THROW_ON_ERROR);
        $pagina = 1;
        $tamanhoPagina = 100;
        $totalTickets = null;
        do {
            // A API recebe a página e o tamanho de página fora do filtro de datas.
            $parametrosTickets = http_build_query([
                'paramFilter' => $filtroTickets,
                'Page' => $pagina,
                'PageSize' => $tamanhoPagina,
                'Order' => 'Asc'
            ]);
            $respostaTickets = $enviarRequisicao(
                $urlBaseAuvo . '/tickets?' . $parametrosTickets,
                'GET',
                [
                    'Accept: application/json',
                    'Authorization: Bearer ' . $token
                ]
            );
            // Uma resposta HTTP de erro não deve ser apresentada como uma lista vazia.
            if ($respostaTickets['status'] < 200 || $respostaTickets['status'] >= 300) {
                throw new RuntimeException(
                    'Token salvo, mas a consulta de tickets respondeu HTTP '
                    . $respostaTickets['status'] . '.'
                );
            }

            // Confirma o formato esperado antes de acessar os tickets retornados.
            $dadosTickets = json_decode($respostaTickets['resposta'], true);
            if (
                !is_array($dadosTickets)
                || !isset($dadosTickets['result']['entityList'])
                || !is_array($dadosTickets['result']['entityList'])
            ) {
                throw new RuntimeException('A API Auvo retornou uma resposta de tickets em formato inesperado.');
            }

            // Acumula os resultados e usa o total informado pela API para controlar a paginação.
            $tickets = array_merge($tickets, $dadosTickets['result']['entityList']);
            $metadadosPaginacao = $dadosTickets['result']['pagedSearchReturnData'] ?? [];
            if (isset($metadadosPaginacao['totalItems']) && is_numeric($metadadosPaginacao['totalItems'])) {
                $totalTickets = (int) $metadadosPaginacao['totalItems'];
            }

            $itensNestaPagina = count($dadosTickets['result']['entityList']);
            $pagina++;
        // Sem total informado, continua enquanto a página vier cheia; caso contrário, usa totalItems.
        } while (
            $itensNestaPagina > 0
            && ($totalTickets === null
                ? $itensNestaPagina === $tamanhoPagina
                : count($tickets) < $totalTickets)
        );

        // Consulta as tarefas no mesmo intervalo, pois os horários de atendimento vêm delas,
        // e não dos campos retornados pelo endpoint de tickets.
        $filtroTarefas = json_encode([
            'startDate' => $inicio . 'T00:00:00',
            'endDate' => $fim . 'T23:59:59'
        ], JSON_THROW_ON_ERROR);
        $pagina = 1;
        $totalTarefas = null;
        $horariosPorTicket = [];
        do {
            // Prepara os parâmetros de paginação para a busca de tarefas.
            $parametrosTarefas = http_build_query([
                'paramFilter' => $filtroTarefas,
                'Page' => $pagina,
                'PageSize' => $tamanhoPagina,
                'Order' => 'Asc'
            ]);
            $respostaTarefas = $enviarRequisicao(
                $urlBaseAuvo . '/tasks?' . $parametrosTarefas,
                'GET',
                [
                    'Accept: application/json',
                    'Authorization: Bearer ' . $token
                ]
            );
            // Se a busca de tarefas falhar, informa o erro em vez de mostrar horários incompletos.
            if ($respostaTarefas['status'] < 200 || $respostaTarefas['status'] >= 300) {
                throw new RuntimeException(
                    'Tickets consultados, mas a consulta das tarefas respondeu HTTP '
                    . $respostaTarefas['status'] . '.'
                );
            }

            // Confirma que a resposta possui a lista de tarefas esperada.
            $dadosTarefas = json_decode($respostaTarefas['resposta'], true);
            if (
                !is_array($dadosTarefas)
                || !isset($dadosTarefas['result']['entityList'])
                || !is_array($dadosTarefas['result']['entityList'])
            ) {
                throw new RuntimeException('A API Auvo retornou uma resposta de tarefas em formato inesperado.');
            }

            // Agrupa os horários encontrados pelo ID do ticket relacionado à tarefa.
            foreach ($dadosTarefas['result']['entityList'] as $tarefa) {
                // Ignora entradas inválidas ou sem ticket relacionado.
                if (!is_array($tarefa) || empty($tarefa['ticketId'])) {
                    continue;
                }

                $idTicket = (string) $tarefa['ticketId'];
                if (!isset($horariosPorTicket[$idTicket])) {
                    $horariosPorTicket[$idTicket] = ['inicio' => null, 'fim' => null];
                }

                // Para tickets com várias tarefas, guarda a primeira entrada e a última saída.
                foreach (['checkInDate' => 'inicio', 'checkOutDate' => 'fim'] as $campoApi => $campoHorario) {
                    // Campos ausentes ou vazios não representam um horário de atendimento.
                    if (!isset($tarefa[$campoApi]) || !is_string($tarefa[$campoApi]) || trim($tarefa[$campoApi]) === '') {
                        continue;
                    }

                    // Converte a data da API para timestamp e ignora valores que não possam ser lidos.
                    $timestamp = strtotime($tarefa[$campoApi]);
                    if ($timestamp === false) {
                        continue;
                    }

                    // Substitui o valor guardado somente se encontrou uma entrada anterior
                    // ou uma saída posterior para o mesmo ticket.
                    $horarioAtual = $horariosPorTicket[$idTicket][$campoHorario];
                    if (
                        $horarioAtual === null
                        || ($campoHorario === 'inicio' && $timestamp < $horarioAtual)
                        || ($campoHorario === 'fim' && $timestamp > $horarioAtual)
                    ) {
                        $horariosPorTicket[$idTicket][$campoHorario] = $timestamp;
                    }
                }
            }

            // Lê o total de tarefas e decide se há mais páginas a consultar.
            $metadadosPaginacao = $dadosTarefas['result']['pagedSearchReturnData'] ?? [];
            if (isset($metadadosPaginacao['totalItems']) && is_numeric($metadadosPaginacao['totalItems'])) {
                $totalTarefas = (int) $metadadosPaginacao['totalItems'];
            }

            $itensNestaPagina = count($dadosTarefas['result']['entityList']);
            $pagina++;
        // Continua a paginação até processar todas as tarefas do período.
        } while (
            $itensNestaPagina > 0
            && ($totalTarefas === null
                ? $itensNestaPagina === $tamanhoPagina
                : (($pagina - 1) * $tamanhoPagina) < $totalTarefas)
        );

        // Anexa os horários encontrados a cada ticket para facilitar a renderização da tabela.
        foreach ($tickets as &$ticket) {
            $idTicket = isset($ticket['id']) ? (string) $ticket['id'] : '';
            $ticket['_serviceTimes'] = $horariosPorTicket[$idTicket] ?? ['inicio' => null, 'fim' => null];
        }
        unset($ticket);

        // Define a mensagem exibida quando as consultas de tickets e tarefas terminam com sucesso.
        $sucesso = 'Autenticação concluída. Consulta dos tickets no período selecionado realizada com sucesso.';
    } catch (Throwable $excecao) {
        // Exibe a falha na página por meio do bloco de erro escapado abaixo.
        $erro = $excecao->getMessage();
    }
}

// Inclui o cabeçalho padrão da aplicação antes do conteúdo da página.
require __DIR__ . '/../views/header.php';

?>
            <!-- Mostra o formulário, o estado da autenticação e os resultados da consulta. -->
            <section class="painel">
                <p>Selecione as datas inicial e final para consultar os tickets abertos nesse período. O token é salvo fora da pasta pública do site e não é exibido.</p>

                <?php if ($erro !== ''): ?>
                    <!-- Escapa a mensagem para impedir que conteúdo de erro seja interpretado como HTML. -->
                    <div class="aviso erro" role="alert"><?= escapar($erro) ?></div>
                <?php endif; ?>

                <?php if ($sucesso !== ''): ?>
                    <div class="aviso sucesso" role="status"><?= escapar($sucesso) ?></div>
                <?php endif; ?>

                <!-- Informa somente se há um token armazenado, sem revelar seu conteúdo. -->
                <?php if ($tokenSalvo): ?>
                    <p>Existe um token salvo em <code>auvo.tokens.json</code>. O valor não é exibido nesta página.</p>
                <?php else: ?>
                    <p>Nenhum token de acesso foi salvo ainda.</p>
                <?php endif; ?>

                <!-- Envia as datas e o token de proteção do formulário por POST. -->
                <form method="post" class="filtro-periodo filtro-auvo">
                    <input type="hidden" name="token" value="<?= escapar(obterToken()) ?>">
                    <div class="campo-data">
                        <label for="inicio">Data inicial</label>
                        <input
                            id="inicio"
                            name="inicio"
                            type="date"
                            value="<?= escapar($inicio) ?>"
                            required
                        >
                    </div>
                    <div class="campo-data">
                        <label for="fim">Data final</label>
                        <input
                            id="fim"
                            name="fim"
                            type="date"
                            value="<?= escapar($fim) ?>"
                            required
                        >
                    </div>

                    <button type="submit" class="botao">Autenticar e listar tickets</button>
                </form>

                <!-- A tabela só aparece depois de uma consulta concluída com sucesso. -->
                <?php if ($sucesso !== ''): ?>
                    <h2>Tickets de <?= escapar(formatarData($inicio)) ?> a <?= escapar(formatarData($fim)) ?></h2>
                    <?php if ($tickets === []): ?>
                        <p>Nenhum ticket encontrado nesse período.</p>
                    <?php else: ?>
                        <!-- Permite rolagem horizontal em telas menores sem alterar os dados exibidos. -->
                        <div class="tabela-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Assunto</th>
                                        <th>Data de abertura</th>
                                        <th>Início do atendimento</th>
                                        <th>Fim do atendimento</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Exibe uma linha por ticket retornado pela API Auvo. -->
                                    <?php foreach ($tickets as $ticket): ?>
                                        <?php
                                        // Converte a data de abertura, se estiver presente, para o formato local.
                                        $dataAbertura = isset($ticket['creationDate']) && is_string($ticket['creationDate'])
                                            ? strtotime($ticket['creationDate'])
                                            : false;
                                        // Recupera os horários associados às tarefas ou usa null se não houver dados.
                                        $inicioAtendimento = $ticket['_serviceTimes']['inicio'] ?? null;
                                        $fimAtendimento = $ticket['_serviceTimes']['fim'] ?? null;
                                        ?>
                                        <tr>
                                            <!-- A API pode chamar o identificador de id ou ticketId. -->
                                            <td><?= escapar($ticket['ticketId'] ?? $ticket['id'] ?? '') ?></td>
                                            <!-- Aceita os nomes alternativos usados pela API para o assunto. -->
                                            <td><?= escapar($ticket['subject'] ?? $ticket['title'] ?? '') ?></td>
                                            <!-- Mostra N/A quando não há data de abertura válida no retorno. -->
                                            <td><?= $dataAbertura === false ? 'N/A' : escapar(date('d/m/Y H:i:s', $dataAbertura)) ?></td>
                                            <!-- Os horários vêm do check-in e check-out das tarefas relacionadas. -->
                                            <td><?= $inicioAtendimento === null ? 'N/A' : escapar(date('H:i:s', $inicioAtendimento)) ?></td>
                                            <td><?= $fimAtendimento === null ? 'N/A' : escapar(date('H:i:s', $fimAtendimento)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
<!-- Inclui o rodapé padrão da aplicação após o conteúdo da página. -->
<?php require __DIR__ . '/../views/footer.php'; ?>
