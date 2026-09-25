<?php

require_once __DIR__ . '/../../biblioteca.php';

exigirLogin();
$pdo = conectarBanco();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirExclusao();
    validarToken();
    $id = filter_var(lerCampo($_POST, 'id'), FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        http_response_code(400);
        exit('Identificador inválido.');
    }

    try {
        $consulta = $pdo->prepare('DELETE FROM categoria WHERE id_categoria = ?');
        $consulta->execute([$id]);

        if ($consulta->rowCount() === 0) {
            throw new RuntimeException('Categoria não encontrada.');
        }

        redirecionar('views/categoria/index_categoria.php?msg=sucesso');
    } catch (PDOException $excecao) {
        $_SESSION['mensagem_erro'] = mensagemBanco($excecao);
    } catch (RuntimeException $excecao) {
        $_SESSION['mensagem_erro'] = $excecao->getMessage();
    }

    redirecionar('views/categoria/index_categoria.php?error=1');
}

$consulta = $pdo->query('SELECT * FROM categoria ORDER BY descricao_categoria');
$registros = $consulta->fetchAll();
$titulo = 'Categorias';
require __DIR__ . '/../header.php';

?>
            <section class="painel">
                <label for="pesquisar">Pesquisar</label>
                <input type="search" id="pesquisar" placeholder="Descrição ou centro de custo">

                <div class="tabela-scroll max-h-[400px] overflow-y-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Descrição</th>
                                <th>Centro de custo</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registros as $registro): ?>
                                <tr class="linha-registro">
                                    <td><?= (int) $registro['id_categoria'] ?></td>
                                    <td><?= escapar($registro['descricao_categoria']) ?></td>
                                    <td><?= escapar($registro['Centro_de_custo'] ?? 'Não informado') ?></td>
                                    <td>
                                        <div class="acoes">
                                            <?php if (podeEditar()): ?>
                                                <a class="botao pequeno" href="categoria.php?id=<?= (int) $registro['id_categoria'] ?>">Editar</a>
                                            <?php endif; ?>
                                            <?php if (podeExcluir()): ?>
                                                <form method="post" class="form-excluir">
                                                    <input type="hidden" name="token" value="<?= escapar(obterToken()) ?>">
                                                    <input type="hidden" name="id" value="<?= (int) $registro['id_categoria'] ?>">
                                                    <button type="submit" class="botao perigo pequeno">Excluir</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="sem-registros" <?= $registros ? 'hidden' : '' ?>>
                                <td colspan="4">Nenhuma categoria encontrada.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <?php if (podeEditar()): ?>
                    <a class="botao incluir" href="categoria.php">Incluir Novo</a>
                <?php endif; ?>
            </section>
<?php require __DIR__ . '/../footer.php'; ?>
