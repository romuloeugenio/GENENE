<?php

// Execute com C:\xampp\php\php.exe tests/integration.php.
// Os cadastros são testados em um banco temporário, removido ao terminar.
if (PHP_SAPI !== 'cli') {
    exit('Execute este teste pelo terminal.');
}

$raiz = dirname(__DIR__);
$banco = 'gerene_teste_' . bin2hex(random_bytes(5));
$pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$servidor = null;
$cliente = curl_init();
$base = 'http://127.0.0.1:18763';
$quantidade = 0;

/**
 * Registra o resultado de uma verificação do teste.
 * @param bool $condicao Resultado esperado como verdadeiro.
 * @param string $descricao Descrição da verificação.
 * @return void Imprime OK ou lança uma exceção para interromper o teste.
 */
function conferir($condicao, $descricao)
{
    global $quantidade;

    if (!$condicao) {
        throw new RuntimeException('FALHOU: ' . $descricao);
    }

    $quantidade++;
    echo 'OK: ' . $descricao . PHP_EOL;
}

/**
 * Envia uma requisição HTTP mantendo os cookies do cliente de teste.
 * @param string $caminho Página relativa ao endereço definido em $base.
 * @param array|null $dados Campos de POST; null realiza uma requisição GET.
 * @return array Código HTTP, cabeçalhos e corpo da resposta.
 */
function requisitar($caminho, $dados = null)
{
    global $base, $cliente;

    curl_setopt_array($cliente, [
        CURLOPT_URL => $base . '/' . $caminho,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEFILE => '',
        CURLOPT_TIMEOUT => 10,
        CURLOPT_POST => $dados !== null
    ]);

    if ($dados !== null) {
        curl_setopt($cliente, CURLOPT_POSTFIELDS, http_build_query($dados));
    }

    $resposta = curl_exec($cliente);

    if ($resposta === false) {
        throw new RuntimeException(curl_error($cliente));
    }

    $tamanho = curl_getinfo($cliente, CURLINFO_HEADER_SIZE);

    return [
        'status' => curl_getinfo($cliente, CURLINFO_HTTP_CODE),
        'cabecalho' => substr($resposta, 0, $tamanho),
        'corpo' => substr($resposta, $tamanho)
    ];
}

/**
 * Extrai o token de um formulário retornado pela aplicação.
 * @param array $resposta Resposta retornada por requisitar().
 * @return string Token encontrado; lança exceção se não existir.
 */
function tokenDaPagina($resposta)
{
    preg_match('/name="token" value="([a-f0-9]+)"/', $resposta['corpo'], $resultado);

    if (!isset($resultado[1])) {
        throw new RuntimeException('Token não encontrado na página.');
    }

    return $resultado[1];
}

/**
 * Inicia uma nova sessão e autentica um usuário de teste.
 * @param string $email E-mail do usuário.
 * @param string $senha Senha do usuário.
 * @return string Token da sessão autenticada.
 */
function entrar($email, $senha)
{
    global $cliente;

    curl_setopt($cliente, CURLOPT_COOKIELIST, 'ALL');
    $token = tokenDaPagina(requisitar('index.php'));
    $resposta = requisitar('index.php', [
        'token' => $token,
        'email' => $email,
        'senha' => $senha
    ]);

    conferir($resposta['status'] === 303, 'Login de ' . $email);
    conferir(strpos($resposta['cabecalho'], 'views/dashdark.php') !== false, 'DashDark é a página de entrada');

    return tokenDaPagina(requisitar('views/dashboard.php'));
}

/**
 * Lê o JSON com os dados calculados pelo dashboard.
 * @param array $resposta HTML retornado pela página do dashboard.
 * @return array Dados das quatro primeiras usinas.
 */
