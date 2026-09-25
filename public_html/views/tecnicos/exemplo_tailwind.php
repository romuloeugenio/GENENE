<?php

require_once __DIR__ . '/../../biblioteca.php';

exigirLogin();
exigirEdicao();

// Esta página demonstra apenas a construção visual do formulário.
// O cadastro original continua em tecnicos.php.
$nome = '';
$cpf = '';
$especialidade = '';
$telefone = '';

?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Exemplo Tailwind - Técnicos</title>

        <!-- O Tailwind é carregado pelo CDN. Esta página não usa custom.css. -->
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    </head>

    <body class="min-h-screen bg-slate-100 font-sans text-slate-800">
        <!-- Cabeçalho: fundo azul, texto branco e espaçamento interno. -->
        <header class="bg-slate-900 px-6 py-5 text-white">
            <div class="mx-auto flex max-w-4xl items-center justify-between gap-4">
                <strong class="text-xl tracking-wide">GERENE</strong>
                <span class="text-sm text-slate-300">Exemplo com Tailwind CSS</span>
            </div>
        </header>

        <!-- Conteúdo centralizado e com largura máxima. -->
        <main class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
            <a
                href="index_tecnicos.php"
                class="text-sm font-medium text-emerald-700 hover:underline"
            >
                ← Voltar para técnicos
            </a>

            <h1 class="mt-6 text-3xl font-bold tracking-tight">Incluir técnico</h1>
            <p class="mt-2 text-sm text-slate-600">
                Prévia visual. O botão Gravar está desabilitado neste exemplo.
            </p>

            <!-- Cartão: fundo branco, borda, cantos arredondados e sombra leve. -->
            <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-lg font-semibold">Dados do técnico</h2>
                <p class="mt-1 text-sm text-slate-500">Todos os campos são obrigatórios.</p>

                <form>
                    <!-- Uma coluna no celular e duas a partir de 768 px. -->
                    <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label for="nome" class="mb-2 block text-sm font-medium">
                                Nome
                            </label>
                            <input
                                type="text"
                                id="nome"
                                name="nome"
                                value="<?= escapar($nome) ?>"
                                maxlength="120"
                                autocomplete="name"
                                placeholder="Nome completo"
                                required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100"
                            >
                        </div>

                        <div>
                            <label for="cpf" class="mb-2 block text-sm font-medium">
                                CPF
                            </label>
                            <input
                                type="text"
                                id="cpf"
                                name="cpf"
                                value="<?= escapar($cpf) ?>"
                                maxlength="14"
                                placeholder="000.000.000-00"
                                required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100"
                            >
                        </div>

                        <div>
                            <label for="especialidade" class="mb-2 block text-sm font-medium">
                                Especialidade
                            </label>
                            <input
                                type="text"
                                id="especialidade"
                                name="especialidade"
                                value="<?= escapar($especialidade) ?>"
                                maxlength="120"
                                placeholder="Ex.: manutenção elétrica"
                                required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100"
                            >
                        </div>

                        <div>
                            <label for="telefone" class="mb-2 block text-sm font-medium">
                                Telefone
                            </label>
                            <input
                                type="tel"
                                id="telefone"
                                name="telefone"
                                value="<?= escapar($telefone) ?>"
                                maxlength="25"
                                autocomplete="tel"
                                placeholder="(00) 00000-0000"
                                required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100"
                            >
                        </div>
                    </div>

                    <!-- Botões empilhados no celular e lado a lado em telas maiores. -->
                    <div class="mt-8 flex flex-col gap-3 border-t border-slate-200 pt-6 sm:flex-row">
                        <button
                            type="button"
                            disabled
                            class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            Gravar (demonstração)
                        </button>

                        <a
                            href="index_tecnicos.php"
                            class="rounded-lg border border-slate-300 px-5 py-2.5 text-center text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Voltar para pesquisa
                        </a>
                    </div>
                </form>
            </section>
        </main>
    </body>
</html>
