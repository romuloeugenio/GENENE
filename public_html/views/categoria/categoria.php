<?php

require_once __DIR__ . '/../../biblioteca.php';

exigirLogin();
exigirEdicao();

$pdo = conectarBanco();
$id = obterId();
$descricao_categoria = '';
$Centro_de_custo = '';
$erros = [];

if ($id > 0) {
    $consulta = $pdo->prepare('SELECT * FROM categoria WHERE id_categoria = ?');
    $consulta->execute([$id]);
    $registro = $consulta->fetch();

    if (!$registro) {
        http_response_code(404);
        exit('Categoria não encontrada.');
    }

    $descricao_categoria = $registro['descricao_categoria'];
    $Centro_de_custo = $registro['Centro_de_custo'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarToken();
    $descricao_categoria = lerCampo($_POST, 'descricao_categoria');
    $Centro_de_custo = lerCampo($_POST, 'Centro_de_custo');

    if ($descricao_categoria === '' || iconv_strlen($descricao_categoria, 'UTF-8') > 120) {
        $erros[] = 'Descrição: preencha até 120 caracteres.';
    }

    // O campo aceita o intervalo completo de um INT do MySQL.
    if ($Centro_de_custo !== '') {
        $centroValidado = filter_var($Centro_de_custo, FILTER_VALIDATE_INT);

        if ($centroValidado === false || $centroValidado < -2147483648 || $centroValidado > 2147483647) {
            $erros[] = 'Centro de custo: informe um número inteiro entre -2147483648 e 2147483647.';
        }
    }

    if (empty($erros)) {
        try {
            if ($id > 0) {
                $consulta = $pdo->prepare(
                    'UPDATE categoria SET descricao_categoria = ?, Centro_de_custo = ?
                     WHERE id_categoria = ?'
                );
                $consulta->execute([$descricao_categoria, $Centro_de_custo === '' ? null : $Centro_de_custo, $id]);
            } else {
                $consulta = $pdo->prepare(
                    'INSERT INTO categoria (descricao_categoria, Centro_de_custo) VALUES (?, ?)'
                );
                $consulta->execute([$descricao_categoria, $Centro_de_custo === '' ? null : $Centro_de_custo]);
            }

            redirecionar('views/categoria/index_categoria.php?msg=sucesso');
        } catch (PDOException $excecao) {
            $erros[] = mensagemBanco($excecao);
        }
    }

    if ($erros) {
        http_response_code(422);
    }
}

$titulo = 'Categorias - ' . ($id > 0 ? 'Editar' : 'Incluir');
require __DIR__ . '/../header.php';

?>
            <section class="painel">
                <?php if ($erros): ?>
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
                            <label for="descricao_categoria">Descrição da categoria</label>
                            <input type="text" id="descricao_categoria" name="descricao_categoria"
                                   value="<?= escapar($descricao_categoria) ?>" maxlength="120" required>
                        </div>
                        <div class="campo">
                            <label for="Centro_de_custo">Centro de custo</label>
                            <input type="number" id="Centro_de_custo" name="Centro_de_custo"
                                   value="<?= escapar($Centro_de_custo) ?>" min="-2147483648" max="2147483647" step="1">
                            <small>Opcional. Deixe em branco quando ainda não houver um centro definido.</small>
                        </div>
                    </div>
                    <div class="acoes">
                        <button type="submit" class="botao">Gravar</button>
                        <a class="botao secundario" href="index_categoria.php">Voltar para pesquisa</a>
                    </div>
                </form>
            </section>
<?php require __DIR__ . '/../footer.php'; ?>
