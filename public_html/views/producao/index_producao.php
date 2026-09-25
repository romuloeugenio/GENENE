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
        $consulta = $pdo->prepare('DELETE FROM producao_energia WHERE id = ?');
        $consulta->execute([$id]);

        if ($consulta->rowCount() === 0) {
            throw new RuntimeException('Registro não encontrado.');
        }

        redirecionar('views/producao/index_producao.php?msg=sucesso');
    } catch (PDOException $excecao) {
        $_SESSION['mensagem_erro'] = mensagemBanco($excecao);
        redirecionar('views/producao/index_producao.php?error=1');
    } catch (RuntimeException $excecao) {
        $_SESSION['mensagem_erro'] = $excecao->getMessage();
        redirecionar('views/producao/index_producao.php?error=1');
    }
}

// Consulta dos registros que serão exibidos na tabela.
$consulta = $pdo->query(
    'SELECT r.*, u.nome AS nome_usina
     FROM producao_energia r
     INNER JOIN usinas u ON u.id = r.usina_id
     ORDER BY r.id DESC'
);
$registros = $consulta->fetchAll();
$inicio = '2026-01-01';
$fim = date('Y-m-d');
$totalProducao = 0;
$quantidadeVisivel = 0;

// Calcula o total e a visibilidade inicial usando o mesmo período da pesquisa.
foreach ($registros as $registro) {
    if ($registro['data_producao'] >= $inicio && $registro['data_producao'] <= $fim) {
        $totalProducao += (float) $registro['kwh_gerado'];
        $quantidadeVisivel++;
    }
}
$titulo = 'Produção de Energia';

require __DIR__ . '/../header.php';

?>
            <section class="painel painel-producao">
                <div class="pesquisa-producao">
                    <div>
                        <label for="pesquisar">Pesquisar</label>
                        <input type="search" id="pesquisar" placeholder="Digite para filtrar os registros">
                    </div>
                    <div>
                        <label for="producao-inicio">Data inicial</label>
                        <input type="date" id="producao-inicio" value="<?= escapar($inicio) ?>">
                    </div>
                    <div>
                        <label for="producao-fim">Data final</label>
                        <input type="date" id="producao-fim" value="<?= escapar($fim) ?>">
                    </div>
                </div>
                <p id="erro-periodo-producao" class="aviso erro" role="alert" hidden>A data inicial deve ser menor ou igual à data final.</p>

                <div class="tabela-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Usina</th>
                                <th>Data de produção</th>
                                <th>Energia gerada (kWh)</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registros as $registro): ?>
                                <tr class="linha-registro" data-data="<?= escapar($registro['data_producao']) ?>" data-kwh="<?= escapar($registro['kwh_gerado']) ?>" <?= $registro['data_producao'] < $inicio || $registro['data_producao'] > $fim ? 'hidden' : '' ?>>
                                    <td><?= escapar($registro['id']) ?></td>
                                    <td><?= escapar($registro['nome_usina']) ?></td>
                                    <td><?= escapar(formatarData($registro['data_producao'])) ?></td>
                                    <td><?= escapar(formatarNumero($registro['kwh_gerado'])) ?></td>
                                    <td>
                                        <div class="acoes">
                                            <?php if (podeEditar()): ?>
                                                <a class="botao pequeno" href="producao.php?id=<?= escapar($registro['id']) ?>">Editar</a>
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
                            <tr id="sem-registros" <?= $quantidadeVisivel > 0 ? 'hidden' : '' ?>>
                                <td colspan="5">Nenhum registro encontrado.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="rodape-producao">
                    <?php if (podeEditar()): ?>
                        <a class="botao" href="producao.php">Incluir Novo</a>
                    <?php else: ?>
                        <button type="button" class="botao" disabled title="Seu usuário possui acesso somente para consulta.">Incluir Novo</button>
                    <?php endif; ?>
                    <section class="total-producao" aria-live="polite">
                        <p>Total de produção de energia no período</p>
                        <strong><span id="total-producao"><?= escapar(formatarNumero($totalProducao)) ?></span> kWh</strong>
                        <p>Considera os registros da pesquisa. Sem datas, inclui todo o período.</p>
                    </section>
                </div>
            </section>
<?php require __DIR__ . '/../footer.php'; ?>
