<?php

require_once __DIR__ . '/../../biblioteca.php';

exigirLogin();

$pdo = conectarBanco();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirExclusao();
    validarToken();
    $id_usina = lerCampo($_POST, 'id_usina');
    $mes_estimado = lerCampo($_POST, 'mes_estimado');
    $idUsinaValido = filter_var($id_usina, FILTER_VALIDATE_INT);

    if ($idUsinaValido === false || $idUsinaValido < 1 || $idUsinaValido > 4294967295
        || !preg_match('/^\d{4}-(0[1-9]|1[0-2])-01$/D', $mes_estimado)
        || !dataValida($mes_estimado)) {
        http_response_code(400);
        exit('Identificador ou mês estimado inválido.');
    }

    try {
        $consulta = $pdo->prepare(
            'DELETE FROM producao_estimada_usina
             WHERE id_usina = ? AND mes_estimado = ?'
        );
        $consulta->execute([$idUsinaValido, $mes_estimado]);

        if ($consulta->rowCount() === 0) {
            throw new RuntimeException('Registro não encontrado.');
        }

        redirecionar('views/producao_estimada/index_producao_estimada.php?msg=sucesso');
    } catch (PDOException $excecao) {
        $_SESSION['mensagem_erro'] = mensagemBanco($excecao);
        redirecionar('views/producao_estimada/index_producao_estimada.php?error=1');
    } catch (RuntimeException $excecao) {
        $_SESSION['mensagem_erro'] = $excecao->getMessage();
        redirecionar('views/producao_estimada/index_producao_estimada.php?error=1');
    }
}

$consulta = $pdo->query(
    'SELECT p.id_usina, p.mes_estimado, p.producao, u.nome AS nome_usina
     FROM producao_estimada_usina p
     INNER JOIN usinas u ON u.id = p.id_usina
     ORDER BY p.mes_estimado DESC, u.nome'
);
$registros = $consulta->fetchAll();
$titulo = 'Produção Estimada';

require __DIR__ . '/../header.php';

?>
            <section class="painel">
                <label for="pesquisar">Pesquisar</label>
                <input type="search" id="pesquisar" placeholder="Digite para filtrar os registros">

                <div class="tabela-scroll max-h-[400px] overflow-y-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Usina</th>
                                <th>Mês estimado</th>
                                <th>Produção estimada</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registros as $registro): ?>
                                <tr class="linha-registro">
                                    <td><?= escapar($registro['nome_usina']) ?></td>
                                    <td><?= escapar(date('m/Y', strtotime($registro['mes_estimado']))) ?></td>
                                    <td><?= escapar(formatarNumero($registro['producao'])) ?></td>
                                    <td>
                                        <div class="acoes">
                                            <?php if (podeEditar()): ?>
                                                <a class="botao pequeno" href="producao_estimada.php?id_usina=<?= escapar($registro['id_usina']) ?>&amp;mes_estimado=<?= escapar($registro['mes_estimado']) ?>">Editar</a>
                                            <?php endif; ?>

                                            <?php if (podeExcluir()): ?>
                                                <form method="post" class="form-excluir">
                                                    <input type="hidden" name="token" value="<?= escapar(obterToken()) ?>">
                                                    <input type="hidden" name="id_usina" value="<?= escapar($registro['id_usina']) ?>">
                                                    <input type="hidden" name="mes_estimado" value="<?= escapar($registro['mes_estimado']) ?>">
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
                                <td colspan="4">Nenhum registro encontrado.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <?php if (podeEditar()): ?>
                    <a class="botao incluir" href="producao_estimada.php">Incluir Novo</a>
                <?php endif; ?>
            </section>
<?php require __DIR__ . '/../footer.php'; ?>
