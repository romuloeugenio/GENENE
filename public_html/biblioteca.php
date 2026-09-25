<?php

require_once __DIR__ . '/db.php';

/**
 * Converte um valor em texto seguro para exibição no HTML.
 * @param mixed $valor Texto ou número que será exibido.
 * @return string Texto com os caracteres especiais escapados.
 */
function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Lê um campo de GET ou POST, removendo espaços nas extremidades.
 * @param array $origem Normalmente $_GET ou $_POST.
 * @param string $campo Nome do campo recebido.
 * @param string $padrao Valor para campo ausente ou que não seja texto.
 * @return string Valor recebido ou valor padrão.
 */
function lerCampo($origem, $campo, $padrao = '')
{
    if (isset($origem[$campo]) && is_string($origem[$campo])) {
        return trim($origem[$campo]);
    }

    return $padrao;
}

/**
 * Monta um endereço dentro da aplicação.
 * @param string $caminho Caminho relativo à pasta public_html.
 * @return string Endereço da página ou do arquivo.
 */
function endereco($caminho)
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($caminho, '/');
}

/**
 * Envia o navegador para outra página do sistema.
 * @param string $caminho Caminho relativo à pasta public_html.
 * @return void Envia o redirecionamento e encerra a execução.
 */
function redirecionar($caminho)
{
    header('Location: ' . endereco($caminho), true, 303);
    exit();
}

/**
 * Obtém o identificador do registro na URL.
 * Parâmetros: nenhum. Lê id de $_GET.
 * @return int Zero para inclusão ou ID positivo; rejeita valores inválidos.
 */
function obterId()
{
    $id = filter_var(lerCampo($_GET, 'id', '0'), FILTER_VALIDATE_INT);

    if ($id === false || $id < 0 || $id > 4294967295) {
        http_response_code(400);
        exit('Identificador inválido.');
    }

    return $id;
}

/**
 * Cria ou recupera o token de proteção dos formulários.
 * Parâmetros: nenhum. Utiliza $_SESSION.
 * @return string Token aleatório de 64 caracteres.
 */
