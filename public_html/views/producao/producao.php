<?php

require_once __DIR__ . '/../../biblioteca.php';

exigirLogin();
exigirEdicao();

$pdo = conectarBanco();
$id = obterId();
$erros = [];

// Valores iniciais para um novo cadastro.
$usina_id = '';
$data_producao = date('Y-m-d');
$kwh_gerado = '';

// Carrega os dados quando o usuário escolhe Editar.
if ($id > 0) {
    $consulta = $pdo->prepare('SELECT * FROM producao_energia WHERE id = ?');
    $consulta->execute([$id]);
    $registro = $consulta->fetch();

    if (!$registro) {
        http_response_code(404);
        exit('Registro não encontrado.');
    }

    $usina_id = $registro['usina_id'];
    $data_producao = $registro['data_producao'];
    $kwh_gerado = $registro['kwh_gerado'];
}

// Recebe e valida os campos enviados pelo formulário.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarToken();

    $usina_id = lerCampo($_POST, 'usina_id');
    $data_producao = lerCampo($_POST, 'data_producao');
    $kwh_gerado = lerCampo($_POST, 'kwh_gerado');

    if (!vinculoExiste($pdo, 'usinas', $usina_id)) {
        $erros[] = 'Usina: selecione um cadastro existente.';
    }

    if (!dataValida($data_producao)) {
        $erros[] = 'Data de produção: informe uma data válida.';
    }

    if (!numeroValido($kwh_gerado)) {
        $erros[] = 'Energia gerada (kWh): informe um número positivo ou zero, com até duas casas decimais.';
    }

    // Só grava no banco se não houver erros de preenchimento.
    if (empty($erros)) {
        try {
            if ($id > 0) {
                $consulta = $pdo->prepare(
                    'UPDATE producao_energia
                     SET usina_id = ?, data_producao = ?, kwh_gerado = ?
                     WHERE id = ?'
                );
                $consulta->execute([$usina_id, $data_producao, $kwh_gerado, $id]);
            } else {
                $consulta = $pdo->prepare(
                    'INSERT INTO producao_energia (usina_id, data_producao, kwh_gerado)
                     VALUES (?, ?, ?)'
                );
                $consulta->execute([$usina_id, $data_producao, $kwh_gerado]);
            }

            redirecionar('views/producao/index_producao.php?msg=sucesso');
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

$titulo = 'Produção de Energia - ' . ($id > 0 ? 'Editar' : 'Incluir');
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
                            <label for="usina_id">Usina</label>
                            <select id="usina_id" name="usina_id" required>
                                <option value="">Selecione</option>
                                <?php foreach ($usinas as $opcao): ?>
                                    <option value="<?= escapar($opcao['id']) ?>" <?= (string) $usina_id === (string) $opcao['id'] ? 'selected' : '' ?>>
                                        <?= escapar($opcao['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($usinas)): ?>
                                <small>Cadastre uma usina antes de incluir este lançamento.</small>
                            <?php endif; ?>
                        </div>

                        <div class="campo">
                            <label for="data_producao">Data de produção</label>
                            <input type="date" id="data_producao" name="data_producao"
                                   value="<?= escapar($data_producao) ?>" min="1000-01-01" max="9999-12-31" required>
                        </div>

                        <div class="campo">
                            <label for="kwh_gerado">Energia gerada (kWh)</label>
                            <input type="number" id="kwh_gerado" name="kwh_gerado"
                                   value="<?= escapar($kwh_gerado) ?>" min="0" max="9999999999.99" step="0.01" required>
                        </div>

                    </div>

                    <div class="acoes">
                        <button type="submit" class="botao">Gravar</button>
                        <a class="botao secundario" href="index_producao.php">Voltar para pesquisa</a>
                    </div>
                </form>
            </section>
<?php require __DIR__ . '/../footer.php'; ?>
