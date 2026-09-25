<?php

require_once __DIR__ . '/../../biblioteca.php';

exigirLogin();
exigirEdicao();
exigirGestor();

$pdo = conectarBanco();
$id = obterId();
$erros = [];

// Valores iniciais para um novo cadastro.
$nome = '';
$email = '';
$senha = '';
$pode_editar = 'N';
$pode_excluir = 'N';
$senha_hash = '';

// Carrega os dados quando o usuário escolhe Editar.
if ($id > 0) {
    $consulta = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
    $consulta->execute([$id]);
    $registro = $consulta->fetch();

    if (!$registro) {
        http_response_code(404);
        exit('Registro não encontrado.');
    }

    $nome = $registro['nome'];
    $email = $registro['email'];
    $pode_editar = $registro['pode_editar'];
    $pode_excluir = $registro['pode_excluir'];
    $senha_hash = $registro['senha_hash'];
}

// Recebe e valida os campos enviados pelo formulário.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarToken();

    $nome = lerCampo($_POST, 'nome');
    $email = lerCampo($_POST, 'email');
    $senha = isset($_POST['senha']) && is_string($_POST['senha']) ? $_POST['senha'] : '';
    $pode_editar = lerCampo($_POST, 'pode_editar');
    $pode_excluir = lerCampo($_POST, 'pode_excluir');

    if ($nome === '' || iconv_strlen($nome, 'UTF-8') > 120) {
        $erros[] = 'Nome: preencha até 120 caracteres.';
    }

    if ($email === '' || iconv_strlen($email, 'UTF-8') > 190) {
        $erros[] = 'E-mail: preencha até 190 caracteres.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Informe um e-mail válido.';
    }

    if (($id === 0 || $senha !== '') && (strlen($senha) < 8 || strlen($senha) > 72)) {
        $erros[] = 'A senha deve ter entre 8 e 72 bytes.';
    }

    if (!in_array($pode_editar, ['S', 'N'], true)) {
        $erros[] = 'Pode incluir / editar: selecione uma opção válida.';
    }

    if (!in_array($pode_excluir, ['S', 'N'], true)) {
        $erros[] = 'Pode excluir: selecione uma opção válida.';
    }

    // Só grava no banco se não houver erros de preenchimento.
    if (empty($erros)) {
        try {
            $pdo->beginTransaction();

            // Bloqueia a alteração simultânea dos gestores e preserva o último.
            $consulta = $pdo->query(
                "SELECT id FROM usuarios
                 WHERE pode_editar = 'S' AND pode_excluir = 'S'
                 ORDER BY id FOR UPDATE"
            );
            $gestores = $consulta->fetchAll(PDO::FETCH_COLUMN);

            if (count($gestores) === 1 && (int) $gestores[0] === $id
                && ($pode_editar !== 'S' || $pode_excluir !== 'S')) {
                throw new RuntimeException('Mantenha pelo menos um usuário com as duas permissões.');
            }

            if ($senha !== '') {
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            }

            if ($id > 0) {
                $consulta = $pdo->prepare(
                    'UPDATE usuarios
                     SET nome = ?, email = ?, senha_hash = ?, pode_editar = ?, pode_excluir = ?
                     WHERE id = ?'
                );
                $consulta->execute([$nome, $email, $senha_hash, $pode_editar, $pode_excluir, $id]);
            } else {
                $consulta = $pdo->prepare(
                    'INSERT INTO usuarios (nome, email, senha_hash, pode_editar, pode_excluir)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $consulta->execute([$nome, $email, $senha_hash, $pode_editar, $pode_excluir]);
            }

            $pdo->commit();

            redirecionar('views/usuarios/index_usuarios.php?msg=sucesso');
        } catch (PDOException $excecao) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erros[] = mensagemBanco($excecao);
        } catch (RuntimeException $excecao) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erros[] = $excecao->getMessage();
        }
    }

    if (!empty($erros)) {
        http_response_code(422);
    }
}

$titulo = 'Usuários - ' . ($id > 0 ? 'Editar' : 'Incluir');
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
                            <label for="email">E-mail</label>
                            <input type="email" id="email" name="email"
                                   value="<?= escapar($email) ?>" maxlength="190" required>
                        </div>

                        <div class="campo">
                            <label for="senha">Senha</label>
                            <input type="password" id="senha" name="senha" minlength="8" maxlength="72"
                                   autocomplete="new-password" <?= $id === 0 ? 'required' : '' ?>>
                            <small>Entre 8 e 72 bytes. Ao editar, deixe em branco para manter a senha.</small>
                        </div>

                        <div class="campo">
                            <label for="pode_editar">Pode incluir / editar</label>
                            <select id="pode_editar" name="pode_editar" required>
                                <option value="">Selecione</option>
                                <option value="S" <?= $pode_editar === 'S' ? 'selected' : '' ?>>Sim</option>
                                <option value="N" <?= $pode_editar === 'N' ? 'selected' : '' ?>>Não</option>
                            </select>
                        </div>

                        <div class="campo">
                            <label for="pode_excluir">Pode excluir</label>
                            <select id="pode_excluir" name="pode_excluir" required>
                                <option value="">Selecione</option>
                                <option value="S" <?= $pode_excluir === 'S' ? 'selected' : '' ?>>Sim</option>
                                <option value="N" <?= $pode_excluir === 'N' ? 'selected' : '' ?>>Não</option>
                            </select>
                        </div>

                    </div>

                    <div class="acoes">
                        <button type="submit" class="botao">Gravar</button>
                        <a class="botao secundario" href="index_usuarios.php">Voltar para pesquisa</a>
                    </div>
                </form>
            </section>
<?php require __DIR__ . '/../footer.php'; ?>
