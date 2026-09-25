<?php

require_once __DIR__ . '/../../biblioteca.php';

exigirLogin();
exigirEdicao();

$pdo = conectarBanco();
$id = obterId();
$erros = [];

// Valores iniciais para um novo cadastro.
$nome = '';
$cpf = '';
$especialidade = '';
$telefone = '';

// Carrega os dados quando o usuário escolhe Editar.
if ($id > 0) {
    $consulta = $pdo->prepare('SELECT * FROM tecnicos WHERE id = ?');
    $consulta->execute([$id]);
    $registro = $consulta->fetch();

    if (!$registro) {
        http_response_code(404);
        exit('Registro não encontrado.');
    }

    $nome = $registro['nome'];
    $cpf = $registro['cpf'];
    $especialidade = $registro['especialidade'];
    $telefone = $registro['telefone'];
}

// Recebe e valida os campos enviados pelo formulário.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarToken();

    $nome = lerCampo($_POST, 'nome');
    $cpf = lerCampo($_POST, 'cpf');
    $especialidade = lerCampo($_POST, 'especialidade');
    $telefone = lerCampo($_POST, 'telefone');
    $cpf = preg_replace('/[.\\-\\s]/', '', $cpf);

    if ($nome === '' || iconv_strlen($nome, 'UTF-8') > 120) {
        $erros[] = 'Nome: preencha até 120 caracteres.';
    }

    if (!cpfValido($cpf)) {
        $erros[] = 'Informe um CPF válido.';
    }

    if ($especialidade === '' || iconv_strlen($especialidade, 'UTF-8') > 120) {
        $erros[] = 'Especialidade: preencha até 120 caracteres.';
    }

    if ($telefone === '' || iconv_strlen($telefone, 'UTF-8') > 25) {
        $erros[] = 'Telefone: preencha até 25 caracteres.';
    }

    // Só grava no banco se não houver erros de preenchimento.
    if (empty($erros)) {
        try {
            if ($id > 0) {
                $consulta = $pdo->prepare(
                    'UPDATE tecnicos
                     SET nome = ?, cpf = ?, especialidade = ?, telefone = ?
                     WHERE id = ?'
                );
                $consulta->execute([$nome, $cpf, $especialidade, $telefone, $id]);
            } else {
                $consulta = $pdo->prepare(
                    'INSERT INTO tecnicos (nome, cpf, especialidade, telefone)
                     VALUES (?, ?, ?, ?)'
                );
                $consulta->execute([$nome, $cpf, $especialidade, $telefone]);
            }

            redirecionar('views/tecnicos/index_tecnicos.php?msg=sucesso');
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

$titulo = 'Técnicos - ' . ($id > 0 ? 'Editar' : 'Incluir');
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
                            <label for="cpf">CPF</label>
                            <input type="text" id="cpf" name="cpf"
                                   value="<?= escapar($cpf) ?>" maxlength="14" required>
                        </div>

                        <div class="campo">
                            <label for="especialidade">Especialidade</label>
                            <input type="text" id="especialidade" name="especialidade"
                                   value="<?= escapar($especialidade) ?>" maxlength="120" required>
                        </div>

                        <div class="campo">
                            <label for="telefone">Telefone</label>
                            <input type="tel" id="telefone" name="telefone"
                                   value="<?= escapar($telefone) ?>" maxlength="25" required>
                        </div>

                    </div>

                    <div class="acoes">
                        <button type="submit" class="botao">Gravar</button>
                        <a class="botao secundario" href="index_tecnicos.php">Voltar para pesquisa</a>
                    </div>
                </form>
            </section>
<?php require __DIR__ . '/../footer.php'; ?>
