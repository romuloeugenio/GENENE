<?php

require_once __DIR__ . '/../../biblioteca.php';

exigirLogin();

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
        $consulta = $pdo->prepare('DELETE FROM despesas WHERE id = ?');
        $consulta->execute([$id]);

        if ($consulta->rowCount() === 0) {
            throw new RuntimeException('Registro não encontrado.');
        }

        redirecionar('views/despesas/index_despesas.php?msg=sucesso');
    } catch (PDOException $excecao) {
        $_SESSION['mensagem_erro'] = mensagemBanco($excecao);
        redirecionar('views/despesas/index_despesas.php?error=1');
    } catch (RuntimeException $excecao) {
        $_SESSION['mensagem_erro'] = $excecao->getMessage();
        redirecionar('views/despesas/index_despesas.php?error=1');
    }
}

// Consulta dos registros que serão exibidos na tabela.
$consulta = $pdo->query(
    'SELECT r.*, u.nome AS nome_usina, c.descricao_categoria, c.Centro_de_custo, t.nome AS nome_tecnico
     FROM despesas r
     INNER JOIN usinas u ON u.id = r.usina_id
     INNER JOIN categoria c ON c.id_categoria = r.categoria
     LEFT JOIN tecnicos t ON t.id = r.tecnico
     ORDER BY r.id DESC'
);
$registros = $consulta->fetchAll();
$titulo = 'Despesas';

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
                                <th>Usina</th>
                                <th>Data</th>
                                <th>Valor (R$)</th>
                                <th>Descrição</th>
                                <th>Categoria</th>
                                <th>Centro de custo</th>
                                <th>Técnico</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registros as $registro): ?>
                                <tr class="linha-registro">
                                    <td><?= escapar($registro['id']) ?></td>
                                    <td><?= escapar($registro['nome_usina']) ?></td>
                                    <td><?= escapar(formatarData($registro['data'])) ?></td>
                                    <td><?= escapar(formatarNumero($registro['valor_despesa'])) ?></td>
                                    <td><?= escapar($registro['descricao']) ?></td>
                                    <td><?= escapar($registro['descricao_categoria']) ?></td>
                                    <td><?= escapar($registro['Centro_de_custo'] ?? 'Não informado') ?></td>
                                    <td><?= escapar($registro['nome_tecnico'] ?? 'Sem técnico vinculado') ?></td>
                                    <td>
                                        <div class="acoes">
                                            <?php if (podeEditar()): ?>
                                                <a class="botao pequeno" href="despesas.php?id=<?= escapar($registro['id']) ?>">Editar</a>
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
                                <td colspan="9">Nenhum registro encontrado.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <?php if (podeEditar()): ?>
                    <a class="botao incluir" href="despesas.php">Incluir Novo</a>
                <?php endif; ?>
            </section>
<?php require __DIR__ . '/../footer.php'; ?>
