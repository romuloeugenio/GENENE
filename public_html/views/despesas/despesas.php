<?php

require_once __DIR__ . '/../../biblioteca.php';

exigirLogin();
exigirEdicao();

$pdo = conectarBanco();
$id = obterId();
$erros = [];

// Valores iniciais para um novo cadastro.
$usina_id = '';
$data = date('Y-m-d');
$valor_despesa = '';
$descricao = '';
$categoria = '';
$tecnico = '';

// Carrega os dados quando o usuário escolhe Editar.
if ($id > 0) {
    $consulta = $pdo->prepare('SELECT * FROM despesas WHERE id = ?');
    $consulta->execute([$id]);
    $registro = $consulta->fetch();

    if (!$registro) {
        http_response_code(404);
        exit('Registro não encontrado.');
    }

    $usina_id = $registro['usina_id'];
    $data = $registro['data'];
    $valor_despesa = $registro['valor_despesa'];
    $descricao = $registro['descricao'];
    $categoria = $registro['categoria'];
    $tecnico = $registro['tecnico'] ?? '';
}

// Recebe e valida os campos enviados pelo formulário.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarToken();

    $usina_id = lerCampo($_POST, 'usina_id');
    $data = lerCampo($_POST, 'data');
    $valor_despesa = lerCampo($_POST, 'valor_despesa');
    $valor_despesa_normalizado = preg_match('/^\d{1,10},\d{1,2}$/D', $valor_despesa) === 1
        ? str_replace(',', '.', $valor_despesa)
        : $valor_despesa;
    $descricao = lerCampo($_POST, 'descricao');
    $categoria = lerCampo($_POST, 'categoria');
    $tecnico = lerCampo($_POST, 'tecnico');

    if (!vinculoExiste($pdo, 'usinas', $usina_id)) {
        $erros[] = 'Usina: selecione um cadastro existente.';
    }

    if (!dataValida($data)) {
        $erros[] = 'Data: informe uma data válida.';
    }

    if (!numeroValido($valor_despesa_normalizado)) {
        $erros[] = 'Valor (R$): informe um número positivo ou zero, com até duas casas decimais.';
    }

    if ($descricao === '' || iconv_strlen($descricao, 'UTF-8') > 2000) {
        $erros[] = 'Descrição: preencha até 2000 caracteres.';
    }

    if (!vinculoExiste($pdo, 'categoria', $categoria)) {
        $erros[] = 'Categoria: selecione um cadastro existente.';
    }

    if ($tecnico !== '' && !vinculoExiste($pdo, 'tecnicos', $tecnico)) {
        $erros[] = 'Técnico: selecione um cadastro existente ou deixe sem técnico.';
    }

    // Só grava no banco se não houver erros de preenchimento.
    if (empty($erros)) {
        try {
            if ($id > 0) {
                $consulta = $pdo->prepare(
                    'UPDATE despesas
                     SET usina_id = ?, data = ?, valor_despesa = ?, descricao = ?, categoria = ?, tecnico = ?
                     WHERE id = ?'
                );
                $consulta->execute([$usina_id, $data, $valor_despesa_normalizado, $descricao, $categoria, $tecnico === '' ? null : $tecnico, $id]);
            } else {
                $consulta = $pdo->prepare(
                    'INSERT INTO despesas (usina_id, data, valor_despesa, descricao, categoria, tecnico)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $consulta->execute([$usina_id, $data, $valor_despesa_normalizado, $descricao, $categoria, $tecnico === '' ? null : $tecnico]);
            }

            redirecionar('views/despesas/index_despesas.php?msg=sucesso');
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

$consulta = $pdo->query('SELECT id_categoria, descricao_categoria, Centro_de_custo FROM categoria ORDER BY descricao_categoria');
$categorias = $consulta->fetchAll();

$consulta = $pdo->query('SELECT id, nome FROM tecnicos ORDER BY nome');
$tecnicos = $consulta->fetchAll();

$titulo = 'Despesas - ' . ($id > 0 ? 'Editar' : 'Incluir');
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
                            <label for="data">Data</label>
                            <input type="date" id="data" name="data"
                                   value="<?= escapar($data) ?>" min="1000-01-01" max="9999-12-31" required>
                        </div>

                        <div class="campo">
                            <label for="valor_despesa">Valor (R$)</label>
                            <input type="text" id="valor_despesa" name="valor_despesa" inputmode="decimal"
                                   value="<?= escapar($valor_despesa) ?>" maxlength="13"
                                   pattern="[0-9]{1,10}([,.][0-9]{1,2})?" required>
                        </div>

                        <div class="campo">
                            <label for="descricao">Descrição</label>
                            <textarea id="descricao" name="descricao" rows="3" maxlength="2000" required><?= escapar($descricao) ?></textarea>
                        </div>

                        <div class="campo">
                            <label for="categoria">Categoria</label>
                            <select id="categoria" name="categoria" required>
                                <option value="">Selecione</option>
                                <?php foreach ($categorias as $opcao): ?>
                                    <option value="<?= (int) $opcao['id_categoria'] ?>" <?= (string) $categoria === (string) $opcao['id_categoria'] ? 'selected' : '' ?>>
                                        <?= escapar($opcao['descricao_categoria']) ?>
                                        <?= $opcao['Centro_de_custo'] !== null ? ' — CC ' . escapar($opcao['Centro_de_custo']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($categorias)): ?>
                                <small>Cadastre uma categoria antes de lançar uma despesa.</small>
                            <?php endif; ?>
                            <a href="<?= escapar(endereco('views/categoria/categoria.php')) ?>">Cadastrar categoria</a>
                        </div>

                        <div class="campo">
                            <label for="tecnico">Técnico</label>
                            <select id="tecnico" name="tecnico">
                                <option value="">Sem técnico vinculado</option>
                                <?php foreach ($tecnicos as $opcao): ?>
                                    <option value="<?= (int) $opcao['id'] ?>" <?= (string) $tecnico === (string) $opcao['id'] ? 'selected' : '' ?>>
                                        <?= escapar($opcao['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>

                    <div class="acoes">
                        <button type="submit" class="botao">Gravar</button>
                        <a class="botao secundario" href="index_despesas.php">Voltar para pesquisa</a>
                    </div>
                </form>
            </section>
<?php require __DIR__ . '/../footer.php'; ?>
