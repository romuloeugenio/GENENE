<?php

require_once __DIR__ . '/../../biblioteca.php';

exigirLogin();
exigirGestor();

$pdo = conectarBanco();
$erro = '';

// A exclusão é feita somente por POST, com permissão e token válido.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirExclusao();
    validarToken();
    $id = filter_var(lerCampo($_POST, 'id'), FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        http_response_code(400);
        exit('Identificador inválido.');
    }

    try {
        if ($id === (int) $_SESSION['usuario_id']) {
            throw new RuntimeException('Você não pode excluir o próprio usuário.');
        }

        $pdo->beginTransaction();
        $consulta = $pdo->query(
            "SELECT id FROM usuarios
             WHERE pode_editar = 'S' AND pode_excluir = 'S'
             ORDER BY id FOR UPDATE"
        );
        $gestores = $consulta->fetchAll(PDO::FETCH_COLUMN);

        if (count($gestores) === 1 && (int) $gestores[0] === $id) {
            throw new RuntimeException('Não é possível excluir o último gestor.');
        }

        $consulta = $pdo->prepare('DELETE FROM usuarios WHERE id = ?');
        $consulta->execute([$id]);

        if ($consulta->rowCount() === 0) {
            throw new RuntimeException('Registro não encontrado.');
        }

        $pdo->commit();

        redirecionar('views/usuarios/index_usuarios.php?msg=sucesso');
    } catch (PDOException $excecao) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['mensagem_erro'] = mensagemBanco($excecao);
        redirecionar('views/usuarios/index_usuarios.php?error=1');
    } catch (RuntimeException $excecao) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['mensagem_erro'] = $excecao->getMessage();
        redirecionar('views/usuarios/index_usuarios.php?error=1');
    }
}

// Consulta dos registros que serão exibidos na tabela.
$consulta = $pdo->query(
    'SELECT r.id, r.nome, r.email, r.pode_editar, r.pode_excluir
     FROM usuarios r
     ORDER BY r.id DESC'
);
$registros = $consulta->fetchAll();
$titulo = 'Usuários';

require __DIR__ . '/../header.php';

?>
            <section class="painel">
                <label for="pesquisar">Pesquisar</label>
                <input type="search" id="pesquisar" placeholder="Digite para filtrar os registros">

                <div class="tabela-scroll max-h-[400px] overflow-y-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Pode incluir / editar</th>
                                <th>Pode excluir</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registros as $registro): ?>
                                <tr class="linha-registro">
                                    <td><?= escapar($registro['id']) ?></td>
                                    <td><?= escapar($registro['nome']) ?></td>
                                    <td><?= escapar($registro['email']) ?></td>
                                    <td><?= escapar($registro['pode_editar']) ?></td>
                                    <td><?= escapar($registro['pode_excluir']) ?></td>
                                    <td>
                                        <div class="acoes">
                                            <?php if (podeEditar()): ?>
                                                <a class="botao pequeno" href="usuarios.php?id=<?= escapar($registro['id']) ?>">Editar</a>
                                            <?php endif; ?>

                                            <?php if (podeExcluir()): ?>
                                                <form method="post" class="form-excluir">
                                                    <input type="hidden" name="token" value="<?= escapar(obterToken()) ?>">
                                                    <input type="hidden" name="id" value="<?= escapar($registro['id']) ?>">
                                                    <button type="submit" class="botao perigo pequeno">Excluir</button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if (!podeEditar() && !podeExcluir()): ?>
                                                <span>Somente consulta</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="sem-registros" <?= count($registros) > 0 ? 'hidden' : '' ?>>
                                <td colspan="6">Nenhum registro encontrado.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <?php if (podeEditar()): ?>
                    <a class="botao incluir" href="usuarios.php">Incluir Novo</a>
                <?php endif; ?>
            </section>
<?php require __DIR__ . '/../footer.php'; ?>
