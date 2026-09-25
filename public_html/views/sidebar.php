<?php

if (!isset($titulo)) {
    http_response_code(403);
    exit('Acesse uma página do sistema.');
}

?>
        <aside class="menu" id="menu">
            <nav aria-label="Menu principal">
                <a href="<?= escapar(endereco('views/dashdark.php')) ?>">DashDark</a>
                <a href="<?= escapar(endereco('views/dashboard.php')) ?>">Dashboard</a>

                <?php if (podeEditar() && podeExcluir()): ?>
                    <a href="<?= escapar(endereco('views/usuarios/index_usuarios.php')) ?>">Usuários</a>
                <?php endif; ?>

                <a href="<?= escapar(endereco('views/usinas/index_usinas.php')) ?>">Usinas</a>
                <a href="<?= escapar(endereco('views/tecnicos/index_tecnicos.php')) ?>">Técnicos</a>
                <a href="<?= escapar(endereco('views/producao/index_producao.php')) ?>">Produção de Energia</a>
                <a href="<?= escapar(endereco('views/despesas/index_despesas.php')) ?>">Despesas</a>
                <a href="<?= escapar(endereco('views/categoria/index_categoria.php')) ?>">Categorias</a>
            </nav>
        </aside>