function obterToken()
{
    if (empty($_SESSION['token'])) {
        $_SESSION['token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['token'];
}

/**
 * Confere o token enviado por um formulário.
 * Parâmetros: nenhum. Lê token de $_POST e compara com a sessão.
 * @return void Continua ou encerra com HTTP 403 se o token não conferir.
 */
function validarToken()
{
    if (!hash_equals(obterToken(), lerCampo($_POST, 'token'))) {
        http_response_code(403);
        exit('Formulário expirado ou inválido. Atualize a página e tente novamente.');
    }
}

/**
 * Verifica o login, a inatividade e as permissões atuais no banco.
 * Parâmetros: nenhum. Utiliza a sessão e conectarBanco().
 * @return void Continua autenticado ou redireciona para o login.
 */
function exigirLogin()
{
    if (empty($_SESSION['usuario_id'])) {
        redirecionar('index.php');
    }

    if (time() - ($_SESSION['ultima_atividade'] ?? 0) > 1800) {
        $_SESSION = [];
        session_regenerate_id(true);
        redirecionar('index.php');
    }

    $pdo = conectarBanco();
    $consulta = $pdo->prepare('SELECT id, nome, pode_editar, pode_excluir FROM usuarios WHERE id = ?');
    $consulta->execute([$_SESSION['usuario_id']]);
    $usuario = $consulta->fetch();

    if (!$usuario) {
        $_SESSION = [];
        session_regenerate_id(true);
        redirecionar('index.php');
    }

    $_SESSION['nome'] = $usuario['nome'];
    $_SESSION['pode_editar'] = $usuario['pode_editar'];
    $_SESSION['pode_excluir'] = $usuario['pode_excluir'];
    $_SESSION['ultima_atividade'] = time();
}

/**
 * Verifica a permissão para incluir e editar registros.
 * Parâmetros: nenhum. Lê pode_editar da sessão.
 * @return bool Verdadeiro quando a permissão é S.
 */
function podeEditar()
{
    return ($_SESSION['pode_editar'] ?? 'N') === 'S';
}

/**
 * Verifica a permissão para excluir registros.
 * Parâmetros: nenhum. Lê pode_excluir da sessão.
 * @return bool Verdadeiro quando a permissão é S.
 */
function podeExcluir()
{
    return ($_SESSION['pode_excluir'] ?? 'N') === 'S';
}

/**
 * Bloqueia inclusões e edições sem permissão.
 * Parâmetros: nenhum. Utiliza podeEditar().
 * @return void Continua ou encerra a página com HTTP 403.
 */
function exigirEdicao()
{
    if (!podeEditar()) {
        http_response_code(403);
        exit('Você não tem permissão para incluir ou editar registros.');
    }
}

/**
 * Bloqueia exclusões sem permissão.
 * Parâmetros: nenhum. Utiliza podeExcluir().
 * @return void Continua ou encerra a página com HTTP 403.
 */
function exigirExclusao()
{
    if (!podeExcluir()) {
        http_response_code(403);
        exit('Você não tem permissão para excluir registros.');
    }
}

/**
 * Restringe a gestão de usuários a quem possui as duas permissões.
 * Parâmetros: nenhum. Utiliza podeEditar() e podeExcluir().
 * @return void Continua ou encerra a página com HTTP 403.
 */
function exigirGestor()
{
    if (!podeEditar() || !podeExcluir()) {
        http_response_code(403);
        exit('A gestão de usuários exige permissão para editar e excluir.');
    }
}

/**
 * Verifica se uma data existe e pode ser gravada no MySQL.
 * @param string $valor Data no formato AAAA-MM-DD.
 * @return bool Verdadeiro para uma data válida entre os anos 1000 e 9999.
 */
function dataValida($valor)
{
    $data = DateTime::createFromFormat('!Y-m-d', $valor);

    return $data !== false
        && $data->format('Y-m-d') === $valor
        && $valor >= '1000-01-01'
        && $valor <= '9999-12-31';
}

/**
 * Valida um valor positivo ou zero para uma coluna DECIMAL(12,2).
 * @param string $valor Número sem separador de milhar e com ponto decimal.
 * @return bool Verdadeiro para até dez inteiros e duas casas decimais.
 */
function numeroValido($valor)
{
    return preg_match('/^\d{1,10}(\.\d{1,2})?$/D', $valor) === 1;
}

/**
 * Confere os dois dígitos verificadores de um CPF.
 * @param string $cpf CPF com onze números, sem pontuação.
 * @return bool Verdadeiro quando o CPF é válido.
 */
function cpfValido($cpf)
{
    if (!preg_match('/^\d{11}$/D', $cpf) || preg_match('/^(\d)\1{10}$/D', $cpf)) {
        return false;
    }

    for ($tamanho = 9; $tamanho <= 10; $tamanho++) {
        $soma = 0;

        for ($posicao = 0; $posicao < $tamanho; $posicao++) {
            $soma += (int) $cpf[$posicao] * ($tamanho + 1 - $posicao);
        }

        $resto = $soma % 11;
        $digito = $resto < 2 ? 0 : 11 - $resto;

        if ((int) $cpf[$tamanho] !== $digito) {
            return false;
        }
    }

    return true;
}

/**
 * Confere se uma usina, um técnico ou uma categoria existe.
 * @param PDO $pdo Conexão com o banco.
 * @param string $tabela Somente usinas, tecnicos ou categoria são aceitos.
 * @param string $id Identificador recebido pelo formulário.
 * @return bool Verdadeiro quando o registro existe.
 */
function vinculoExiste($pdo, $tabela, $id)
{
    if (!in_array($tabela, ['usinas', 'tecnicos', 'categoria'], true) || !ctype_digit($id)) {
        return false;
    }

    $chave = $tabela === 'categoria' ? 'id_categoria' : 'id';
    $consulta = $pdo->prepare('SELECT ' . $chave . ' FROM ' . $tabela . ' WHERE ' . $chave . ' = ?');
    $consulta->execute([$id]);

    return $consulta->fetch() !== false;
}

/**
 * Traduz um erro SQL para uma mensagem sem detalhes internos do servidor.
 * @param PDOException $erro Exceção gerada pela operação SQL.
 * @return string Mensagem para exibir ao usuário.
 */
function mensagemBanco($erro)
{
    error_log($erro->getMessage());

    if ($erro->getCode() === '23000') {
        return 'Há um registro duplicado ou um vínculo que impede esta operação.';
    }

    return 'Não foi possível concluir a operação no banco de dados.';
}

/**
 * Formata um número no padrão brasileiro.
 * @param mixed $valor Número que será exibido.
 * @return string Número com duas casas decimais.
 */
function formatarNumero($valor)
{
    return number_format((float) $valor, 2, ',', '.');
}

/**
 * Formata uma data do MySQL para exibição.
 * @param string $valor Data no formato AAAA-MM-DD.
 * @return string Data DD/MM/AAAA ou texto vazio para uma data inválida.
 */
function formatarData($valor)
{
    if (!dataValida($valor)) {
        return '';
    }

    return date('d/m/Y', strtotime($valor));
}