function dadosGraficos($resposta)
{
    preg_match('/id="dados-graficos">(.*?)<\/script>/s', $resposta['corpo'], $resultado);

    return json_decode($resultado[1], true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Extrai a série diária e os filtros do novo dashboard.
 * @param array $resposta Resposta HTTP da página DashDark.
 * @return array Dados usados pelos quatro gráficos sincronizados.
 */
function dadosDashDark($resposta)
{
    preg_match('/id="dados-dark" type="application\/json">(.*?)<\/script>/s', $resposta['corpo'], $resultado);

    return json_decode($resultado[1], true, 512, JSON_THROW_ON_ERROR);
}

try {
    $pdo->exec("CREATE DATABASE `$banco` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$banco`");
    $pdo->exec(file_get_contents($raiz . '/public_html/schema.sql'));

    putenv('DB_NAME=' . $banco);
    putenv('BASE_URL=/');

    $servidor = proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:18763', '-t', $raiz . '/public_html'],
        [
            0 => ['pipe', 'r'],
            1 => ['file', __DIR__ . '/integration-server.log', 'w'],
            2 => ['file', __DIR__ . '/integration-server.log', 'a']
        ],
        $pipes,
        $raiz,
        null,
        ['bypass_shell' => true]
    );

    if (!is_resource($servidor)) {
        throw new RuntimeException('Não foi possível iniciar o servidor de testes.');
    }

    fclose($pipes[0]);

    for ($tentativa = 0; $tentativa < 30; $tentativa++) {
        $socket = @fsockopen('127.0.0.1', 18763, $codigo, $mensagem, 0.1);

        if ($socket) {
            fclose($socket);
            break;
        }

        usleep(100000);
    }

    conferir(requisitar('index.php')['status'] === 200, 'Página de login');
    conferir(requisitar('views/usinas/index_usinas.php')['status'] === 303, 'Consulta exige login');
    conferir(requisitar('views/usinas/usinas.php')['status'] === 303, 'Formulário exige login');
    conferir(requisitar('views/dashdark.php')['status'] === 303, 'DashDark exige login');

    $token = tokenDaPagina(requisitar('index.php'));
    $resposta = requisitar('index.php', ['token' => $token, 'email' => "' OR 1=1 --", 'senha' => 'admin']);
    conferir(strpos($resposta['corpo'], 'incorretos') !== false, 'SQL injection não autentica');
    conferir(requisitar('index.php', ['email' => 'admin@usina.com', 'senha' => 'admin'])['status'] === 403, 'Login exige token');

    $token = entrar('admin@usina.com', 'admin');
    conferir(strpos(requisitar('views/dashboard.php')['corpo'], 'Nenhuma usina') !== false, 'Dashboard vazio');
    conferir(strpos(requisitar('views/dashdark.php')['corpo'], 'Sua operação começa aqui') !== false, 'DashDark sem usinas');
    conferir(requisitar('logout.php')['status'] === 405, 'Logout exige POST');

    $cadastros = [
        'usuarios' => [
            'nome' => 'Consulta', 'email' => 'consulta@example.com', 'senha' => 'SenhaTeste123',
            'pode_editar' => 'N', 'pode_excluir' => 'N'
        ],
        'usinas' => [
            'nome' => 'Usina <script>alert(1)</script>', 'tipo' => 'Fotovoltaica',
            'capacidade_kw' => '100.50', 'localizacao' => 'São Paulo', 'status' => 'Ativa'
        ],
        'tecnicos' => [
            'nome' => 'Técnico teste', 'cpf' => '529.982.247-25',
            'especialidade' => 'Elétrica', 'telefone' => '11999999999'
        ],
        'producao' => [
            'usina_id' => '1', 'data_producao' => '2026-01-02', 'kwh_gerado' => '120.50'
        ],
        'categoria' => [
            'descricao_categoria' => 'Elétrica', 'Centro_de_custo' => '123'
        ],
        'despesas' => [
            'usina_id' => '1', 'data' => '2026-01-02', 'valor_despesa' => '45.20',
            'descricao' => 'Cabos', 'categoria' => '1', 'tecnico' => '1'
        ],
        'horas_extras' => [
            'usina_id' => '1', 'tecnico_id' => '1', 'data' => '2026-01-02',
            'quantidade_horas' => '2.50', 'valor_total' => '80.00'
        ],
        'diarias' => [
            'usina_id' => '1', 'tecnico_id' => '1', 'data' => '2026-01-02',
            'valor_diaria' => '150.00', 'descricao' => 'Visita técnica'
        ]
    ];

    foreach ($cadastros as $modulo => $campos) {
        $formulario = 'views/' . $modulo . '/' . $modulo . '.php';
        $listagem = 'views/' . $modulo . '/index_' . $modulo . '.php';
        $id = $modulo === 'usuarios' ? 2 : 1;

        conferir(requisitar($formulario)['status'] === 200, 'Abrir inclusão de ' . $modulo);
        conferir(requisitar($formulario, ['token' => $token] + $campos)['status'] === 303, 'Incluir ' . $modulo);
        conferir(requisitar($listagem)['status'] === 200, 'Listar ' . $modulo);
        conferir(requisitar($formulario . '?id=' . $id)['status'] === 200, 'Abrir edição de ' . $modulo);

        $resposta = requisitar($formulario . '?id=' . $id, ['token' => $token] + $campos);
        conferir($resposta['status'] === 303, 'Editar ' . $modulo);
    }

    $html = requisitar('views/usinas/index_usinas.php')['corpo'];
    $formDespesa = requisitar('views/despesas/despesas.php?id=1')['corpo'];
    conferir(strpos($formDespesa, '<select id="categoria"') !== false, 'Categoria é um combobox');
    conferir(strpos($formDespesa, 'Elétrica') !== false && strpos($formDespesa, 'CC 123') !== false, 'Combobox usa descrição e centro de custo');
    conferir(strpos($formDespesa, '<select id="tecnico"') !== false, 'Despesa permite selecionar técnico');
    $listaDespesas = requisitar('views/despesas/index_despesas.php')['corpo'];
    conferir(strpos($listaDespesas, 'Técnico teste') !== false && strpos($listaDespesas, 'Elétrica') !== false, 'Listagem mostra nomes dos vínculos');
    foreach (['categoria', 'tecnico'] as $campoInvalido) {
        $campos = ['token' => $token, $campoInvalido => '999'] + $cadastros['despesas'];
        conferir(requisitar('views/despesas/despesas.php', $campos)['status'] === 422, 'Rejeita vínculo inexistente: ' . $campoInvalido);
    }
    $campos = ['token' => $token, 'Centro_de_custo' => '2147483648'] + $cadastros['categoria'];
    conferir(requisitar('views/categoria/categoria.php', $campos)['status'] === 422, 'Centro de custo respeita INT');
    $resposta = requisitar('views/categoria/index_categoria.php', ['token' => $token, 'id' => '1']);
    conferir(strpos($resposta['cabecalho'], 'error=1') !== false, 'Categoria vinculada não pode ser excluída');
    $resposta = requisitar('views/tecnicos/index_tecnicos.php', ['token' => $token, 'id' => '1']);
    conferir(strpos($resposta['cabecalho'], 'error=1') !== false, 'Técnico vinculado não pode ser excluído');
    $campos = ['token' => $token, 'tecnico' => ''] + $cadastros['despesas'];
    conferir(requisitar('views/despesas/despesas.php?id=1', $campos)['status'] === 303, 'Despesa admite técnico não informado');
    conferir($pdo->query('SELECT tecnico FROM despesas WHERE id = 1')->fetchColumn() === null, 'Técnico ausente é NULL');
    conferir(requisitar('views/despesas/despesas.php?id=1', ['token' => $token] + $cadastros['despesas'])['status'] === 303, 'Vínculo do técnico pode ser atualizado');
    foreach (['views/dashboard.php', 'views/dashdark.php'] as $paginaPainel) {
        $painel = requisitar($paginaPainel)['corpo'];
        conferir(strpos($painel, 'views/horas_extras/') === false && strpos($painel, 'views/diarias/') === false, 'Menus sem Horas Extras e Diárias: ' . $paginaPainel);
        conferir(strpos($painel, 'views/categoria/index_categoria.php') !== false, 'Menu de Categorias: ' . $paginaPainel);
    }
    conferir(strpos($html, '<script>alert(1)</script>') === false && strpos($html, '&lt;script&gt;') !== false, 'Texto de tabela protegido contra XSS');
    conferir(requisitar('views/usinas/index_usinas.php', ['id' => '1'])['status'] === 403, 'Exclusão exige token');
    conferir(requisitar('views/usinas/usinas.php?id=abc')['status'] === 400, 'ID inválido');
    conferir(requisitar('views/usinas/usinas.php?id=999')['status'] === 404, 'Registro inexistente');

    foreach (['-1', '1e9', '99999999999', '0.001'] as $numero) {
        $campos = ['token' => $token, 'capacidade_kw' => $numero] + $cadastros['usinas'];
        conferir(requisitar('views/usinas/usinas.php', $campos)['status'] === 422, 'Rejeitar número: ' . $numero);
    }

    $campos = ['token' => $token, 'tipo' => 'Outro'] + $cadastros['usinas'];
    conferir(requisitar('views/usinas/usinas.php', $campos)['status'] === 422, 'Tipo inválido');
    $campos = ['token' => $token, 'cpf' => '11111111111'] + $cadastros['tecnicos'];
    conferir(requisitar('views/tecnicos/tecnicos.php', $campos)['status'] === 422, 'CPF inválido');
    $campos = ['token' => $token, 'data_producao' => '2026-02-30'] + $cadastros['producao'];
    conferir(requisitar('views/producao/producao.php', $campos)['status'] === 422, 'Data inexistente');
    $campos = ['token' => $token, 'usina_id' => '999'] + $cadastros['producao'];
    conferir(requisitar('views/producao/producao.php', $campos)['status'] === 422, 'Usina inexistente');
    $campos = ['token' => $token] + $cadastros['producao'];
    conferir(requisitar('views/producao/producao.php', $campos)['status'] === 422, 'Produção duplicada');

    $hashAntes = $pdo->query('SELECT senha_hash FROM usuarios WHERE id = 2')->fetchColumn();
    $campos = ['token' => $token, 'senha' => ''] + $cadastros['usuarios'];
    conferir(requisitar('views/usuarios/usuarios.php?id=2', $campos)['status'] === 303, 'Senha em branco na edição');
    conferir($hashAntes === $pdo->query('SELECT senha_hash FROM usuarios WHERE id = 2')->fetchColumn(), 'Senha em branco mantém o hash');

    $resposta = requisitar('views/usinas/index_usinas.php', ['token' => $token, 'id' => '1']);
    conferir(strpos($resposta['cabecalho'], 'error=1') !== false, 'FK impede excluir usina com lançamentos');
    $campos = ['token' => $token, 'nome' => 'Administrador', 'email' => 'admin@usina.com', 'senha' => '', 'pode_editar' => 'N', 'pode_excluir' => 'N'];
    conferir(requisitar('views/usuarios/usuarios.php?id=1', $campos)['status'] === 422, 'Preservação do último gestor');
    $resposta = requisitar('views/usuarios/index_usuarios.php', ['token' => $token, 'id' => '1']);
    conferir(strpos($resposta['cabecalho'], 'error=1') !== false, 'Autoexclusão bloqueada');

    $pdo->exec("INSERT INTO horas_extras (usina_id, tecnico_id, data, quantidade_horas, valor_total) VALUES (1, 1, '2026-01-02', 1.50, 40)");
    $pdo->exec("INSERT INTO diarias (usina_id, tecnico_id, data, valor_diaria, descricao) VALUES (1, 1, '2026-01-02', 50, 'Segunda diária')");
    $pdo->exec("INSERT INTO categoria (descricao_categoria, Centro_de_custo) VALUES ('HORAS EXTRAS', 200)");
    $pdo->exec("INSERT INTO despesas (usina_id, data, valor_despesa, descricao, categoria, tecnico) VALUES (1, '2026-01-02', 30, 'Horas extras teste A', 2, 1), (1, '2026-01-02', 50, 'Horas extras teste B', 2, 1)");

    $pdo->exec("INSERT INTO categoria (descricao_categoria, Centro_de_custo) VALUES ('DIARIAS', 100)");
    $pdo->exec("INSERT INTO despesas (usina_id, data, valor_despesa, descricao, categoria, tecnico) VALUES (1, '2026-01-02', 200, 'Diária na nova categoria', 3, 1)");
    for ($id = 2; $id <= 5; $id++) {
        $pdo->exec("INSERT INTO usinas (nome, tipo, capacidade_kw, localizacao) VALUES ('Usina $id', 'Hidrelétrica', 100, 'Local')");
    }

    $resposta = requisitar('views/dashboard.php?inicio=2026-01-01&fim=2026-01-03');
    $graficos = dadosGraficos($resposta);
    conferir(count($graficos) === 4 && $graficos[3]['id'] === 4, 'Seleção das quatro primeiras usinas');
    conferir($graficos[0]['energia'] === [0, 120.5, 0], 'Energia e dias sem lançamento');
    conferir($graficos[0]['horas'] === [0, 80, 0], 'Horas extras usa valores da categoria em reais');
    conferir($graficos[0]['diarias'] === [0, 200, 0], 'Diárias vêm exclusivamente da categoria DIARIAS');
    conferir($graficos[0]['despesas'] === [0, 325.2, 0], 'Despesas totais incluem horas extras uma única vez');
    conferir(substr_count($resposta['corpo'], '<canvas') === 13, 'Comparação geral e três gráficos por usina');
    preg_match('/<script type="application\/json" id="dados-todas-usinas">(.*?)<\/script>/s', $resposta['corpo'], $comparacaoJson);
    $comparacao = json_decode($comparacaoJson[1], true);
    conferir(count($comparacao['usinas']) === 5, 'Comparação clara inclui todas as cinco usinas');
    conferir($comparacao['usinas'][0]['energia'] === [0, 120.5, 0] && $comparacao['usinas'][4]['energia'] === [0, 0, 0], 'Comparação clara mantém valores e zeros por usina');
    $graficos = dadosGraficos(requisitar('views/dashboard.php?inicio=2025-12-01&fim=2026-03-01'));
    conferir($graficos[0]['rotulos'] === ['12/2025', '01/2026', '02/2026', '03/2026'], 'Agrupamento mensal');
    conferir($graficos[0]['energia'] === [0, 120.5, 0, 0], 'Total mensal');
    $resposta = requisitar('views/dashboard.php?inicio=2026-02-30&fim=2026-01-01');
    conferir(strpos($resposta['corpo'], 'Informe um período válido') !== false, 'Período inválido');

    $token = entrar('consulta@example.com', 'SenhaTeste123');
    $respostaDark = requisitar('views/dashdark.php?usina_id=1&inicio=2026-01-01&fim=2026-01-03');
    $dark = dadosDashDark($respostaDark);
    conferir($respostaDark['status'] === 200, 'DashDark disponível para consulta');
    conferir($dark['usinaId'] === 1 && count($dark['serie']) === 365, 'DashDark carrega a janela anual da usina selecionada');
    $porData = array_column($dark['serie'], null, 'data');
    conferir($porData['2026-01-02']['energia'] === 120.5, 'Produção do DashDark');
    conferir($porData['2026-01-02']['horas'] === 80, 'Horas extras do DashDark em reais');
    conferir($porData['2026-01-02']['despesas'] === 325.2, 'Despesas do DashDark incluem a categoria de horas extras');
    conferir($porData['2026-01-02']['diarias'] === 200, 'Diárias do DashDark vêm da categoria');
    conferir($porData['2026-01-01']['energia'] === 0, 'Dia sem produção no DashDark');
    conferir(count($dark['producao']) === 1, 'Tabela de produção da usina selecionada');
    conferir(substr_count($respostaDark['corpo'], '<canvas') === 4, 'Quatro gráficos no DashDark');
    conferir(count($dark['todasUsinas']) === 5 && $dark['todasUsinas'][0]['valores']['2026-01-02'] === 120.5, 'Comparação inclui todas as usinas e a produção correta');
    conferir(strpos($respostaDark['corpo'], '<script>alert(1)</script>') === false, 'Nome da usina escapado no DashDark');
    $outra = dadosDashDark(requisitar('views/dashdark.php?usina_id=5&inicio=2026-01-01&fim=2026-01-03'));
    conferir($outra['usinaId'] === 5 && array_sum(array_column($outra['serie'], 'energia')) === 0, 'Combobox inclui quinta usina sem misturar dados');
    conferir($outra['todasUsinas'] === $dark['todasUsinas'], 'Comparação de todas as usinas independe do combobox');
    $ano = dadosDashDark(requisitar('views/dashdark.php?usina_id=1&inicio=2020-01-01&fim=2026-01-03'));
    conferir($ano['inicio'] === $ano['limiteInicial'] && count($ano['serie']) === 365, 'Servidor limita o período a 365 dias');
    $bissexto = dadosDashDark(requisitar('views/dashdark.php?usina_id=1&inicio=2024-01-01&fim=2024-12-31'));
    conferir($bissexto['inicio'] === '2024-01-02' && count($bissexto['serie']) === 365, 'Limite de 365 dias em ano bissexto');
    conferir(requisitar('views/dashdark.php?usina_id=999')['status'] === 400, 'Usina inexistente rejeitada no DashDark');
    conferir(requisitar('views/dashdark.php?usina_id=1%20OR%201=1')['status'] === 400, 'ID malformado rejeitado no DashDark');
    conferir(strpos(requisitar('views/dashdark.php?inicio=2026-02-30')['corpo'], 'Período inválido') !== false, 'Datas inválidas no DashDark');
    conferir(strpos(requisitar('index.php')['cabecalho'], 'views/dashdark.php') !== false, 'Sessão existente abre DashDark');
    conferir(strpos(requisitar('views/dashboard.php')['corpo'], 'views/dashdark.php') !== false, 'Menu clássico oferece DashDark');
    conferir(strpos(requisitar('views/usinas/index_usinas.php')['corpo'], 'Incluir Novo') === false, 'Consulta não exibe inclusão');
    conferir(requisitar('views/usinas/usinas.php?id=1')['status'] === 403, 'Consulta não abre edição');
    conferir(requisitar('views/usinas/usinas.php?id=1', ['token' => $token] + $cadastros['usinas'])['status'] === 403, 'Consulta não grava por POST');
    conferir(requisitar('views/usinas/index_usinas.php', ['token' => $token, 'id' => '1'])['status'] === 403, 'Consulta não exclui por POST');
    conferir(requisitar('views/usuarios/index_usuarios.php')['status'] === 403, 'Consulta não gerencia usuários');

    $pdo->exec("UPDATE usuarios SET pode_editar = 'S' WHERE id = 2");
    conferir(requisitar('views/usinas/usinas.php?id=1')['status'] === 200, 'Permissão atualizada sem novo login');
    conferir(requisitar('views/usuarios/usuarios.php?id=2', ['token' => $token] + $cadastros['usuarios'])['status'] === 403, 'Editor não eleva permissões');
    $pdo->exec("UPDATE usuarios SET pode_editar = 'N', pode_excluir = 'S' WHERE id = 2");
    conferir(requisitar('views/usinas/index_usinas.php', ['token' => $token, 'id' => '5'])['status'] === 303, 'Usuário com permissão somente de exclusão');

    $token = entrar('admin@usina.com', 'admin');
    $pdo->exec('DELETE FROM horas_extras WHERE id > 1');
    $pdo->exec('DELETE FROM diarias WHERE id > 1');
    $pdo->exec('DELETE FROM despesas WHERE id > 1');
    $pdo->exec('DELETE FROM categoria WHERE id_categoria > 1');

    foreach (['producao', 'despesas', 'categoria', 'horas_extras', 'diarias', 'tecnicos', 'usinas', 'usuarios'] as $modulo) {
        $id = $modulo === 'usuarios' ? '2' : '1';
        $resposta = requisitar('views/' . $modulo . '/index_' . $modulo . '.php', ['token' => $token, 'id' => $id]);
        conferir($resposta['status'] === 303 && strpos($resposta['cabecalho'], 'msg=sucesso') !== false, 'Excluir ' . $modulo);
    }

    conferir(requisitar('logout.php', ['token' => $token])['status'] === 303, 'Logout');
    conferir(requisitar('views/dashboard.php')['status'] === 303, 'Sessão encerrada');

    // Confere também o Apache real, sem cadastrar dados no banco gerene_local.
    $base = 'http://localhost/gerene';
    $token = entrar('admin@usina.com', 'admin');
    conferir(requisitar('views/dashboard.php')['status'] === 200, 'Dashboard no Apache do XAMPP');
    conferir(requisitar('views/dashdark.php')['status'] === 200, 'DashDark no Apache do XAMPP');

    foreach (array_keys($cadastros) as $modulo) {
        conferir(requisitar('views/' . $modulo . '/index_' . $modulo . '.php')['status'] === 200, 'Listagem no Apache: ' . $modulo);
    }

    conferir(requisitar('schema.sql')['status'] === 403, 'Apache bloqueia o arquivo SQL');
    conferir(requisitar('config.php')['status'] === 403, 'Apache bloqueia a configuração');
    conferir(requisitar('assets/js/main.js')['status'] === 200, 'JavaScript no Apache');
    conferir(requisitar('assets/css/custom.css')['status'] === 200, 'CSS no Apache');
    requisitar('logout.php', ['token' => $token]);

    echo PHP_EOL . $quantidade . ' verificações aprovadas.' . PHP_EOL;
} finally {
    if (is_resource($servidor)) {
        proc_terminate($servidor);
        proc_close($servidor);
    }

    curl_close($cliente);
    $pdo->exec("DROP DATABASE IF EXISTS `$banco`");
}
