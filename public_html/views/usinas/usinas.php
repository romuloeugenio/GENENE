<?php

require_once __DIR__ . '/../../biblioteca.php';

exigirLogin();
exigirEdicao();

$pdo = conectarBanco();
$id = obterId();
$erros = [];

// Valores iniciais para um novo cadastro.
$nome = '';
$tipo = '';
$capacidade_kw = '';
$localizacao = '';
$status = 'Ativa';

// Carrega os dados quando o usuário escolhe Editar.
if ($id > 0) {
    $consulta = $pdo->prepare('SELECT * FROM usinas WHERE id = ?');
    $consulta->execute([$id]);
    $registro = $consulta->fetch();

    if (!$registro) {
        http_response_code(404);
        exit('Registro não encontrado.');
    }

    $nome = $registro['nome'];
    $tipo = $registro['tipo'];
    $capacidade_kw = $registro['capacidade_kw'];
    $localizacao = $registro['localizacao'];
    $status = $registro['status'];
}

// Recebe e valida os campos enviados pelo formulário.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarToken();

    $nome = lerCampo($_POST, 'nome');
    $tipo = lerCampo($_POST, 'tipo');
    $capacidade_kw = lerCampo($_POST, 'capacidade_kw');
    $localizacao = lerCampo($_POST, 'localizacao');
    $status = lerCampo($_POST, 'status');

    if ($nome === '' || iconv_strlen($nome, 'UTF-8') > 120) {
        $erros[] = 'Nome: preencha até 120 caracteres.';
    }

    if (!in_array($tipo, ['Fotovoltaica', 'Hidrelétrica'], true)) {
        $erros[] = 'Tipo: selecione uma opção válida.';
    }

    if (!numeroValido($capacidade_kw)) {
        $erros[] = 'Capacidade (kW): informe um número positivo ou zero, com até duas casas decimais.';
    }

    if ($localizacao === '' || iconv_strlen($localizacao, 'UTF-8') > 255) {
        $erros[] = 'Localização: preencha até 255 caracteres.';
    }

    if (!in_array($status, ['Ativa', 'Inativa', 'Manutenção'], true)) {
        $erros[] = 'Status: selecione uma opção válida.';
    }

    // Só grava no banco se não houver erros de preenchimento.
    if (empty($erros)) {
        try {
            if ($id > 0) {
                $consulta = $pdo->prepare(
                    'UPDATE usinas
                     SET nome = ?, tipo = ?, capacidade_kw = ?, localizacao = ?, status = ?
                     WHERE id = ?'
                );
                $consulta->execute([$nome, $tipo, $capacidade_kw, $localizacao, $status, $id]);
            } else {
                $consulta = $pdo->prepare(
                    'INSERT INTO usinas (nome, tipo, capacidade_kw, localizacao, status)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $consulta->execute([$nome, $tipo, $capacidade_kw, $localizacao, $status]);
            }

            redirecionar('views/usinas/index_usinas.php?msg=sucesso');
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

$titulo = 'Usinas - ' . ($id > 0 ? 'Editar' : 'Incluir');
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
                            <label for="nome">Nome</label>
                            <input type="text" id="nome" name="nome"
                                   value="<?= escapar($nome) ?>" maxlength="120" required>
                        </div>

                        <div class="campo">
                            <label for="tipo">Tipo</label>
                            <select id="tipo" name="tipo" required>
                                <option value="">Selecione</option>
                                <option value="Fotovoltaica" <?= $tipo === 'Fotovoltaica' ? 'selected' : '' ?>>Fotovoltaica</option>
                                <option value="Hidrelétrica" <?= $tipo === 'Hidrelétrica' ? 'selected' : '' ?>>Hidrelétrica</option>
                            </select>
                        </div>

                        <div class="campo">
                            <label for="capacidade_kw">Capacidade (kW)</label>
                            <input type="number" id="capacidade_kw" name="capacidade_kw"
                                   value="<?= escapar($capacidade_kw) ?>" min="0" max="9999999999.99" step="0.01" required>
                        </div>

                        <div class="campo">
                            <label for="localizacao">Localização</label>
                            <input type="text" id="localizacao" name="localizacao"
                                   value="<?= escapar($localizacao) ?>" maxlength="255" required>
                        </div>

                        <div class="campo">
                            <label for="status">Status</label>
                            <select id="status" name="status" required>
                                <option value="">Selecione</option>
                                <option value="Ativa" <?= $status === 'Ativa' ? 'selected' : '' ?>>Ativa</option>
                                <option value="Inativa" <?= $status === 'Inativa' ? 'selected' : '' ?>>Inativa</option>
                                <option value="Manutenção" <?= $status === 'Manutenção' ? 'selected' : '' ?>>Manutenção</option>
                            </select>
                        </div>

                    </div>

                    <div class="acoes">
                        <button type="submit" class="botao">Gravar</button>
                        <a class="botao secundario" href="index_usinas.php">Voltar para pesquisa</a>
                    </div>
                </form>
            </section>
<?php require __DIR__ . '/../footer.php'; ?>
