<?php

require_once __DIR__ . '/../../biblioteca.php';

exigirLogin();
exigirEdicao();

$pdo = conectarBanco();
$id_usina_original = lerCampo($_GET, 'id_usina');
$mes_original = lerCampo($_GET, 'mes_estimado');
$emEdicao = $id_usina_original !== '' || $mes_original !== '';
$erros = [];
$id_usina = '';
$mes_estimado = date('Y-m');
$producao = '';

if ($emEdicao) {
    $idUsinaValido = filter_var($id_usina_original, FILTER_VALIDATE_INT);
    if ($idUsinaValido === false || $idUsinaValido < 1 || $idUsinaValido > 4294967295
        || !preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/D', $mes_original)
        || !dataValida($mes_original)) {
        http_response_code(400);
        exit('Identificador ou mês estimado inválido.');
    }

    $consulta = $pdo->prepare(
        'SELECT id_usina, mes_estimado, producao
         FROM producao_estimada_usina
         WHERE id_usina = ? AND mes_estimado = ?'
    );
    $consulta->execute([$idUsinaValido, $mes_original]);
    $registro = $consulta->fetch();

    if (!$registro) {
        http_response_code(404);
        exit('Registro não encontrado.');
    }

    $id_usina = $registro['id_usina'];
    $mes_estimado = substr($registro['mes_estimado'], 0, 7);
    $producao = $registro['producao'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarToken();

    $id_usina = lerCampo($_POST, 'id_usina');
    $mes_estimado = lerCampo($_POST, 'mes_estimado');
    $producao = lerCampo($_POST, 'producao');
    $producao_normalizada = preg_match('/^\d{1,10},\d{1,2}$/D', $producao) === 1
        ? str_replace(',', '.', $producao)
        : $producao;
    $mes_estimado_data = $mes_estimado . '-01';

    if (!vinculoExiste($pdo, 'usinas', $id_usina)) {
        $erros[] = 'Usina: selecione um cadastro existente.';
    }

    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $mes_estimado)
        || !dataValida($mes_estimado_data)) {
        $erros[] = 'Mês estimado: selecione um mês válido.';
    }

    if (!numeroValido($producao_normalizada)) {
        $erros[] = 'Produção: informe um número positivo ou zero, com até duas casas decimais.';
    }

    if (empty($erros)) {
        try {
            if ($emEdicao) {
                $consulta = $pdo->prepare(
                    'UPDATE producao_estimada_usina
                     SET id_usina = ?, mes_estimado = ?, producao = ?
                     WHERE id_usina = ? AND mes_estimado = ?'
                );
                $consulta->execute([$id_usina, $mes_estimado_data, $producao_normalizada, $id_usina_original, $mes_original]);
            } else {
                $consulta = $pdo->prepare(
                    'INSERT INTO producao_estimada_usina (id_usina, mes_estimado, producao)
                     VALUES (?, ?, ?)'
                );
                $consulta->execute([$id_usina, $mes_estimado_data, $producao_normalizada]);
            }

            redirecionar('views/producao_estimada/index_producao_estimada.php?msg=sucesso');
        } catch (PDOException $excecao) {
            $erros[] = mensagemBanco($excecao);
        } catch (RuntimeException $excecao) {
            $erros[] = $excecao->getMessage();
        }
    }

    if (!empty($erros)) {
        http_response_code(422);
    }
}

$consulta = $pdo->query('SELECT id, nome FROM usinas ORDER BY nome');
$usinas = $consulta->fetchAll();

$titulo = 'Produção Estimada - ' . ($emEdicao ? 'Editar' : 'Incluir');
require __DIR__ . '/../header.php';

?>
            <section class="painel">
                <?php if (!empty($erros)): ?>
                    <div class="aviso erro" role="alert">
                        <ul>
                            <?php foreach ($erros as $erro): ?>
                                <li><?= escapar($erro) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="token" value="<?= escapar(obterToken()) ?>">

                    <div class="campos">
                        <div class="campo">
                            <label for="id_usina">Usina</label>
                            <select id="id_usina" name="id_usina" required>
                                <option value="">Selecione</option>
                                <?php foreach ($usinas as $opcao): ?>
                                    <option value="<?= escapar($opcao['id']) ?>" <?= (string) $id_usina === (string) $opcao['id'] ? 'selected' : '' ?>>
                                        <?= escapar($opcao['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($usinas)): ?>
                                <small>Cadastre uma usina antes de incluir esta estimativa.</small>
                            <?php endif; ?>
                        </div>

                        <div class="campo">
                            <label for="mes_estimado">Mês estimado</label>
                            <input type="month" id="mes_estimado" name="mes_estimado"
                                   value="<?= escapar($mes_estimado) ?>" min="1000-01" max="9999-12" required>
                        </div>

                        <div class="campo">
                            <label for="producao">Produção estimada</label>
                            <input type="text" id="producao" name="producao" inputmode="decimal"
                                   value="<?= escapar($producao) ?>" maxlength="13"
                                   pattern="[0-9]{1,10}([,.][0-9]{1,2})?" required>
                        </div>
                    </div>

                    <div class="acoes">
                        <button type="submit" class="botao">Gravar</button>
                        <a class="botao secundario" href="index_producao_estimada.php">Voltar para pesquisa</a>
                    </div>
                </form>
            </section>
<?php require __DIR__ . '/../footer.php'; ?>
