<?php

require_once __DIR__ . '/biblioteca.php';

if (!empty($_SESSION['usuario_id'])) {
    exigirLogin();
    redirecionar('views/dashdark.php');
}

$erro = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarToken();
    $email = lerCampo($_POST, 'email');
    $senha = isset($_POST['senha']) && is_string($_POST['senha']) ? $_POST['senha'] : '';

    if (time() < ($_SESSION['bloqueado_ate'] ?? 0)) {
        $erro = 'Aguarde cinco minutos antes de tentar novamente.';
    } else {
        $pdo = conectarBanco();
        $consulta = $pdo->prepare('SELECT * FROM usuarios WHERE email = ?');
        $consulta->execute([$email]);
        $usuario = $consulta->fetch();

        if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
            session_regenerate_id(true);
            $_SESSION = [
                'usuario_id' => $usuario['id'],
                'nome' => $usuario['nome'],
                'pode_editar' => $usuario['pode_editar'],
                'pode_excluir' => $usuario['pode_excluir'],
                'ultima_atividade' => time()
            ];
            redirecionar('views/dashdark.php');
        }

        $_SESSION['tentativas'] = ($_SESSION['tentativas'] ?? 0) + 1;

        if ($_SESSION['tentativas'] >= 5) {
            $_SESSION['bloqueado_ate'] = time() + 300;
            $_SESSION['tentativas'] = 0;
        }

        $erro = 'E-mail ou senha incorretos.';
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Entrar - GERENE</title>
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
        <link rel="stylesheet" href="<?= escapar(endereco('assets/css/custom.css')) ?>">
    </head>
    <body class="pagina-login">
        <main class="cartao-login">
            <h1>GERENE</h1>
            <p>Gerenciamento de usinas</p>

            <?php if ($erro !== ''): ?>
                <div class="aviso erro" role="alert"><?= escapar($erro) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="token" value="<?= escapar(obterToken()) ?>">

                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" value="<?= escapar($email) ?>" autocomplete="username" required>

                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" autocomplete="current-password" required>

                <button type="submit" class="botao">Entrar</button>
            </form>
        </main>
    </body>
</html>
