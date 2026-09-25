# GERENE — Código completo da versão local XAMPP

## migrations/20260924_despesas_categoria.sql

```sql
-- Execute uma única vez no banco antigo. Faça backup antes da migração.
-- Mantém IDs, valores e vínculos existentes das despesas.
CREATE TABLE categoria (
    id_categoria INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descricao_categoria VARCHAR(120) NOT NULL,
    Centro_de_custo INT NULL,
    UNIQUE KEY uq_categoria_descricao (descricao_categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categoria (descricao_categoria)
SELECT DISTINCT CASE WHEN TRIM(categoria) = '' THEN 'Sem categoria' ELSE TRIM(categoria) END
FROM despesas_material;

RENAME TABLE despesas_material TO despesas;

ALTER TABLE despesas
    ADD COLUMN categoria_nova INT UNSIGNED NULL,
    ADD COLUMN tecnico INT UNSIGNED NULL;

UPDATE despesas d
INNER JOIN categoria c
    ON c.descricao_categoria = CASE WHEN TRIM(d.categoria) = '' THEN 'Sem categoria' ELSE TRIM(d.categoria) END
SET d.categoria_nova = c.id_categoria;

ALTER TABLE despesas
    DROP COLUMN categoria,
    CHANGE COLUMN categoria_nova categoria INT UNSIGNED NOT NULL,
    ADD KEY idx_despesas_categoria (categoria),
    ADD KEY idx_despesas_tecnico (tecnico),
    ADD CONSTRAINT fk_despesas_categoria FOREIGN KEY (categoria)
        REFERENCES categoria(id_categoria) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT fk_despesas_tecnico FOREIGN KEY (tecnico)
        REFERENCES tecnicos(id) ON DELETE RESTRICT ON UPDATE CASCADE;
```

## migrations/aplicar_despesas.php

```php
<?php

// Migração local por terminal. O backup deve ser feito antes de executar.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Execute pelo terminal.');
}

require_once __DIR__ . '/../public_html/db.php';

$pdo = conectarBanco();
$tabelas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

if (in_array('despesas', $tabelas, true) || in_array('categoria', $tabelas, true)) {
    exit("Migração não executada: despesas ou categoria já existe. Confira o estado do banco.\n");
}

if (!in_array('despesas_material', $tabelas, true)) {
    exit("Tabela despesas_material não encontrada.\n");
}

$pdo->exec(file_get_contents(__DIR__ . '/20260924_despesas_categoria.sql'));
echo "Migração concluída: despesas, categoria e vínculo com técnicos.\n";
```

## public_html/.htaccess

```apache
Options -Indexes
DirectoryIndex index.php

<FilesMatch "^(config\.php|db\.php|biblioteca\.php|schema\.sql)$">
    Require all denied
</FilesMatch>
```

## public_html/assets/css/custom.css

```css
/* Estilos básicos e cores do sistema. */
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f2f5f7;
    color: #243746;
    font-family: Arial, sans-serif;
    font-size: 14px;
}

h1 {
    margin: 0 0 24px;
    font-size: 26px;
    font-weight: bold;
}

h2 {
    margin: 0 0 12px;
    font-size: 20px;
    font-weight: bold;
}

h3 {
    margin: 24px 0 12px;
    font-size: 15px;
    font-weight: bold;
}

p {
    margin: 12px 0;
    line-height: 1.6;
}

button,
input,
select,
textarea {
    font: inherit;
}

label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
}

input,
select,
textarea {
    display: block;
    width: 100%;
    padding: 10px;
    border: 1px solid #cbd5dd;
    border-radius: 5px;
    background: white;
    color: #243746;
}

input:focus,
select:focus,
textarea:focus {
    outline: 2px solid #168a72;
    outline-offset: 1px;
}

[hidden] {
    display: none !important;
}

small {
    display: block;
    margin-top: 6px;
    color: #607080;
}

/* Barra fixa e menu lateral. */
.barra-superior {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 70px;
    padding: 0 24px;
    background: #17394d;
    color: white;
}

.barra-superior > div {
    display: flex;
    align-items: center;
    gap: 16px;
}

.menu {
    position: fixed;
    top: 70px;
    left: 0;
    bottom: 0;
    z-index: 9;
    width: 220px;
    padding: 20px 12px;
    overflow-y: auto;
    background: #214c63;
}

.menu a {
    display: block;
    padding: 13px 12px;
    margin-bottom: 6px;
    border-radius: 5px;
    color: white;
    text-decoration: none;
}

.menu a:hover,
.menu a.ativo {
    background: #168a72;
}

.conteudo {
    margin: 70px 0 0 220px;
    padding: 28px;
}

.menu-alterado .menu {
    display: none;
}

.menu-alterado .conteudo {
    margin-left: 0;
}

/* Formulários e tabelas. */
.painel {
    min-width: 0;
    padding: 24px;
    margin-bottom: 24px;
    border: 1px solid #dce4e9;
    border-radius: 8px;
    background: white;
}

.campos {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
    margin-bottom: 26px;
}

.botao {
    display: inline-block;
    padding: 11px 18px;
    border: 0;
    border-radius: 5px;
    background: #168a72;
    color: white;
    text-decoration: none;
    text-align: center;
    cursor: pointer;
}

.botao:hover {
    filter: brightness(0.93);
}

.botao.secundario {
    background: #e5ebef;
    color: #243746;
}

.botao.perigo {
    background: #b84b4b;
}

.botao.pequeno {
    padding: 6px 10px;
    font-size: 12px;
}

.acoes {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.incluir {
    margin-top: 22px;
}

#pesquisar {
    max-width: 450px;
    margin-bottom: 20px;
}

.tabela-scroll {
    max-height: 400px;
    overflow: auto;
}

.painel-producao .tabela-scroll {
    max-height: 340px;
}

.painel-producao .linha-registro td {
    padding: 4.5px 10px;
}

table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    text-align: left;
    font-size: 13px;
}

th,
td {
    padding: 10px 12px;
    border-bottom: 1px solid #e3e9ed;
    overflow-wrap: anywhere;
}

th {
    position: sticky;
    top: 0;
    background: #eaf0f4;
    font-weight: bold;
    white-space: nowrap;
}

.aviso {
    padding: 14px;
    margin: 0 0 20px;
    border-radius: 5px;
    line-height: 1.6;
}

.aviso.erro {
    background: #ffe9e7;
    color: #922e25;
}

.aviso.sucesso {
    background: #def5e8;
    color: #236646;
}

.aviso ul {
    margin: 0;
    padding-left: 20px;
}

/* Dashboard e login. */
.pesquisa-producao,
.rodape-producao {
    display: flex;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 20px;
}

.pesquisa-producao #pesquisar {
    margin-bottom: 0;
}

.rodape-producao {
    position: sticky;
    bottom: 0;
    z-index: 2;
    padding: 12px 0;
    background: white;
    align-items: center;
    margin-top: 22px;
    margin-bottom: 0;
}

.rodape-producao .botao:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.total-producao {
    margin-left: auto;
    padding: 16px 20px;
    border: 1px solid #b8dfd3;
    border-radius: 8px;
    background: #edf8f3;
    color: #236646;
}

.total-producao p {
    margin: 0;
    font-size: 12px;
}

.total-producao strong {
    display: block;
    margin: 6px 0;
    font-size: 22px;
}

.filtro-periodo {
    display: flex;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 16px;
}

.grade-usinas {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 24px;
}

.grafico {
    position: relative;
    height: 230px;
}

.grafico-todas-usinas {
    height: 360px;
}

details {
    margin-top: 24px;
}

summary {
    margin-bottom: 15px;
    cursor: pointer;
}

.pagina-login {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 24px;
}

.cartao-login {
    width: 100%;
    max-width: 400px;
    padding: 32px;
    border: 1px solid #dce4e9;
    border-radius: 8px;
    background: white;
}

.cartao-login h1 {
    color: #168a72;
}

.cartao-login input {
    margin-bottom: 20px;
}

.cartao-login .botao {
    width: 100%;
}

/* Em telas pequenas, o botão Menu abre a navegação sobre o conteúdo. */
@media (max-width: 1100px) {
    .grade-usinas {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 760px) {
    .menu {
        display: none;
    }

    .menu-alterado .menu {
        display: block;
    }

    .conteudo {
        margin-left: 0;
        padding: 18px;
    }

    .campos {
        grid-template-columns: 1fr;
    }

    .barra-superior {
        padding: 0 12px;
    }

    .barra-superior span {
        display: none;
    }

    .painel {
        padding: 18px;
    }
}
```

## public_html/assets/js/dashdark.js

```javascript
'use strict';

let dadosDark = null;
let inicioDark = '';
let graficosDark = {};
const etapasDark = [1, 7, 14, 30, 60, 90, 180, 365];

/**
 * Converte uma data ISO em milissegundos UTC, sem interferência de fuso ou horário de verão.
 * @param {string} data Data no formato AAAA-MM-DD.
 * @returns {number} Instante UTC correspondente à meia-noite da data.
 */
function instanteDark(data) {
    return Date.parse(data + 'T00:00:00Z');
}

/**
 * Formata uma data ISO para exibição em português.
 * @param {string} data Data no formato AAAA-MM-DD.
 * @returns {string} Data no formato DD/MM/AAAA.
 */
function dataTextoDark(data) {
    return data.slice(8, 10) + '/' + data.slice(5, 7) + '/' + data.slice(0, 4);
}

/**
 * Conta os dias inclusivos de um intervalo.
 * @param {string} inicio Data inicial ISO.
 * @param {string} fim Data final ISO.
 * @returns {number} Quantidade de dias incluindo as duas extremidades.
 */
function diasDark(inicio, fim) {
    return Math.round((instanteDark(fim) - instanteDark(inicio)) / 86400000) + 1;
}

/**
 * Calcula a data inicial respeitando a janela de dados carregada e o limite de um ano.
 * @param {string} fim Data final ISO, mantida fixa ao usar os botões de período.
 * @param {number} quantidade Duração desejada, entre 1 e 365 dias.
 * @param {string} limite Primeira data disponível na série.
 * @returns {string} Data inicial ISO ajustada ao limite.
 */
function calcularInicioDark(fim, quantidade, limite) {
    const dias = Math.max(1, Math.min(365, quantidade));
    const inicio = new Date(instanteDark(fim) - (dias - 1) * 86400000).toISOString().slice(0, 10);

    return inicio < limite ? limite : inicio;
}

/**
 * Escolhe a próxima duração ao ampliar ou reduzir a janela.
 * @param {number} atual Quantidade atual de dias.
 * @param {number} direcao Positivo amplia; negativo reduz.
 * @returns {number} Próxima duração entre um dia e 365 dias.
 */
function proximaEtapaDark(atual, direcao) {
    if (direcao > 0) {
        for (const etapa of etapasDark) {
            if (etapa > atual) {
                return etapa;
            }
        }

        return 365;
    }

    for (let indice = etapasDark.length - 1; indice >= 0; indice--) {
        if (etapasDark[indice] < atual) {
            return etapasDark[indice];
        }
    }

    return 1;
}

/**
 * Filtra e agrupa as quatro medidas com as mesmas datas de início e fim.
 * @param {Array<Object>} serie Série diária com energia, horas extras, diárias e despesas em reais.
 * @param {string} inicio Data inicial ISO.
 * @param {string} fim Data final ISO.
 * @returns {Object} Rótulos, séries agrupadas, totais e indicação de agrupamento mensal.
 */
function agruparDark(serie, inicio, fim) {
    const mensal = diasDark(inicio, fim) > 62;
    const grupos = new Map();
    const chaves = ['energia', 'horas', 'diarias', 'despesas'];
    const resultado = {
        mensal: mensal,
        rotulos: [],
        energia: [],
        horas: [],
        diarias: [],
        despesas: [],
        totais: { energia: 0, horas: 0, diarias: 0, despesas: 0 }
    };

    for (const dia of serie) {
        if (dia.data < inicio || dia.data > fim) {
            continue;
        }

        const periodo = mensal ? dia.data.slice(0, 7) : dia.data;

        if (!grupos.has(periodo)) {
            grupos.set(periodo, { energia: 0, horas: 0, diarias: 0, despesas: 0 });
        }

        for (const chave of chaves) {
            grupos.get(periodo)[chave] += Number(dia[chave]);
            resultado.totais[chave] += Number(dia[chave]);
        }
    }

    for (const [periodo, valores] of grupos) {
        const rotulo = mensal ? periodo.slice(5, 7) + '/' + periodo.slice(0, 4) : dataTextoDark(periodo);
        resultado.rotulos.push(rotulo);

        for (const chave of chaves) {
            resultado[chave].push(Math.round(valores[chave] * 100) / 100);
        }
    }

    return resultado;
}

/**
 * Monta a configuração de um gráfico na paleta escura.
 * @param {string} chave Energia ou comparativo de horas extras e despesas.
 * @param {Object} agrupado Resultado de agruparDark().
 * @returns {Object} Configuração completa aceita pelo Chart.js.
 */
function configuracaoDark(chave, agrupado) {
    const cores = { energia: '#a78bfa', comparativo: '#f4be70', despesas: '#60d8ae' };
    const nomes = { energia: 'Produção (kWh)', comparativo: 'Diárias (R$)', despesas: 'Despesas totais (R$)' };
    const unidades = { energia: 'kWh', comparativo: 'R$', despesas: 'R$' };
    const principal = chave === 'energia';

    const configuracao = {
        type: principal ? 'line' : 'bar',
        data: {
            labels: agrupado.rotulos,
            datasets: [{
                label: nomes[chave],
                data: agrupado[chave],
                borderColor: cores[chave],
                backgroundColor: principal ? 'rgba(167, 139, 250, 0.12)' : cores[chave],
                borderWidth: principal ? 2 : 0,
                fill: principal,
                tension: 0.3,
                pointRadius: agrupado.rotulos.length < 15 ? 3 : 0,
                pointHoverRadius: 5,
                borderRadius: 4,
                maxBarThickness: 24
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            locale: 'pt-BR',
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#10121b',
                    titleColor: '#e2e8f0',
                    bodyColor: '#cbd5e1',
                    borderColor: '#34384b',
                    borderWidth: 1,
                    padding: 12
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: { color: '#7e879f', maxTicksLimit: principal ? 10 : 4, maxRotation: 0, font: { size: 10 } }
                },
                y: {
                    beginAtZero: true,
                    border: { display: false },
                    grid: { color: 'rgba(148, 163, 184, 0.07)' },
                    title: { display: true, text: unidades[chave], color: '#7e879f', font: { size: 10 } },
                    ticks: { color: '#7e879f', maxTicksLimit: 5, font: { size: 10 } }
                }
            }
        }
    };

    if (chave === 'comparativo') {
        configuracao.data.datasets = [
            {
                type: 'bar',
                label: 'Diárias (R$)',
                data: agrupado.diarias,
                yAxisID: 'diarias',
                backgroundColor: '#f4be70',
                borderRadius: 4,
                maxBarThickness: 32,
                order: 2
            },
            {
                type: 'line',
                label: 'Horas extras (R$)',
                data: agrupado.horas,
                yAxisID: 'horas',
                borderColor: '#67d9e8',
                backgroundColor: '#67d9e8',
                borderWidth: 2,
                tension: 0.25,
                pointRadius: agrupado.rotulos.length < 15 ? 3 : 0,
                fill: false,
                order: 1
            }
        ];
        configuracao.options.plugins.legend = {
            display: true,
            position: 'bottom',
            labels: { color: '#cbd5e1', boxWidth: 12, padding: 20 }
        };
        const eixoBase = configuracao.options.scales.y;
        delete configuracao.options.scales.y;
        configuracao.options.scales.diarias = {
            ...eixoBase,
            position: 'left',
            title: { display: true, text: 'Diárias (R$)', color: '#f4be70' }
        };
        configuracao.options.scales.horas = {
            ...eixoBase,
            position: 'right',
            grid: { drawOnChartArea: false },
            title: { display: true, text: 'Horas extras (R$)', color: '#67d9e8' }
        };
        configuracao.options.scales.x.ticks.maxTicksLimit = 10;
    }

    return configuracao;
}

/**
 * Atualiza a tabela inferior com os lançamentos de produção do período visível.
 * Parâmetros: nenhum. Usa dadosDark e inicioDark.
 * @returns {void} Substitui as linhas da tabela usando texto seguro.
 */
function atualizarTabelaDark() {
    const corpo = document.getElementById('tabela-producao');
    corpo.replaceChildren();
    let quantidade = 0;

    for (const registro of dadosDark.producao) {
        if (registro.data_producao < inicioDark || registro.data_producao > dadosDark.fim) {
            continue;
        }

        const linha = document.createElement('tr');
        linha.className = 'border-b border-white/5';
        const valores = ['#' + registro.id, dataTextoDark(registro.data_producao), Number(registro.kwh_gerado).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })];

        for (let indice = 0; indice < valores.length; indice++) {
            const celula = document.createElement('td');
            celula.className = indice === 2 ? 'px-4 py-3 text-right' : 'px-4 py-3';
            celula.textContent = valores[indice];
            linha.appendChild(celula);
        }

        corpo.appendChild(linha);
        quantidade++;
    }

    if (quantidade === 0) {
        const linha = document.createElement('tr');
        const celula = document.createElement('td');
        celula.colSpan = 3;
        celula.className = 'px-4 py-8 text-center text-slate-500';
        celula.textContent = 'Nenhum lançamento de produção neste período.';
        linha.appendChild(celula);
        corpo.appendChild(linha);
    }
}

/**
 * Monta linhas de produção de todas as usinas, preenchendo dias sem lançamento com zero.
 * @param {Object} agrupado Agrupamento da janela selecionada, com os rótulos do eixo horizontal.
 * @returns {Object} Configuração de linhas em kWh com uma cor e legenda por usina.
 */
function configuracaoTodasUsinasDark(agrupado) {
    const configuracao = configuracaoDark('energia', agrupado);
    const cores = ['#a78bfa', '#38bdf8', '#2dd4bf', '#fbbf24', '#fb7185', '#cbd5e1'];
    configuracao.data.datasets = dadosDark.todasUsinas.map((usina, indice) => {
        const serie = dadosDark.serie.map(dia => ({
            data: dia.data,
            energia: Number(usina.valores[dia.data] || 0),
            horas: 0,
            diarias: 0,
            despesas: 0
        }));
        const cor = cores[indice] || `hsl(${(indice * 137.5) % 360}, 70%, 65%)`;

        return {
            label: usina.nome,
            data: agruparDark(serie, inicioDark, dadosDark.fim).energia,
            borderColor: cor,
            backgroundColor: cor,
            borderWidth: 2,
            fill: false,
            tension: 0,
            pointRadius: 0,
            pointHoverRadius: 4
        };
    });
    configuracao.options.plugins.legend = {
        display: true,
        position: 'top',
        align: 'start',
        labels: { color: '#cbd5e1', usePointStyle: true, boxWidth: 8, padding: 16 }
    };

    return configuracao;
}

/**
 * Sincroniza os gráficos, os totais, as datas e a tabela.
 * Parâmetros: nenhum. Usa o intervalo atual em inicioDark e dadosDark.fim.
 * @returns {void} Atualiza a página e registra o filtro na URL sem recarregar.
 */
function atualizarPainelDark() {
    const agrupado = agruparDark(dadosDark.serie, inicioDark, dadosDark.fim);
    const quantidade = diasDark(inicioDark, dadosDark.fim);

    for (const chave of ['energia', 'horas', 'despesas', 'diarias']) {
        document.getElementById('total-' + chave).textContent = agrupado.totais[chave].toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    for (const chave of ['energia', 'comparativo', 'despesas', 'usinas']) {
        if (typeof Chart !== 'undefined') {
            const configuracao = chave === 'usinas' ? configuracaoTodasUsinasDark(agrupado) : configuracaoDark(chave, agrupado);
            if (graficosDark[chave]) {
                graficosDark[chave].data = configuracao.data;
                graficosDark[chave].update('none');
            } else {
                graficosDark[chave] = new Chart(document.getElementById('grafico-' + chave), configuracao);
            }
        }
    }

    document.getElementById('inicio').value = inicioDark;
    document.getElementById('fim').value = dadosDark.fim;
    document.getElementById('periodo-visivel').textContent = dataTextoDark(inicioDark) + ' — ' + dataTextoDark(dadosDark.fim) + ' · ' + quantidade + (quantidade === 1 ? ' dia' : ' dias');
    document.getElementById('agrupamento').textContent = agrupado.mensal ? 'Valores por mês' : 'Valores por dia';

    for (const botao of document.querySelectorAll('[data-dias]')) {
        botao.setAttribute('aria-pressed', String(Number(botao.dataset.dias) === quantidade));
    }

    atualizarTabelaDark();

    const url = new URL(window.location.href);
    url.searchParams.set('inicio', inicioDark);
    url.searchParams.set('fim', dadosDark.fim);
    url.searchParams.set('usina_id', dadosDark.usinaId);
    window.history.replaceState(null, '', url);
}

/**
 * Aplica uma nova duração mantendo fixa a data final carregada.
 * @param {number} quantidade Duração solicitada, limitada a 365 dias.
 * @returns {void} Atualiza todos os componentes do painel.
 */
function mudarPeriodoDark(quantidade) {
    inicioDark = calcularInicioDark(dadosDark.fim, quantidade, dadosDark.limiteInicial);
    atualizarPainelDark();
}

/**
 * Aplica uma duração escolhida nos atalhos de período.
 * @param {MouseEvent} evento Clique em um botão com data-dias.
 * @returns {void} Sincroniza o painel com a duração selecionada.
 */
function selecionarPeriodoDark(evento) {
    mudarPeriodoDark(Number(evento.currentTarget.dataset.dias));
}

/**
 * Permite controlar o intervalo pelo teclado no gráfico principal.
 * @param {KeyboardEvent} evento Teclas menos ampliam e mais reduzem o período.
 * @returns {void} Aplica o controle de período pelo teclado.
 */
function tecladoPeriodoDark(evento) {
    if (!['+', '=', '-'].includes(evento.key)) {
        return;
    }

    evento.preventDefault();
    const direcao = evento.key === '-' ? 1 : -1;
    mudarPeriodoDark(proximaEtapaDark(diasDark(inicioDark, dadosDark.fim), direcao));
}

/**
 * Reenvia o filtro ao escolher outra usina, preservando as datas dos campos.
 * Parâmetros: nenhum. Lê o formulário filtro-dark.
 * @returns {void} Solicita a nova usina pelo método GET.
 */
function selecionarUsinaDark() {
    document.getElementById('filtro-dark').requestSubmit();
}

/**
 * Abre ou fecha o menu lateral em telas pequenas.
 * Parâmetros: nenhum. Usa menu-dark e botao-menu-dark.
 * @returns {void} Alterna as classes de visibilidade e aria-expanded.
 */
function alternarMenuDark() {
    const menu = document.getElementById('menu-dark');
    const aberto = menu.classList.contains('hidden');
    menu.classList.toggle('hidden', !aberto);
    menu.classList.toggle('flex', aberto);
    document.getElementById('botao-menu-dark').setAttribute('aria-expanded', String(aberto));
}

/**
 * Inicializa o novo dashboard após o carregamento do HTML.
 * Parâmetros: nenhum. Lê o JSON de dados-dark.
 * @returns {void} Registra os controles e cria os gráficos quando há usina selecionada.
 */
function iniciarDark() {
    dadosDark = JSON.parse(document.getElementById('dados-dark').textContent);
    document.getElementById('botao-menu-dark').addEventListener('click', alternarMenuDark);
    document.getElementById('fechar-menu-dark').addEventListener('click', alternarMenuDark);
    document.getElementById('usina_id').addEventListener('change', selecionarUsinaDark);

    if (!dadosDark.usinaId) {
        return;
    }

    inicioDark = dadosDark.inicio;

    if (typeof Chart === 'undefined') {
        document.getElementById('falha-graficos').hidden = false;
    }

    atualizarPainelDark();
    document.getElementById('area-producao').addEventListener('keydown', tecladoPeriodoDark);

    for (const botao of document.querySelectorAll('[data-dias]')) {
        botao.addEventListener('click', selecionarPeriodoDark);
    }
}

document.addEventListener('DOMContentLoaded', iniciarDark);
```

## public_html/assets/js/main.js

```javascript
'use strict';

/**
 * Inicia os eventos da página depois que o HTML estiver carregado.
 * Parâmetros: nenhum.
 * @returns {void} Registra os eventos e desenha os gráficos existentes.
 */
function iniciarPagina() {
    const menu = document.getElementById('alternar-menu');
    const pesquisa = document.getElementById('pesquisar');
    const formularios = document.querySelectorAll('.form-excluir');

    if (menu) {
        menu.addEventListener('click', alternarMenu);
        atualizarEstadoMenu();
        window.addEventListener('resize', atualizarEstadoMenu);
    }

    if (pesquisa) {
        pesquisa.addEventListener('input', pesquisarTabela);
        pesquisarTabela();
    }

    for (const campo of document.querySelectorAll('#producao-inicio, #producao-fim')) {
        campo.addEventListener('input', pesquisarTabela);
    }

    for (const formulario of formularios) {
        formulario.addEventListener('submit', confirmarExclusao);
    }

    const links = document.querySelectorAll('#menu a');

    for (const link of links) {
        if (link.pathname === window.location.pathname) {
            link.classList.add('ativo');
            link.setAttribute('aria-current', 'page');
        }
    }

    carregarGraficos();
}

/**
 * Atualiza o estado acessível do botão conforme o tamanho da tela e o menu.
 * Parâmetros: nenhum.
 * @returns {void} Altera aria-expanded do botão Menu.
 */
function atualizarEstadoMenu() {
    const pequeno = window.innerWidth <= 760;
    const alterado = document.body.classList.contains('menu-alterado');
    const aberto = pequeno ? alterado : !alterado;

    document.getElementById('alternar-menu').setAttribute('aria-expanded', String(aberto));
}

/**
 * Abre ou fecha o menu lateral.
 * Parâmetros: nenhum.
 * @returns {void} Altera a classe do corpo e o estado acessível do botão.
 */
function alternarMenu() {
    document.body.classList.toggle('menu-alterado');
    atualizarEstadoMenu();
}

/**
 * Prepara um texto para pesquisa sem diferença de maiúsculas e acentos.
 * @param {string} texto Texto original.
 * @returns {string} Texto sem acentos e em letras minúsculas.
 */
function normalizarTexto(texto) {
    return texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
}

/**
 * Filtra a tabela por texto e, na produção, pelo período inclusivo informado.
 * Parâmetros: nenhum. Lê a pesquisa, as datas opcionais e as linhas do documento.
 * @returns {void} Atualiza as linhas visíveis, o aviso de período e o total em kWh.
 */
function pesquisarTabela() {
    const pesquisa = normalizarTexto(document.getElementById('pesquisar').value.trim());
    const linhas = document.querySelectorAll('.linha-registro');
    const inicio = document.getElementById('producao-inicio')?.value || '';
    const fim = document.getElementById('producao-fim')?.value || '';
    const erroPeriodo = document.getElementById('erro-periodo-producao');
    const totalProducao = document.getElementById('total-producao');
    const periodoInvalido = Boolean(inicio && fim && inicio > fim);
    let quantidade = 0;
    let total = 0;

    if (erroPeriodo) {
        erroPeriodo.hidden = !periodoInvalido;
    }

    for (const linha of linhas) {
        const data = linha.dataset.data || '';
        linha.hidden = periodoInvalido
            || !normalizarTexto(linha.textContent).includes(pesquisa)
            || Boolean(inicio && data < inicio)
            || Boolean(fim && data > fim);

        if (!linha.hidden) {
            quantidade++;
            total += Number(linha.dataset.kwh || 0);
        }
    }

    document.getElementById('sem-registros').hidden = quantidade > 0;

    if (totalProducao) {
        totalProducao.textContent = total.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
}

/**
 * Solicita confirmação antes de enviar uma exclusão.
 * @param {SubmitEvent} evento Evento de envio do formulário.
 * @returns {void} Cancela o envio caso o usuário escolha Cancelar.
 */
function confirmarExclusao(evento) {
    if (!window.confirm('Deseja excluir este registro?')) {
        evento.preventDefault();
    }
}

/**
 * Lê os dados do dashboard e cria os três gráficos de cada usina.
 * Parâmetros: nenhum. Lê o elemento dados-graficos.
 * @returns {void} Desenha os gráficos ou mostra uma mensagem de falha do CDN.
 */
function carregarGraficos() {
    const elemento = document.getElementById('dados-graficos');

    if (!elemento) {
        return;
    }

    if (typeof Chart === 'undefined') {
        document.getElementById('erro-graficos').hidden = false;
        return;
    }

    const usinas = JSON.parse(elemento.textContent);

    if (document.getElementById('producao-todas-usinas')) {
        criarGraficoTodasUsinas(JSON.parse(document.getElementById('dados-todas-usinas').textContent));
    }

    for (const usina of usinas) {
        criarGraficoEnergia(usina);
        criarGraficoComparativo(usina);
        criarGraficoDespesasTotais(usina);
    }
}

/**
 * Mostra a produção de energia sem misturar unidades financeiras.
 * @param {Object} usina Dados com id, rotulos e energia em kWh.
 * @returns {void} Desenha o gráfico de produção da usina.
 */
function criarGraficoEnergia(usina) {
    new Chart(document.getElementById('energia-' + usina.id), {
        type: 'bar',
        data: {
            labels: usina.rotulos,
            datasets: [{
                label: 'Energia (kWh)',
                data: usina.energia,
                backgroundColor: '#168a72'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            locale: 'pt-BR',
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'kWh' }
                }
            }
        }
    });
}

/**
 * Compara diárias em barras e horas extras em linha, em eixos independentes.
 * @param {Object} usina Dados com id, rotulos, diarias e horas, ambos em reais.
 * @returns {void} Desenha cada série em seu próprio eixo vertical em R$.
 */
function criarGraficoComparativo(usina) {
    new Chart(document.getElementById('comparativo-' + usina.id), {
        type: 'bar',
        data: {
            labels: usina.rotulos,
            datasets: [
                {
                    type: 'bar',
                    label: 'Diárias (R$)',
                    data: usina.diarias,
                    yAxisID: 'diarias',
                    backgroundColor: '#367bb5',
                    order: 2
                },
                {
                    type: 'line',
                    label: 'Horas extras (R$)',
                    data: usina.horas,
                    yAxisID: 'horas',
                    borderColor: '#e18a16',
                    backgroundColor: '#e18a16',
                    borderWidth: 2,
                    tension: 0.25,
                    fill: false,
                    order: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            locale: 'pt-BR',
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'bottom' } },
            scales: {
                diarias: {
                    position: 'left',
                    beginAtZero: true,
                    title: { display: true, text: 'Diárias (R$)' }
                },
                horas: {
                    position: 'right',
                    beginAtZero: true,
                    grid: { drawOnChartArea: false },
                    title: { display: true, text: 'Horas extras (R$)' }
                }
            }
        }
    });
}

/**
 * Mostra o total de todas as categorias de despesa no período selecionado.
 * @param {Object} usina Dados com id, rotulos e despesas em reais.
 * @returns {void} Desenha um gráfico de barras abaixo do comparativo.
 */
function criarGraficoDespesasTotais(usina) {
    new Chart(document.getElementById('despesas-' + usina.id), {
        type: 'bar',
        data: {
            labels: usina.rotulos,
            datasets: [{ label: 'Despesas totais (R$)', data: usina.despesas, backgroundColor: '#168a72' }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            locale: 'pt-BR',
            scales: { y: { beginAtZero: true, title: { display: true, text: 'R$' } } }
        }
    });
}

/**
 * Compara a produção de todas as usinas com linhas coloridas e legenda no topo.
 * @param {Object} dados Rótulos do período e lista de usinas com nome e energia em kWh.
 * @returns {void} Desenha o gráfico de linhas acima dos painéis individuais.
 */
function criarGraficoTodasUsinas(dados) {
    const cores = ['#7c3aed', '#0284c7', '#168a72', '#b7791f', '#e11d48', '#475569'];

    new Chart(document.getElementById('producao-todas-usinas'), {
        type: 'line',
        data: {
            labels: dados.rotulos,
            datasets: dados.usinas.map((usina, indice) => ({
                label: usina.nome,
                data: usina.energia,
                borderColor: cores[indice] || `hsl(${(indice * 137.5) % 360}, 65%, 40%)`,
                backgroundColor: cores[indice] || `hsl(${(indice * 137.5) % 360}, 65%, 40%)`,
                borderWidth: 2,
                fill: false,
                tension: 0,
                pointRadius: 0,
                pointHoverRadius: 4
            }))
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            locale: 'pt-BR',
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'start',
                    labels: { usePointStyle: true, boxWidth: 8, padding: 16 }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { maxTicksLimit: 12, maxRotation: 0 } },
                y: { beginAtZero: true, title: { display: true, text: 'kWh' } }
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', iniciarPagina);
```

## public_html/biblioteca.php

```php
<?php

require_once __DIR__ . '/db.php';

/**
 * Converte um valor em texto seguro para exibição no HTML.
 * @param mixed $valor Texto ou número que será exibido.
 * @return string Texto com os caracteres especiais escapados.
 */
function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Lê um campo de GET ou POST, removendo espaços nas extremidades.
 * @param array $origem Normalmente $_GET ou $_POST.
 * @param string $campo Nome do campo recebido.
 * @param string $padrao Valor para campo ausente ou que não seja texto.
 * @return string Valor recebido ou valor padrão.
 */
function lerCampo($origem, $campo, $padrao = '')
{
    if (isset($origem[$campo]) && is_string($origem[$campo])) {
        return trim($origem[$campo]);
    }

    return $padrao;
}

/**
 * Monta um endereço dentro da aplicação.
 * @param string $caminho Caminho relativo à pasta public_html.
 * @return string Endereço da página ou do arquivo.
 */
function endereco($caminho)
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($caminho, '/');
}

/**
 * Envia o navegador para outra página do sistema.
 * @param string $caminho Caminho relativo à pasta public_html.
 * @return void Envia o redirecionamento e encerra a execução.
 */
function redirecionar($caminho)
{
    header('Location: ' . endereco($caminho), true, 303);
    exit();
}

/**
 * Obtém o identificador do registro na URL.
 * Parâmetros: nenhum. Lê id de $_GET.
 * @return int Zero para inclusão ou ID positivo; rejeita valores inválidos.
 */
function obterId()
{
    $id = filter_var(lerCampo($_GET, 'id', '0'), FILTER_VALIDATE_INT);

    if ($id === false || $id < 0 || $id > 4294967295) {
        http_response_code(400);
        exit('Identificador inválido.');
    }

    return $id;
}

/**
 * Cria ou recupera o token de proteção dos formulários.
 * Parâmetros: nenhum. Utiliza $_SESSION.
 * @return string Token aleatório de 64 caracteres.
 */
function obterToken()
{
    if (empty($_SESSION['token'])) {
        $_SESSION['token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['token'];
}

/**
 * Confere o token enviado por um formulário.
 * Parâmetros: nenhum. Lê token de $_POST e compara com a sessão.
 * @return void Continua ou encerra com HTTP 403 se o token não conferir.
 */
function validarToken()
{
    if (!hash_equals(obterToken(), lerCampo($_POST, 'token'))) {
        http_response_code(403);
        exit('Formulário expirado ou inválido. Atualize a página e tente novamente.');
    }
}

/**
 * Verifica o login, a inatividade e as permissões atuais no banco.
 * Parâmetros: nenhum. Utiliza a sessão e conectarBanco().
 * @return void Continua autenticado ou redireciona para o login.
 */
function exigirLogin()
{
    if (empty($_SESSION['usuario_id'])) {
        redirecionar('index.php');
    }

    if (time() - ($_SESSION['ultima_atividade'] ?? 0) > 1800) {
        $_SESSION = [];
        session_regenerate_id(true);
        redirecionar('index.php');
    }

    $pdo = conectarBanco();
    $consulta = $pdo->prepare('SELECT id, nome, pode_editar, pode_excluir FROM usuarios WHERE id = ?');
    $consulta->execute([$_SESSION['usuario_id']]);
    $usuario = $consulta->fetch();

    if (!$usuario) {
        $_SESSION = [];
        session_regenerate_id(true);
        redirecionar('index.php');
    }

    $_SESSION['nome'] = $usuario['nome'];
    $_SESSION['pode_editar'] = $usuario['pode_editar'];
    $_SESSION['pode_excluir'] = $usuario['pode_excluir'];
    $_SESSION['ultima_atividade'] = time();
}

/**
 * Verifica a permissão para incluir e editar registros.
 * Parâmetros: nenhum. Lê pode_editar da sessão.
 * @return bool Verdadeiro quando a permissão é S.
 */
function podeEditar()
{
    return ($_SESSION['pode_editar'] ?? 'N') === 'S';
}

/**
 * Verifica a permissão para excluir registros.
 * Parâmetros: nenhum. Lê pode_excluir da sessão.
 * @return bool Verdadeiro quando a permissão é S.
 */
function podeExcluir()
{
    return ($_SESSION['pode_excluir'] ?? 'N') === 'S';
}

/**
 * Bloqueia inclusões e edições sem permissão.
 * Parâmetros: nenhum. Utiliza podeEditar().
 * @return void Continua ou encerra a página com HTTP 403.
 */
function exigirEdicao()
{
    if (!podeEditar()) {
        http_response_code(403);
        exit('Você não tem permissão para incluir ou editar registros.');
    }
}

/**
 * Bloqueia exclusões sem permissão.
 * Parâmetros: nenhum. Utiliza podeExcluir().
 * @return void Continua ou encerra a página com HTTP 403.
 */
function exigirExclusao()
{
    if (!podeExcluir()) {
        http_response_code(403);
        exit('Você não tem permissão para excluir registros.');
    }
}

/**
 * Restringe a gestão de usuários a quem possui as duas permissões.
 * Parâmetros: nenhum. Utiliza podeEditar() e podeExcluir().
 * @return void Continua ou encerra a página com HTTP 403.
 */
function exigirGestor()
{
    if (!podeEditar() || !podeExcluir()) {
        http_response_code(403);
        exit('A gestão de usuários exige permissão para editar e excluir.');
    }
}

/**
 * Verifica se uma data existe e pode ser gravada no MySQL.
 * @param string $valor Data no formato AAAA-MM-DD.
 * @return bool Verdadeiro para uma data válida entre os anos 1000 e 9999.
 */
function dataValida($valor)
{
    $data = DateTime::createFromFormat('!Y-m-d', $valor);

    return $data !== false
        && $data->format('Y-m-d') === $valor
        && $valor >= '1000-01-01'
        && $valor <= '9999-12-31';
}

/**
 * Valida um valor positivo ou zero para uma coluna DECIMAL(12,2).
 * @param string $valor Número sem separador de milhar e com ponto decimal.
 * @return bool Verdadeiro para até dez inteiros e duas casas decimais.
 */
function numeroValido($valor)
{
    return preg_match('/^\d{1,10}(\.\d{1,2})?$/D', $valor) === 1;
}

/**
 * Confere os dois dígitos verificadores de um CPF.
 * @param string $cpf CPF com onze números, sem pontuação.
 * @return bool Verdadeiro quando o CPF é válido.
 */
function cpfValido($cpf)
{
    if (!preg_match('/^\d{11}$/D', $cpf) || preg_match('/^(\d)\1{10}$/D', $cpf)) {
        return false;
    }

    for ($tamanho = 9; $tamanho <= 10; $tamanho++) {
        $soma = 0;

        for ($posicao = 0; $posicao < $tamanho; $posicao++) {
            $soma += (int) $cpf[$posicao] * ($tamanho + 1 - $posicao);
        }

        $resto = $soma % 11;
        $digito = $resto < 2 ? 0 : 11 - $resto;

        if ((int) $cpf[$tamanho] !== $digito) {
            return false;
        }
    }

    return true;
}

/**
 * Confere se uma usina, um técnico ou uma categoria existe.
 * @param PDO $pdo Conexão com o banco.
 * @param string $tabela Somente usinas, tecnicos ou categoria são aceitos.
 * @param string $id Identificador recebido pelo formulário.
 * @return bool Verdadeiro quando o registro existe.
 */
function vinculoExiste($pdo, $tabela, $id)
{
    if (!in_array($tabela, ['usinas', 'tecnicos', 'categoria'], true) || !ctype_digit($id)) {
        return false;
    }

    $chave = $tabela === 'categoria' ? 'id_categoria' : 'id';
    $consulta = $pdo->prepare('SELECT ' . $chave . ' FROM ' . $tabela . ' WHERE ' . $chave . ' = ?');
    $consulta->execute([$id]);

    return $consulta->fetch() !== false;
}

/**
 * Traduz um erro SQL para uma mensagem sem detalhes internos do servidor.
 * @param PDOException $erro Exceção gerada pela operação SQL.
 * @return string Mensagem para exibir ao usuário.
 */
function mensagemBanco($erro)
{
    error_log($erro->getMessage());

    if ($erro->getCode() === '23000') {
        return 'Há um registro duplicado ou um vínculo que impede esta operação.';
    }

    return 'Não foi possível concluir a operação no banco de dados.';
}

/**
 * Formata um número no padrão brasileiro.
 * @param mixed $valor Número que será exibido.
 * @return string Número com duas casas decimais.
 */
function formatarNumero($valor)
{
    return number_format((float) $valor, 2, ',', '.');
}

/**
 * Formata uma data do MySQL para exibição.
 * @param string $valor Data no formato AAAA-MM-DD.
 * @return string Data DD/MM/AAAA ou texto vazio para uma data inválida.
 */
function formatarData($valor)
{
    if (!dataValida($valor)) {
        return '';
    }

    return date('d/m/Y', strtotime($valor));
}
```

## public_html/config.php

```php
<?php

// Configuração local do XAMPP.
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'gerene_local');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('BASE_URL', getenv('BASE_URL') ?: '/gerene');
define('NOME_EMPRESA', 'GERENE');

date_default_timezone_set('America/Sao_Paulo');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_name('gerene');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/'
    ]);
    session_start();
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
```

## public_html/db.php

```php
<?php

require_once __DIR__ . '/config.php';

/**
 * Abre a conexão PDO e a reutiliza durante a requisição.
 *
 * Parâmetros: nenhum. Lê os dados de config.php.
 * @return PDO Conexão com o MySQL; encerra a página se não conseguir conectar.
 */
function conectarBanco()
{
    static $conexao = null;

    if ($conexao === null) {
        try {
            $endereco = 'mysql:host=' . DB_HOST;
            $endereco .= ';port=' . DB_PORT;
            $endereco .= ';dbname=' . DB_NAME;
            $endereco .= ';charset=utf8mb4';

            $conexao = new PDO($endereco, DB_USER, DB_PASS);
            $conexao->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conexao->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $conexao->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $erro) {
            error_log($erro->getMessage());
            http_response_code(503);
            exit('Não foi possível conectar. Confira config.php e inicie o MySQL no XAMPP.');
        }
    }

    return $conexao;
}
```

## public_html/index.php

```php
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
```

## public_html/logout.php

```php
<?php

require_once __DIR__ . '/biblioteca.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Utilize o botão Sair.');
}

validarToken();
$_SESSION = [];
$cookie = session_get_cookie_params();

setcookie(session_name(), '', [
    'expires' => time() - 3600,
    'path' => $cookie['path'],
    'secure' => $cookie['secure'],
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_destroy();
redirecionar('index.php');
```

## public_html/schema.sql

```sql
-- GERENE: selecione o banco gerene_local antes de importar este arquivo.
-- O banco pode ser criado com: CREATE DATABASE gerene_local CHARACTER SET utf8mb4;
SET NAMES utf8mb4;

CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    pode_editar ENUM('S', 'N') NOT NULL DEFAULT 'N',
    pode_excluir ENUM('S', 'N') NOT NULL DEFAULT 'N',
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuario_email (email),
    KEY idx_usuario_permissoes (pode_editar, pode_excluir)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usinas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    tipo ENUM('Fotovoltaica', 'Hidrelétrica') NOT NULL,
    capacidade_kw DECIMAL(12,2) UNSIGNED NOT NULL,
    localizacao VARCHAR(255) NOT NULL,
    status ENUM('Ativa', 'Inativa', 'Manutenção') NOT NULL DEFAULT 'Ativa',
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_usina_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tecnicos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    cpf CHAR(11) NOT NULL,
    especialidade VARCHAR(120) NOT NULL,
    telefone VARCHAR(25) NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tecnico_cpf (cpf),
    KEY idx_tecnico_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE producao_energia (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usina_id INT UNSIGNED NOT NULL,
    data_producao DATE NOT NULL,
    kwh_gerado DECIMAL(12,2) UNSIGNED NOT NULL,
    UNIQUE KEY uq_producao_usina_data (usina_id, data_producao),
    KEY idx_producao_data (data_producao),
    CONSTRAINT fk_producao_usina FOREIGN KEY (usina_id)
        REFERENCES usinas(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE horas_extras (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usina_id INT UNSIGNED NOT NULL,
    tecnico_id INT UNSIGNED NOT NULL,
    data DATE NOT NULL,
    quantidade_horas DECIMAL(12,2) UNSIGNED NOT NULL,
    valor_total DECIMAL(12,2) UNSIGNED NOT NULL,
    KEY idx_horas_usina_data (usina_id, data),
    KEY idx_horas_tecnico (tecnico_id),
    CONSTRAINT fk_horas_usina FOREIGN KEY (usina_id)
        REFERENCES usinas(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_horas_tecnico FOREIGN KEY (tecnico_id)
        REFERENCES tecnicos(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE diarias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usina_id INT UNSIGNED NOT NULL,
    tecnico_id INT UNSIGNED NOT NULL,
    data DATE NOT NULL,
    valor_diaria DECIMAL(12,2) UNSIGNED NOT NULL,
    descricao TEXT NOT NULL,
    KEY idx_diarias_usina_data (usina_id, data),
    KEY idx_diarias_tecnico (tecnico_id),
    CONSTRAINT fk_diarias_usina FOREIGN KEY (usina_id)
        REFERENCES usinas(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_diarias_tecnico FOREIGN KEY (tecnico_id)
        REFERENCES tecnicos(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categoria (
    id_categoria INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descricao_categoria VARCHAR(120) NOT NULL,
    Centro_de_custo INT NULL,
    UNIQUE KEY uq_categoria_descricao (descricao_categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE despesas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usina_id INT UNSIGNED NOT NULL,
    data DATE NOT NULL,
    valor_despesa DECIMAL(12,2) UNSIGNED NOT NULL,
    descricao TEXT NOT NULL,
    categoria INT UNSIGNED NOT NULL,
    tecnico INT UNSIGNED NULL,
    KEY idx_despesas_categoria (categoria),
    KEY idx_despesas_tecnico (tecnico),
    KEY idx_material_usina_data (usina_id, data),
    CONSTRAINT fk_material_usina FOREIGN KEY (usina_id)
        REFERENCES usinas(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_despesas_categoria FOREIGN KEY (categoria)
        REFERENCES categoria(id_categoria) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_despesas_tecnico FOREIGN KEY (tecnico)
        REFERENCES tecnicos(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuário inicial. Senha: admin.
INSERT INTO usuarios (nome, email, senha_hash, pode_editar, pode_excluir)
VALUES (
    'Administrador',
    'admin@usina.com',
    '$2y$12$Vvvjkm3RKtN15XhGN05rm.Dh1gWWCCGYNpSJclntuZykW.PJmERm6',
    'S',
    'S'
);
```

## public_html/views/categoria/categoria.php

```php
<?php

require_once __DIR__ . '/../../biblioteca.php';

exigirLogin();
exigirEdicao();

$pdo = conectarBanco();
$id = obterId();
$descricao_categoria = '';
$Centro_de_custo = '';
$erros = [];

if ($id > 0) {
    $consulta = $pdo->prepare('SELECT * FROM categoria WHERE id_categoria = ?');
    $consulta->execute([$id]);
    $registro = $consulta->fetch();

    if (!$registro) {
        http_response_code(404);
        exit('Categoria não encontrada.');
    }

    $descricao_categoria = $registro['descricao_categoria'];
    $Centro_de_custo = $registro['Centro_de_custo'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarToken();
    $descricao_categoria = lerCampo($_POST, 'descricao_categoria');
    $Centro_de_custo = lerCampo($_POST, 'Centro_de_custo');

    if ($descricao_categoria === '' || iconv_strlen($descricao_categoria, 'UTF-8') > 120) {
        $erros[] = 'Descrição: preencha até 120 caracteres.';
    }

    // O campo aceita o intervalo completo de um INT do MySQL.
    if ($Centro_de_custo !== '') {
        $centroValidado = filter_var($Centro_de_custo, FILTER_VALIDATE_INT);

        if ($centroValidado === false || $centroValidado < -2147483648 || $centroValidado > 2147483647) {
            $erros[] = 'Centro de custo: informe um número inteiro entre -2147483648 e 2147483647.';
        }
    }

    if (empty($erros)) {
        try {
            if ($id > 0) {
                $consulta = $pdo->prepare(
                    'UPDATE categoria SET descricao_categoria = ?, Centro_de_custo = ?
                     WHERE id_categoria = ?'
                );
                $consulta->execute([$descricao_categoria, $Centro_de_custo === '' ? null : $Centro_de_custo, $id]);
            } else {
                $consulta = $pdo->prepare(
                    'INSERT INTO categoria (descricao_categoria, Centro_de_custo) VALUES (?, ?)'
                );
                $consulta->execute([$descricao_categoria, $Centro_de_custo === '' ? null : $Centro_de_custo]);
            }

            redirecionar('views/categoria/index_categoria.php?msg=sucesso');
        } catch (PDOException $excecao) {
            $erros[] = mensagemBanco($excecao);
        }
    }

    if ($erros) {
        http_response_code(422);
    }
}

$titulo = 'Categorias - ' . ($id > 0 ? 'Editar' : 'Incluir');
require __DIR__ . '/../header.php';

?>
            <section class="painel">
                <?php if ($erros): ?>
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
                            <label for="descricao_categoria">Descrição da categoria</label>
                            <input type="text" id="descricao_categoria" name="descricao_categoria"
                                   value="<?= escapar($descricao_categoria) ?>" maxlength="120" required>
                        </div>
                        <div class="campo">
                            <label for="Centro_de_custo">Centro de custo</label>
                            <input type="number" id="Centro_de_custo" name="Centro_de_custo"
                                   value="<?= escapar($Centro_de_custo) ?>" min="-2147483648" max="2147483647" step="1">
                            <small>Opcional. Deixe em branco quando ainda não houver um centro definido.</small>
                        </div>
                    </div>
                    <div class="acoes">
                        <button type="submit" class="botao">Gravar</button>
                        <a class="botao secundario" href="index_categoria.php">Voltar para pesquisa</a>
                    </div>
                </form>
            </section>
<?php require __DIR__ . '/../footer.php'; ?>
```

## public_html/views/categoria/index_categoria.php

```php
<?php

require_once __DIR__ . '/../../biblioteca.php';

exigirLogin();
$pdo = conectarBanco();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirExclusao();
    validarToken();
    $id = filter_var(lerCampo($_POST, 'id'), FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        http_response_code(400);
        exit('Identificador inválido.');
    }

    try {
        $consulta = $pdo->prepare('DELETE FROM categoria WHERE id_categoria = ?');
        $consulta->execute([$id]);

        if ($consulta->rowCount() === 0) {
            throw new RuntimeException('Categoria não encontrada.');
        }

        redirecionar('views/categoria/index_categoria.php?msg=sucesso');
    } catch (PDOException $excecao) {
        $_SESSION['mensagem_erro'] = mensagemBanco($excecao);
    } catch (RuntimeException $excecao) {
        $_SESSION['mensagem_erro'] = $excecao->getMessage();
    }

    redirecionar('views/categoria/index_categoria.php?error=1');
}

$consulta = $pdo->query('SELECT * FROM categoria ORDER BY descricao_categoria');
$registros = $consulta->fetchAll();
$titulo = 'Categorias';
require __DIR__ . '/../header.php';

?>
            <section class="painel">
                <label for="pesquisar">Pesquisar</label>
                <input type="search" id="pesquisar" placeholder="Descrição ou centro de custo">

                <div class="tabela-scroll max-h-[400px] overflow-y-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Descrição</th>
                                <th>Centro de custo</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registros as $registro): ?>
                                <tr class="linha-registro">
                                    <td><?= (int) $registro['id_categoria'] ?></td>
                                    <td><?= escapar($registro['descricao_categoria']) ?></td>
                                    <td><?= escapar($registro['Centro_de_custo'] ?? 'Não informado') ?></td>
                                    <td>
                                        <div class="acoes">
                                            <?php if (podeEditar()): ?>
                                                <a class="botao pequeno" href="categoria.php?id=<?= (int) $registro['id_categoria'] ?>">Editar</a>
                                            <?php endif; ?>
                                            <?php if (podeExcluir()): ?>
                                                <form method="post" class="form-excluir">
                                                    <input type="hidden" name="token" value="<?= escapar(obterToken()) ?>">
                                                    <input type="hidden" name="id" value="<?= (int) $registro['id_categoria'] ?>">
                                                    <button type="submit" class="botao perigo pequeno">Excluir</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="sem-registros" <?= $registros ? 'hidden' : '' ?>>
                                <td colspan="4">Nenhuma categoria encontrada.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <?php if (podeEditar()): ?>
                    <a class="botao incluir" href="categoria.php">Incluir Novo</a>
                <?php endif; ?>
            </section>
<?php require __DIR__ . '/../footer.php'; ?>
```

## public_html/views/dashboard.php

```php
<?php

require_once __DIR__ . '/../biblioteca.php';

exigirLogin();

$pdo = conectarBanco();
$inicio = lerCampo($_GET, 'inicio', '2026-01-01');
$fim = lerCampo($_GET, 'fim', date('Y-m-d'));
$erro = '';

// Atalhos mantêm a data final e aplicam o mesmo período a todos os gráficos.
$duracao = filter_var(lerCampo($_GET, 'dias'), FILTER_VALIDATE_INT);

if (in_array($duracao, [7, 30, 90, 180, 365], true) && dataValida($fim)) {
    $inicio = (new DateTimeImmutable($fim))->modify('-' . ($duracao - 1) . ' days')->format('Y-m-d');
}

// Valida o intervalo antes de executar as consultas.
if (!dataValida($inicio) || !dataValida($fim) || $inicio > $fim) {
    $erro = 'Informe um período válido. Exibindo o período padrão a partir de 01/01/2026.';
    $inicio = '2026-01-01';
    $fim = date('Y-m-d');
}

$dataInicial = new DateTime($inicio);
$dataFinal = new DateTime($fim);
$dias = $dataInicial->diff($dataFinal)->days;

if ($dias > 3660) {
    $erro = 'O período máximo é de 3.660 dias. Exibindo o período padrão a partir de 01/01/2026.';
    $inicio = '2026-01-01';
    $fim = date('Y-m-d');
    $dataInicial = new DateTime($inicio);
    $dataFinal = new DateTime($fim);
    $dias = $dataInicial->diff($dataFinal)->days;
}

$porMes = $dias > 62;
$formatoSql = $porMes ? '%Y-%m' : '%Y-%m-%d';
$formatoChave = $porMes ? 'Y-m' : 'Y-m-d';
$formatoRotulo = $porMes ? 'm/Y' : 'd/m/Y';
$passo = $porMes ? '+1 month' : '+1 day';
$cursor = new DateTime($porMes ? substr($inicio, 0, 7) . '-01' : $inicio);
$periodos = [];
$rotulos = [];

// Cria todos os períodos, inclusive os que não possuem lançamentos.
while ($cursor <= $dataFinal) {
    $periodos[] = $cursor->format($formatoChave);
    $rotulos[] = $cursor->format($formatoRotulo);
    $cursor->modify($passo);
}

$consulta = $pdo->query('SELECT id, nome, tipo FROM usinas ORDER BY id LIMIT 4');
$usinas = $consulta->fetchAll();
$graficos = [];

foreach ($usinas as $usina) {
    // As quatro consultas são separadas para não multiplicar valores por JOIN.
    $consulta = $pdo->prepare(
        "SELECT DATE_FORMAT(data_producao, '$formatoSql') AS periodo, SUM(kwh_gerado) AS total
         FROM producao_energia
         WHERE usina_id = ? AND data_producao BETWEEN ? AND ?
         GROUP BY periodo ORDER BY periodo"
    );
    $consulta->execute([$usina['id'], $inicio, $fim]);
    $energia = $consulta->fetchAll(PDO::FETCH_KEY_PAIR);

    $consulta = $pdo->prepare(
        "SELECT DATE_FORMAT(d.data, '$formatoSql') AS periodo, SUM(d.valor_despesa) AS total
         FROM despesas d
         INNER JOIN categoria c ON c.id_categoria = d.categoria
         WHERE d.usina_id = ? AND d.data BETWEEN ? AND ?
           AND UPPER(TRIM(c.descricao_categoria)) = ?
         GROUP BY periodo ORDER BY periodo"
    );
    $consulta->execute([$usina['id'], $inicio, $fim, 'HORAS EXTRAS']);
    $horas = $consulta->fetchAll(PDO::FETCH_KEY_PAIR);
    $consulta = $pdo->prepare(
        "SELECT DATE_FORMAT(d.data, '$formatoSql') AS periodo, SUM(d.valor_despesa) AS total
         FROM despesas d
         INNER JOIN categoria c ON c.id_categoria = d.categoria
         WHERE d.usina_id = ? AND d.data BETWEEN ? AND ?
           AND UPPER(TRIM(c.descricao_categoria)) = ?
         GROUP BY periodo ORDER BY periodo"
    );
    $consulta->execute([$usina['id'], $inicio, $fim, 'DIARIAS']);
    $diarias = $consulta->fetchAll(PDO::FETCH_KEY_PAIR);
    $consulta = $pdo->prepare(
        "SELECT DATE_FORMAT(data, '$formatoSql') AS periodo, SUM(valor_despesa) AS total
         FROM despesas
         WHERE usina_id = ? AND data BETWEEN ? AND ?
         GROUP BY periodo ORDER BY periodo"
    );
    $consulta->execute([$usina['id'], $inicio, $fim]);
    $despesas = $consulta->fetchAll(PDO::FETCH_KEY_PAIR);

    $grafico = [
        'id' => (int) $usina['id'],
        'nome' => $usina['nome'],
        'tipo' => $usina['tipo'],
        'rotulos' => $rotulos,
        'energia' => [],
        'horas' => [],
        'diarias' => [],
        'despesas' => []
    ];

    foreach ($periodos as $periodo) {
        $grafico['energia'][] = (float) ($energia[$periodo] ?? 0);
        $grafico['diarias'][] = (float) ($diarias[$periodo] ?? 0);
        $grafico['horas'][] = (float) ($horas[$periodo] ?? 0);
        $grafico['despesas'][] = (float) ($despesas[$periodo] ?? 0);
    }

    $graficos[] = $grafico;
}

// Compara todas as usinas, usando os mesmos períodos dos painéis individuais.
$todasUsinas = $pdo->query('SELECT id, nome FROM usinas ORDER BY id')->fetchAll();
$consulta = $pdo->prepare(
    "SELECT usina_id, DATE_FORMAT(data_producao, '$formatoSql') AS periodo, SUM(kwh_gerado) AS total
     FROM producao_energia
     WHERE data_producao BETWEEN ? AND ?
     GROUP BY usina_id, periodo"
);
$consulta->execute([$inicio, $fim]);
$producaoTodas = [];

foreach ($consulta as $registro) {
    $producaoTodas[$registro['usina_id']][$registro['periodo']] = (float) $registro['total'];
}

$comparacaoUsinas = ['rotulos' => $rotulos, 'usinas' => []];

foreach ($todasUsinas as $usina) {
    $valores = [];

    foreach ($periodos as $periodo) {
        $valores[] = $producaoTodas[$usina['id']][$periodo] ?? 0;
    }

    $comparacaoUsinas['usinas'][] = ['nome' => $usina['nome'], 'energia' => $valores];
}

$titulo = 'Dashboard';
require __DIR__ . '/header.php';

?>
            <?php if ($erro !== ''): ?>
                <div class="aviso erro" role="alert"><?= escapar($erro) ?></div>
            <?php endif; ?>

            <section class="painel">
                <form method="get" class="filtro-periodo">
                    <div>
                        <label for="inicio">Data Inicial</label>
                        <input type="date" id="inicio" name="inicio" value="<?= escapar($inicio) ?>" required>
                    </div>
                    <div>
                        <label for="fim">Data Final</label>
                        <input type="date" id="fim" name="fim" value="<?= escapar($fim) ?>" required>
                    </div>
                    <button type="submit" class="botao">Pesquisar</button>
                    <?php foreach ([7 => '7D', 30 => '30D', 90 => '3M', 180 => '6M', 365 => '1A'] as $diasBotao => $rotuloBotao): ?>
                        <button type="submit" name="dias" value="<?= $diasBotao ?>" class="botao secundario"><?= $rotuloBotao ?></button>
                    <?php endforeach; ?>
                </form>
                <p>Comparação de todas as usinas e painéis individuais das primeiras quatro. Valores por <?= $porMes ? 'mês' : 'dia' ?>.</p>
            </section>

            <?php if ($todasUsinas): ?>
                <section class="painel">
                    <h2>Produção de todas as usinas</h2>
                    <p>Uma linha por usina · kWh · Período de <?= formatarData($inicio) ?> a <?= formatarData($fim) ?>. Clique na legenda para ocultar ou exibir uma usina.</p>
                    <div class="grafico grafico-todas-usinas">
                        <canvas id="producao-todas-usinas" role="img" aria-label="Comparação da produção de todas as usinas em linhas, em kWh"></canvas>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (empty($usinas)): ?>
                <section class="painel">
                    <p>Nenhuma usina cadastrada. Cadastre uma usina para começar.</p>
                    <?php if (podeEditar()): ?>
                        <a class="botao" href="<?= escapar(endereco('views/usinas/usinas.php')) ?>">Incluir Usina</a>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <div class="grade-usinas">
                <?php foreach ($graficos as $grafico): ?>
                    <section class="painel">
                        <h2><?= escapar($grafico['nome']) ?></h2>
                        <p><?= escapar($grafico['tipo']) ?></p>

                        <h3>Produção de energia</h3>
                        <div class="grafico">
                            <canvas id="energia-<?= $grafico['id'] ?>" role="img" aria-label="Produção de energia"></canvas>
                        </div>

                        <h3>Horas extras e diárias (R$)</h3>
                        <p>Linha: HORAS EXTRAS (eixo direito). Barras: DIARIAS (eixo esquerdo). Valores em reais.</p>
                        <div class="grafico">
                            <canvas id="comparativo-<?= $grafico['id'] ?>" role="img" aria-label="Horas extras em linha e diárias em barras, com eixos independentes em reais"></canvas>
                        </div>
                        <h3>Despesas totais (R$)</h3>
                        <p>Todas as categorias da usina no período selecionado.</p>
                        <div class="grafico">
                            <canvas id="despesas-<?= $grafico['id'] ?>" role="img" aria-label="Despesas totais de todas as categorias"></canvas>
                        </div>

                        <details>
                            <summary>Consultar os valores dos gráficos</summary>
                            <div class="tabela-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Período</th>
                                            <th>Energia (kWh)</th>
                                            <th>Horas extras (R$)</th>
                                            <th>Diárias (R$)</th>
                                            <th>Despesas (R$)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rotulos as $posicao => $rotulo): ?>
                                            <tr>
                                                <td><?= escapar($rotulo) ?></td>
                                                <td><?= formatarNumero($grafico['energia'][$posicao]) ?></td>
                                                <td><?= formatarNumero($grafico['horas'][$posicao]) ?></td>
                                                <td><?= formatarNumero($grafico['diarias'][$posicao]) ?></td>
                                                <td><?= formatarNumero($grafico['despesas'][$posicao]) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    </section>
                <?php endforeach; ?>
            </div>

            <div id="erro-graficos" class="aviso erro" hidden>
                Os gráficos não carregaram. Consulte os valores nas tabelas acima.
            </div>

            <noscript>
                <p>Ative o JavaScript para ver os gráficos. As tabelas continuam disponíveis.</p>
            </noscript>

            <script type="application/json" id="dados-todas-usinas">
                <?= json_encode($comparacaoUsinas, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            </script>
            <script type="application/json" id="dados-graficos">
                <?= json_encode($graficos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            </script>
<?php require __DIR__ . '/footer.php'; ?>
```

## public_html/views/dashdark.php

```php
<?php

require_once __DIR__ . '/../biblioteca.php';

exigirLogin();

$pdo = conectarBanco();
$aviso = '';
$inicio = lerCampo($_GET, 'inicio', '2026-01-01');
$fim = lerCampo($_GET, 'fim', date('Y-m-d'));

if (!dataValida($inicio) || !dataValida($fim) || $inicio > $fim) {
    $aviso = 'Período inválido. Exibindo o período padrão a partir de 01/01/2026.';
    $inicio = '2026-01-01';
    $fim = date('Y-m-d');
}

// Carrega no máximo 365 dias até a data final. Os botões de período navegam nesse intervalo.
$limiteInicial = (new DateTimeImmutable($fim))->modify('-364 days')->format('Y-m-d');
$limiteInicial = max('1000-01-01', $limiteInicial);

if ($inicio < $limiteInicial) {
    $inicio = $limiteInicial;
    $aviso = 'O intervalo foi ajustado ao limite de um ano (365 dias).';
}

$consulta = $pdo->query('SELECT id, nome, tipo, capacidade_kw, status FROM usinas ORDER BY id');
$usinas = $consulta->fetchAll();
$idRecebido = lerCampo($_GET, 'usina_id', (string) ($usinas[0]['id'] ?? '0'));
$usinaSelecionada = null;

foreach ($usinas as $usina) {
    if ((string) $usina['id'] === $idRecebido) {
        $usinaSelecionada = $usina;
        break;
    }
}

if ($usinas && !$usinaSelecionada) {
    http_response_code(400);
    $aviso = 'Selecione uma usina válida para consultar os gráficos.';
}

$energia = [];
$horas = [];
$diarias = [];
$despesas = [];
$registrosProducao = [];

if ($usinaSelecionada) {
    $parametros = [$usinaSelecionada['id'], $limiteInicial, $fim];

    // Cada consulta soma apenas uma tabela para evitar duplicação de valores.
    $consulta = $pdo->prepare(
        'SELECT data_producao, SUM(kwh_gerado) AS total
         FROM producao_energia
         WHERE usina_id = ? AND data_producao BETWEEN ? AND ?
         GROUP BY data_producao ORDER BY data_producao'
    );
    $consulta->execute($parametros);
    $energia = $consulta->fetchAll(PDO::FETCH_KEY_PAIR);

    $consulta = $pdo->prepare(
        'SELECT d.data, SUM(d.valor_despesa) AS total
         FROM despesas d
         INNER JOIN categoria c ON c.id_categoria = d.categoria
         WHERE d.usina_id = ? AND d.data BETWEEN ? AND ?
           AND UPPER(TRIM(c.descricao_categoria)) = ?
         GROUP BY d.data ORDER BY d.data'
    );
    $consulta->execute([$usinaSelecionada['id'], $limiteInicial, $fim, 'HORAS EXTRAS']);
    $horas = $consulta->fetchAll(PDO::FETCH_KEY_PAIR);
    $consulta = $pdo->prepare(
        'SELECT d.data, SUM(d.valor_despesa) AS total
         FROM despesas d
         INNER JOIN categoria c ON c.id_categoria = d.categoria
         WHERE d.usina_id = ? AND d.data BETWEEN ? AND ?
           AND UPPER(TRIM(c.descricao_categoria)) = ?
         GROUP BY d.data ORDER BY d.data'
    );
    $consulta->execute([$usinaSelecionada['id'], $limiteInicial, $fim, 'DIARIAS']);
    $diarias = $consulta->fetchAll(PDO::FETCH_KEY_PAIR);
    $consulta = $pdo->prepare(
        'SELECT data, SUM(valor_despesa) AS total
         FROM despesas
         WHERE usina_id = ? AND data BETWEEN ? AND ?
         GROUP BY data ORDER BY data'
    );
    $consulta->execute($parametros);
    $despesas = $consulta->fetchAll(PDO::FETCH_KEY_PAIR);

    $consulta = $pdo->prepare(
        'SELECT id, data_producao, kwh_gerado
         FROM producao_energia
         WHERE usina_id = ? AND data_producao BETWEEN ? AND ?
         ORDER BY data_producao DESC, id DESC'
    );
    $consulta->execute($parametros);
    $registrosProducao = $consulta->fetchAll();
}

// A série diária mantém os zeros. O JavaScript agrupa por mês ao ampliar o período.
$serie = [];
$totais = ['energia' => 0, 'horas' => 0, 'despesas' => 0, 'diarias' => 0];
$cursor = new DateTimeImmutable($limiteInicial);
$dataFinal = new DateTimeImmutable($fim);

while ($cursor <= $dataFinal) {
    $data = $cursor->format('Y-m-d');
    $dia = [
        'data' => $data,
        'energia' => (float) ($energia[$data] ?? 0),
        'diarias' => (float) ($diarias[$data] ?? 0),
        'horas' => (float) ($horas[$data] ?? 0),
        'despesas' => (float) ($despesas[$data] ?? 0)
    ];
    $serie[] = $dia;

    if ($data >= $inicio) {
        foreach ($totais as $chave => $total) {
            $totais[$chave] += $dia[$chave];
        }
    }

    $cursor = $cursor->modify('+1 day');
}

// Uma consulta traz a produção de todas as usinas, independentemente da seleção do topo.
$consulta = $pdo->prepare(
    'SELECT usina_id, data_producao, SUM(kwh_gerado) AS total
     FROM producao_energia
     WHERE data_producao BETWEEN ? AND ?
     GROUP BY usina_id, data_producao'
);
$consulta->execute([$limiteInicial, $fim]);
$producaoTodas = [];

foreach ($usinas as $usina) {
    $producaoTodas[$usina['id']] = ['nome' => $usina['nome'], 'valores' => []];
}

foreach ($consulta as $registro) {
    $producaoTodas[$registro['usina_id']]['valores'][$registro['data_producao']] = (float) $registro['total'];
}

$dados = [
    'inicio' => $inicio,
    'fim' => $fim,
    'limiteInicial' => $limiteInicial,
    'usinaId' => $usinaSelecionada ? (int) $usinaSelecionada['id'] : null,
    'serie' => $serie,
    'producao' => $registrosProducao,
    'todasUsinas' => array_values($producaoTodas)
];

$diasVisiveis = (new DateTimeImmutable($inicio))->diff($dataFinal)->days + 1;
$links = [
    ['DashDark', 'views/dashdark.php', '◈'],
    ['Dashboard', 'views/dashboard.php', '▦'],
    ['Usinas', 'views/usinas/index_usinas.php', '☀'],
    ['Técnicos', 'views/tecnicos/index_tecnicos.php', '♙'],
    ['Produção de Energia', 'views/producao/index_producao.php', 'ϟ'],
    ['Despesas', 'views/despesas/index_despesas.php', '▤'],
    ['Categorias', 'views/categoria/index_categoria.php', '▣']
];

if (podeEditar() && podeExcluir()) {
    $links[] = ['Usuários', 'views/usuarios/index_usuarios.php', '♙'];
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="theme-color" content="#10121b">
        <title>DashDark - GERENE</title>
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
        <script defer src="<?= escapar(endereco('assets/js/dashdark.js')) ?>"></script>
    </head>
    <body class="min-h-screen bg-[#10121b] font-sans text-sm text-slate-200 antialiased [color-scheme:dark]">
        <!-- Layout independente: o dashboard clássico mantém os estilos anteriores. -->
        <aside id="menu-dark" class="fixed inset-y-0 left-0 z-40 hidden w-60 flex-col border-r border-white/5 bg-[#151722] px-4 py-7 lg:flex">
            <button id="fechar-menu-dark" type="button" class="mb-5 self-end rounded-lg border border-white/10 px-3 py-2 text-xs lg:hidden">Fechar menu</button>
            <a href="<?= escapar(endereco('views/dashdark.php')) ?>" class="mb-10 flex items-center gap-3 px-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-500 text-xl font-bold text-white">ϟ</span>
                <span class="text-xl font-bold tracking-widest text-white">GERENE</span>
            </a>
            <p class="mb-3 px-3 text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">Workspace / energia</p>
            <nav aria-label="Menu principal" class="space-y-1 overflow-y-auto">
                <?php foreach ($links as $link): ?>
                    <a
                        href="<?= escapar(endereco($link[1])) ?>"
                        class="flex items-center gap-3 rounded-lg px-3 py-3 text-xs font-medium transition <?= $link[0] === 'DashDark' ? 'bg-violet-500/15 text-violet-300' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>"
                        <?= $link[0] === 'DashDark' ? 'aria-current="page"' : '' ?>
                    >
                        <span class="w-5 text-center text-lg" aria-hidden="true"><?= $link[2] ?></span>
                        <?= escapar($link[0]) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="mt-auto border-t border-white/5 px-3 pt-6 text-xs text-slate-500">
                <p class="mb-2 flex items-center gap-2 text-slate-300"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>Gestão de usinas</p>
                Fotovoltaicas & hidrelétricas
            </div>
        </aside>

        <div class="lg:ml-60">
            <header class="flex min-h-20 items-center justify-between gap-4 border-b border-white/5 px-5 lg:px-9">
                <div class="flex items-center gap-3">
                    <button id="botao-menu-dark" type="button" class="rounded-lg border border-white/10 px-3 py-2 lg:hidden" aria-controls="menu-dark" aria-expanded="false">Menu</button>
                    <p class="text-xs text-slate-500">Painéis <span class="mx-2">/</span> <span class="text-slate-200">DashDark</span></p>
                </div>
                <div class="flex items-center gap-4">
                    <span class="hidden text-xs text-slate-400 sm:block"><?= escapar($_SESSION['nome']) ?></span>
                    <form method="post" action="<?= escapar(endereco('logout.php')) ?>">
                        <input type="hidden" name="token" value="<?= escapar(obterToken()) ?>">
                        <button type="submit" class="rounded-lg border border-white/10 px-3 py-2 text-xs text-slate-300 hover:bg-white/5">Sair ↗</button>
                    </form>
                </div>
            </header>

            <main class="mx-auto max-w-[1700px] px-5 py-8 lg:px-9">
                <div class="mb-7 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="mb-2 text-[10px] font-semibold uppercase tracking-[0.2em] text-violet-400">Inteligência da operação</p>
                        <h1 class="text-2xl font-semibold tracking-tight text-white sm:text-3xl">Visão geral da usina</h1>
                        <p class="mt-2 text-xs text-slate-400">Produção, equipe e despesas no mesmo período.</p>
                    </div>
                    <span class="rounded-full border border-violet-400/20 bg-violet-400/5 px-4 py-2 text-xs text-violet-300">◈ DashDark</span>
                </div>

                <?php if ($aviso !== ''): ?>
                    <p class="mb-5 rounded-xl border border-amber-400/20 bg-amber-400/10 p-4 text-amber-200" role="alert"><?= escapar($aviso) ?></p>
                <?php endif; ?>

                <form id="filtro-dark" method="get" class="mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-white/5 bg-[#191c29] p-5">
                    <div>
                        <label for="inicio" class="mb-2 block text-xs text-slate-400">Data inicial</label>
                        <input id="inicio" name="inicio" type="date" value="<?= escapar($inicio) ?>" min="1000-01-01" max="9999-12-31" required class="rounded-lg border border-white/10 bg-[#121520] px-3 py-2.5 text-xs outline-none focus:border-violet-400">
                    </div>
                    <div>
                        <label for="fim" class="mb-2 block text-xs text-slate-400">Data final</label>
                        <input id="fim" name="fim" type="date" value="<?= escapar($fim) ?>" min="1000-01-01" max="9999-12-31" required class="rounded-lg border border-white/10 bg-[#121520] px-3 py-2.5 text-xs outline-none focus:border-violet-400">
                    </div>
                    <div class="min-w-48 flex-1">
                        <label for="usina_id" class="mb-2 block text-xs text-slate-400">Usina</label>
                        <select id="usina_id" name="usina_id" required class="w-full rounded-lg border border-white/10 bg-[#121520] px-3 py-2.5 text-xs outline-none focus:border-violet-400">
                            <?php if (!$usinaSelecionada): ?>
                                <option value="">Selecione uma usina</option>
                            <?php endif; ?>
                            <?php foreach ($usinas as $usina): ?>
                                <option value="<?= (int) $usina['id'] ?>" <?= (string) $usina['id'] === $idRecebido ? 'selected' : '' ?>><?= escapar($usina['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="rounded-lg bg-violet-500 px-5 py-2.5 text-xs font-semibold text-white hover:bg-violet-400">Aplicar período</button>
                </form>

                <?php if (!$usinaSelecionada): ?>
                    <section class="rounded-xl border border-dashed border-white/15 p-12 text-center">
                        <h2 class="text-lg text-white"><?= $usinas ? 'Escolha a usina para analisar' : 'Sua operação começa aqui' ?></h2>
                        <p class="mt-3 text-slate-400"><?= $usinas ? 'Use a seleção acima e aplique o período.' : 'Cadastre uma usina e seus lançamentos para visualizar os gráficos.' ?></p>
                        <?php if (!$usinas && podeEditar()): ?>
                            <a href="<?= escapar(endereco('views/usinas/usinas.php')) ?>" class="mt-6 inline-block rounded-lg bg-violet-500 px-5 py-3 text-white">Incluir Usina</a>
                        <?php endif; ?>
                    </section>
                <?php else: ?>
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <h2 class="font-medium text-white"><?= escapar($usinaSelecionada['nome']) ?> <span class="ml-2 text-xs font-normal text-slate-500"><?= escapar($usinaSelecionada['tipo']) ?></span></h2>
                        <p id="periodo-visivel" class="text-xs text-slate-400" aria-live="polite"><?= formatarData($inicio) ?> — <?= formatarData($fim) ?> · <?= $diasVisiveis ?> dias</p>
                    </div>

                    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <section class="rounded-xl border border-white/5 bg-[#191c29] p-5">
                            <p class="text-xs text-slate-400">Produção de energia <span class="float-right text-violet-400">ϟ</span></p>
                            <p class="mt-4 text-2xl font-semibold text-white"><span id="total-energia"><?= formatarNumero($totais['energia']) ?></span> <span class="text-xs font-normal text-slate-500">kWh</span></p>
                            <p class="mt-2 text-[11px] text-slate-500">Energia gerada no período</p>
                        </section>
                        <section class="rounded-xl border border-white/5 bg-[#191c29] p-5">
                            <p class="text-xs text-slate-400">Horas extras <span class="float-right text-cyan-300">◷</span></p>
                            <p class="mt-4 text-2xl font-semibold text-white"><span id="total-horas"><?= formatarNumero($totais['horas']) ?></span> <span class="text-xs font-normal text-slate-500">R$</span></p>
                            <p class="mt-2 text-[11px] text-slate-500">Despesas da categoria HORAS EXTRAS</p>
                        </section>
                        <section class="rounded-xl border border-white/5 bg-[#191c29] p-5">
                            <p class="text-xs text-slate-400">Despesas <span class="float-right text-amber-300">▤</span></p>
                            <p class="mt-4 text-2xl font-semibold text-white"><span class="text-xs font-normal text-slate-500">R$</span> <span id="total-despesas"><?= formatarNumero($totais['despesas']) ?></span></p>
                            <p class="mt-2 text-[11px] text-slate-500">Despesas no período</p>
                        </section>
                        <section class="rounded-xl border border-white/5 bg-[#191c29] p-5">
                            <p class="text-xs text-slate-400">Diárias <span class="float-right text-emerald-300">▤</span></p>
                            <p class="mt-4 text-2xl font-semibold text-white"><span class="text-xs font-normal text-slate-500">R$</span> <span id="total-diarias"><?= formatarNumero($totais['diarias']) ?></span></p>
                            <p class="mt-2 text-[11px] text-slate-500">Despesas da categoria DIARIAS</p>
                        </section>
                    </div>

                    <p id="falha-graficos" hidden class="mb-5 rounded-xl bg-amber-400/10 p-4 text-amber-200" role="alert">Não foi possível carregar os gráficos. A tabela de produção e os indicadores continuam disponíveis.</p>

                    <section class="mb-6 rounded-xl border border-white/5 bg-[#191c29] p-5 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="font-semibold text-white">Produção da usina</h2>
                                <p class="mt-1 text-xs text-slate-500">Energia gerada · kWh</p>
                            </div>
                            <div class="flex flex-wrap gap-1 rounded-lg bg-[#121520] p-1" aria-label="Selecionar duração do período">
                                <?php foreach ([7 => '7D', 30 => '30D', 90 => '3M', 180 => '6M', 365 => '1A'] as $duracao => $rotulo): ?>
                                    <button type="button" data-dias="<?= $duracao ?>" aria-pressed="false" class="rounded-md px-3 py-1.5 text-[11px] text-slate-400 hover:bg-white/5 aria-pressed:bg-violet-500/20 aria-pressed:text-violet-300"><?= $rotulo ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div id="area-producao" tabindex="0" aria-label="Gráfico de produção. Use os botões de período ou as teclas mais e menos." class="relative mt-6 h-80 rounded-lg focus-visible:outline-2 focus-visible:outline-violet-400 sm:h-96">
                            <canvas id="grafico-energia" role="img" aria-label="Produção de energia da usina em kWh"></canvas>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-white/5 pt-4 text-[11px] text-slate-500">
                            <p>Selecione 7D, 30D, 3M, 6M ou 1A para ajustar o período dos gráficos.</p>
                            <span id="agrupamento"><?= $diasVisiveis > 62 ? 'Valores por mês' : 'Valores por dia' ?></span>
                        </div>
                    </section>

                    <section class="mb-6 rounded-xl border border-white/5 bg-[#191c29] p-5 sm:p-6">
                        <h2 class="font-semibold text-white">Produção de todas as usinas</h2>
                        <p class="mt-2 text-xs text-slate-400">Uma linha por usina · kWh · Mesmo período selecionado acima. Clique na legenda para ocultar ou exibir uma usina.</p>
                        <div class="relative mt-5 h-80 sm:h-96">
                            <canvas id="grafico-usinas" role="img" aria-label="Comparação da produção de energia de todas as usinas em linhas, em kWh"></canvas>
                        </div>
                    </section>

                    <section class="mb-6 rounded-xl border border-white/5 bg-[#191c29] p-5 sm:p-6">
                        <h2 class="font-semibold text-white">Horas extras e diárias</h2>
                        <p class="mt-2 text-xs text-slate-400">Linha: HORAS EXTRAS (eixo direito). Barras: DIARIAS (eixo esquerdo). Escalas independentes em reais.</p>
                        <div class="relative mt-5 h-80">
                            <canvas id="grafico-comparativo" role="img" aria-label="Horas extras em linha e diárias em barras, com eixos independentes em reais"></canvas>
                        </div>
                    </section>
                    <section class="mb-6 rounded-xl border border-white/5 bg-[#191c29] p-5 sm:p-6">
                        <h2 class="font-semibold text-white">Despesas totais</h2>
                        <p class="mt-2 text-xs text-slate-400">Todas as categorias, na mesma usina e no mesmo período dos demais gráficos.</p>
                        <div class="relative mt-5 h-80">
                            <canvas id="grafico-despesas" role="img" aria-label="Despesas totais em reais"></canvas>
                        </div>
                    </section>

                    <details class="rounded-xl border border-white/5 bg-[#191c29] p-5 sm:p-6">
                        <summary class="cursor-pointer text-sm font-medium text-violet-300">Consultar valores da tabela de produção de energia</summary>
                        <p class="mt-3 text-xs text-slate-500">Lançamentos da usina e do período exibidos nos gráficos.</p>
                        <div class="mt-5 max-h-[400px] overflow-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="sticky top-0 bg-[#222636] text-slate-400">
                                    <tr><th class="px-4 py-3">Registro</th><th class="px-4 py-3">Data</th><th class="px-4 py-3 text-right">Energia gerada (kWh)</th></tr>
                                </thead>
                                <tbody id="tabela-producao">
                                    <?php $quantidade = 0; ?>
                                    <?php foreach ($registrosProducao as $registro): ?>
                                        <?php if ($registro['data_producao'] >= $inicio): ?>
                                            <?php $quantidade++; ?>
                                            <tr class="border-b border-white/5">
                                                <td class="px-4 py-3 text-slate-500">#<?= (int) $registro['id'] ?></td>
                                                <td class="px-4 py-3"><?= formatarData($registro['data_producao']) ?></td>
                                                <td class="px-4 py-3 text-right"><?= formatarNumero($registro['kwh_gerado']) ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <?php if ($quantidade === 0): ?>
                                        <tr><td colspan="3" class="px-4 py-8 text-center text-slate-500">Nenhum lançamento de produção neste período.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>
                    <noscript><p class="mt-5 text-amber-200">Ative o JavaScript para os gráficos e os botões de período. Os filtros e a tabela funcionam sem JavaScript.</p></noscript>
                <?php endif; ?>

                <footer class="mt-8 flex justify-between text-[10px] text-slate-600"><span>GERENE · Gestão de energia</span><span>DashDark</span></footer>
            </main>
        </div>

        <script id="dados-dark" type="application/json"><?= json_encode($dados, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    </body>
</html>
```

## public_html/views/despesas/despesas.php

```php
<?php

require_once __DIR__ . '/../../biblioteca.php';

exigirLogin();
exigirEdicao();

$pdo = conectarBanco();
$id = obterId();
$erros = [];

// Valores iniciais para um novo cadastro.
$usina_id = '';
$data = date('Y-m-d');
$valor_despesa = '';
$descricao = '';
$categoria = '';
$tecnico = '';

// Carrega os dados quando o usuário escolhe Editar.
if ($id > 0) {
    $consulta = $pdo->prepare('SELECT * FROM despesas WHERE id = ?');
    $consulta->execute([$id]);
    $registro = $consulta->fetch();

    if (!$registro) {
        http_response_code(404);
        exit('Registro não encontrado.');
    }

    $usina_id = $registro['usina_id'];
    $data = $registro['data'];
    $valor_despesa = $registro['valor_despesa'];
    $descricao = $registro['descricao'];
    $categoria = $registro['categoria'];
    $tecnico = $registro['tecnico'] ?? '';
}

// Recebe e valida os campos enviados pelo formulário.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarToken();

    $usina_id = lerCampo($_POST, 'usina_id');
    $data = lerCampo($_POST, 'data');
    $valor_despesa = lerCampo($_POST, 'valor_despesa');
    $descricao = lerCampo($_POST, 'descricao');
    $categoria = lerCampo($_POST, 'categoria');
    $tecnico = lerCampo($_POST, 'tecnico');

    if (!vinculoExiste($pdo, 'usinas', $usina_id)) {
        $erros[] = 'Usina: selecione um cadastro existente.';
    }

    if (!dataValida($data)) {
        $erros[] = 'Data: informe uma data válida.';
    }

    if (!numeroValido($valor_despesa)) {
        $erros[] = 'Valor (R$): informe um número positivo ou zero, com até duas casas decimais.';
    }

    if ($descricao === '' || iconv_strlen($descricao, 'UTF-8') > 2000) {
        $erros[] = 'Descrição: preencha até 2000 caracteres.';
    }

    if (!vinculoExiste($pdo, 'categoria', $categoria)) {
        $erros[] = 'Categoria: selecione um cadastro existente.';
    }

    if ($tecnico !== '' && !vinculoExiste($pdo, 'tecnicos', $tecnico)) {
        $erros[] = 'Técnico: selecione um cadastro existente ou deixe sem técnico.';
    }

    // Só grava no banco se não houver erros de preenchimento.
    if (empty($erros)) {
        try {
            if ($id > 0) {
                $consulta = $pdo->prepare(
                    'UPDATE despesas
                     SET usina_id = ?, data = ?, valor_despesa = ?, descricao = ?, categoria = ?, tecnico = ?
                     WHERE id = ?'
                );
                $consulta->execute([$usina_id, $data, $valor_despesa, $descricao, $categoria, $tecnico === '' ? null : $tecnico, $id]);
            } else {
                $consulta = $pdo->prepare(
                    'INSERT INTO despesas (usina_id, data, valor_despesa, descricao, categoria, tecnico)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $consulta->execute([$usina_id, $data, $valor_despesa, $descricao, $categoria, $tecnico === '' ? null : $tecnico]);
            }

            redirecionar('views/despesas/index_despesas.php?msg=sucesso');
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

$consulta = $pdo->query('SELECT id, nome FROM usinas ORDER BY nome');
$usinas = $consulta->fetchAll();

$consulta = $pdo->query('SELECT id_categoria, descricao_categoria, Centro_de_custo FROM categoria ORDER BY descricao_categoria');
$categorias = $consulta->fetchAll();

$consulta = $pdo->query('SELECT id, nome FROM tecnicos ORDER BY nome');
$tecnicos = $consulta->fetchAll();

$titulo = 'Despesas - ' . ($id > 0 ? 'Editar' : 'Incluir');
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
                            <label for="usina_id">Usina</label>
                            <select id="usina_id" name="usina_id" required>
                                <option value="">Selecione</option>
                                <?php foreach ($usinas as $opcao): ?>
                                    <option value="<?= escapar($opcao['id']) ?>" <?= (string) $usina_id === (string) $opcao['id'] ? 'selected' : '' ?>>
                                        <?= escapar($opcao['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($usinas)): ?>
                                <small>Cadastre uma usina antes de incluir este lançamento.</small>
                            <?php endif; ?>
                        </div>

                        <div class="campo">
                            <label for="data">Data</label>
                            <input type="date" id="data" name="data"
                                   value="<?= escapar($data) ?>" min="1000-01-01" max="9999-12-31" required>
                        </div>

                        <div class="campo">
                            <label for="valor_despesa">Valor (R$)</label>
                            <input type="number" id="valor_despesa" name="valor_despesa"
                                   value="<?= escapar($valor_despesa) ?>" min="0" max="9999999999.99" step="0.01" required>
                        </div>

                        <div class="campo">
                            <label for="descricao">Descrição</label>
                            <textarea id="descricao" name="descricao" rows="3" maxlength="2000" required><?= escapar($descricao) ?></textarea>
                        </div>

                        <div class="campo">
                            <label for="categoria">Categoria</label>
                            <select id="categoria" name="categoria" required>
                                <option value="">Selecione</option>
                                <?php foreach ($categorias as $opcao): ?>
                                    <option value="<?= (int) $opcao['id_categoria'] ?>" <?= (string) $categoria === (string) $opcao['id_categoria'] ? 'selected' : '' ?>>
                                        <?= escapar($opcao['descricao_categoria']) ?>
                                        <?= $opcao['Centro_de_custo'] !== null ? ' — CC ' . escapar($opcao['Centro_de_custo']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($categorias)): ?>
                                <small>Cadastre uma categoria antes de lançar uma despesa.</small>
                            <?php endif; ?>
                            <a href="<?= escapar(endereco('views/categoria/categoria.php')) ?>">Cadastrar categoria</a>
                        </div>

                        <div class="campo">
                            <label for="tecnico">Técnico</label>
                            <select id="tecnico" name="tecnico">
                                <option value="">Sem técnico vinculado</option>
                                <?php foreach ($tecnicos as $opcao): ?>
                                    <option value="<?= (int) $opcao['id'] ?>" <?= (string) $tecnico === (string) $opcao['id'] ? 'selected' : '' ?>>
                                        <?= escapar($opcao['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>

                    <div class="acoes">
                        <button type="submit" class="botao">Gravar</button>
                        <a class="botao secundario" href="index_despesas.php">Voltar para pesquisa</a>
                    </div>
                </form>
            </section>
<?php require __DIR__ . '/../footer.php'; ?>
```

## public_html/views/despesas/index_despesas.php

```php
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
```

## public_html/views/diarias/diarias.php

```php
<?php

require_once __DIR__ . '/../../biblioteca.php';

exigirLogin();
exigirEdicao();

$pdo = conectarBanco();
$id = obterId();
$erros = [];

// Valores iniciais para um novo cadastro.
$usina_id = '';
$tecnico_id = '';
$data = date('Y-m-d');
$valor_diaria = '';
$descricao = '';

// Carrega os dados quando o usuário escolhe Editar.
if ($id > 0) {
    $consulta = $pdo->prepare('SELECT * FROM diarias WHERE id = ?');
    $consulta->execute([$id]);
    $registro = $consulta->fetch();

    if (!$registro) {
        http_response_code(404);
        exit('Registro não encontrado.');
    }

    $usina_id = $registro['usina_id'];
    $tecnico_id = $registro['tecnico_id'];
    $data = $registro['data'];
    $valor_diaria = $registro['valor_diaria'];
    $descricao = $registro['descricao'];
}

// Recebe e valida os campos enviados pelo formulário.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarToken();

    $usina_id = lerCampo($_POST, 'usina_id');
    $tecnico_id = lerCampo($_POST, 'tecnico_id');
    $data = lerCampo($_POST, 'data');
    $valor_diaria = lerCampo($_POST, 'valor_diaria');
    $descricao = lerCampo($_POST, 'descricao');

    if (!vinculoExiste($pdo, 'usinas', $usina_id)) {
        $erros[] = 'Usina: selecione um cadastro existente.';
    }

    if (!vinculoExiste($pdo, 'tecnicos', $tecnico_id)) {
        $erros[] = 'Técnico: selecione um cadastro existente.';
    }

    if (!dataValida($data)) {
        $erros[] = 'Data: informe uma data válida.';
    }

    if (!numeroValido($valor_diaria)) {
        $erros[] = 'Valor da diária (R$): informe um número positivo ou zero, com até duas casas decimais.';
    }

    if ($descricao === '' || iconv_strlen($descricao, 'UTF-8') > 2000) {
        $erros[] = 'Descrição: preencha até 2000 caracteres.';
    }

    // Só grava no banco se não houver erros de preenchimento.
    if (empty($erros)) {
        try {
            if ($id > 0) {
                $consulta = $pdo->prepare(
                    'UPDATE diarias
                     SET usina_id = ?, tecnico_id = ?, data = ?, valor_diaria = ?, descricao = ?
                     WHERE id = ?'
                );
                $consulta->execute([$usina_id, $tecnico_id, $data, $valor_diaria, $descricao, $id]);
            } else {
                $consulta = $pdo->prepare(
                    'INSERT INTO diarias (usina_id, tecnico_id, data, valor_diaria, descricao)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $consulta->execute([$usina_id, $tecnico_id, $data, $valor_diaria, $descricao]);
            }

            redirecionar('views/diarias/index_diarias.php?msg=sucesso');
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

$consulta = $pdo->query('SELECT id, nome FROM usinas ORDER BY nome');
$usinas = $consulta->fetchAll();

$consulta = $pdo->query('SELECT id, nome FROM tecnicos ORDER BY nome');
$tecnicos = $consulta->fetchAll();

$titulo = 'Diárias - ' . ($id > 0 ? 'Editar' : 'Incluir');
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
                            <label for="usina_id">Usina</label>
                            <select id="usina_id" name="usina_id" required>
                                <option value="">Selecione</option>
                                <?php foreach ($usinas as $opcao): ?>
                                    <option value="<?= escapar($opcao['id']) ?>" <?= (string) $usina_id === (string) $opcao['id'] ? 'selected' : '' ?>>
                                        <?= escapar($opcao['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($usinas)): ?>
                                <small>Cadastre uma usina antes de incluir este lançamento.</small>
                            <?php endif; ?>
                        </div>

                        <div class="campo">
                            <label for="tecnico_id">Técnico</label>
                            <select id="tecnico_id" name="tecnico_id" required>
                                <option value="">Selecione</option>
                                <?php foreach ($tecnicos as $opcao): ?>
                                    <option value="<?= escapar($opcao['id']) ?>" <?= (string) $tecnico_id === (string) $opcao['id'] ? 'selected' : '' ?>>
                                        <?= escapar($opcao['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($tecnicos)): ?>
                                <small>Cadastre um técnico antes de incluir este lançamento.</small>
                            <?php endif; ?>
                        </div>

                        <div class="campo">
                            <label for="data">Data</label>
                            <input type="date" id="data" name="data"
                                   value="<?= escapar($data) ?>" min="1000-01-01" max="9999-12-31" required>
                        </div>

                        <div class="campo">
                            <label for="valor_diaria">Valor da diária (R$)</label>
                            <input type="number" id="valor_diaria" name="valor_diaria"
                                   value="<?= escapar($valor_diaria) ?>" min="0" max="9999999999.99" step="0.01" required>
                        </div>

                        <div class="campo">
                            <label for="descricao">Descrição</label>
                            <textarea id="descricao" name="descricao" rows="3" maxlength="2000" required><?= escapar($descricao) ?></textarea>
                        </div>

                    </div>

                    <div class="acoes">
                        <button type="submit" class="botao">Gravar</button>
                        <a class="botao secundario" href="index_diarias.php">Voltar para pesquisa</a>
                    </div>
                </form>
            </section>
<?php require __DIR__ . '/../footer.php'; ?>
```

## public_html/views/diarias/index_diarias.php

```php
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
        $consulta = $pdo->prepare('DELETE FROM diarias WHERE id = ?');
        $consulta->execute([$id]);

        if ($consulta->rowCount() === 0) {
            throw new RuntimeException('Registro não encontrado.');
        }

        redirecionar('views/diarias/index_diarias.php?msg=sucesso');
    } catch (PDOException $excecao) {
        $_SESSION['mensagem_erro'] = mensagemBanco($excecao);
        redirecionar('views/diarias/index_diarias.php?error=1');
    } catch (RuntimeException $excecao) {
        $_SESSION['mensagem_erro'] = $excecao->getMessage();
        redirecionar('views/diarias/index_diarias.php?error=1');
    }
}

// Consulta dos registros que serão exibidos na tabela.
$consulta = $pdo->query(
    'SELECT r.*, u.nome AS nome_usina, t.nome AS nome_tecnico
     FROM diarias r
     INNER JOIN usinas u ON u.id = r.usina_id
     INNER JOIN tecnicos t ON t.id = r.tecnico_id
     ORDER BY r.id DESC'
);
$registros = $consulta->fetchAll();
$titulo = 'Diárias';

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
                                <th>Técnico</th>
                                <th>Data</th>
                                <th>Valor da diária (R$)</th>
                                <th>Descrição</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registros as $registro): ?>
                                <tr class="linha-registro">
                                    <td><?= escapar($registro['id']) ?></td>
                                    <td><?= escapar($registro['nome_usina']) ?></td>
                                    <td><?= escapar($registro['nome_tecnico']) ?></td>
                                    <td><?= escapar(formatarData($registro['data'])) ?></td>
                                    <td><?= escapar(formatarNumero($registro['valor_diaria'])) ?></td>
                                    <td><?= escapar($registro['descricao']) ?></td>
                                    <td>
                                        <div class="acoes">
                                            <?php if (podeEditar()): ?>
                                                <a class="botao pequeno" href="diarias.php?id=<?= escapar($registro['id']) ?>">Editar</a>
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
                                <td colspan="7">Nenhum registro encontrado.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <?php if (podeEditar()): ?>
                    <a class="botao incluir" href="diarias.php">Incluir Novo</a>
                <?php endif; ?>
            </section>
<?php require __DIR__ . '/../footer.php'; ?>
```

## public_html/views/footer.php

```php
        </main>
    </body>
</html>
```

## public_html/views/header.php

```php
<?php

if (!isset($titulo)) {
    http_response_code(403);
    exit('Acesse uma página do sistema.');
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= escapar($titulo) ?> - <?= escapar(NOME_EMPRESA) ?></title>
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
        <link rel="stylesheet" href="<?= escapar(endereco('assets/css/custom.css')) ?>">

        <?php if ($titulo === 'Dashboard'): ?>
            <script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
        <?php endif; ?>

        <script defer src="<?= escapar(endereco('assets/js/main.js')) ?>"></script>
    </head>
    <body>
        <header class="barra-superior">
            <div>
                <button type="button" id="alternar-menu" class="botao secundario" aria-controls="menu" aria-expanded="true">Menu</button>
                <strong><?= escapar(NOME_EMPRESA) ?></strong>
            </div>

            <div>
                <span><?= escapar($_SESSION['nome']) ?></span>
                <form method="post" action="<?= escapar(endereco('logout.php')) ?>">
                    <input type="hidden" name="token" value="<?= escapar(obterToken()) ?>">
                    <button type="submit" class="botao secundario">Sair</button>
                </form>
            </div>
        </header>

        <?php require __DIR__ . '/sidebar.php'; ?>

        <main class="conteudo">
            <h1><?= escapar($titulo) ?></h1>

            <?php if (lerCampo($_GET, 'msg') === 'sucesso'): ?>
                <div class="aviso sucesso" role="status">Operação realizada com sucesso.</div>
            <?php endif; ?>

            <?php if (lerCampo($_GET, 'error') === '1'): ?>
                <div class="aviso erro" role="alert">
                    <?= escapar($_SESSION['mensagem_erro'] ?? 'Não foi possível concluir a operação.') ?>
                </div>
                <?php unset($_SESSION['mensagem_erro']); ?>
            <?php endif; ?>
```

## public_html/views/horas_extras/horas_extras.php

```php
<?php

require_once __DIR__ . '/../../biblioteca.php';

exigirLogin();
exigirEdicao();

$pdo = conectarBanco();
$id = obterId();
$erros = [];

// Valores iniciais para um novo cadastro.
$usina_id = '';
$tecnico_id = '';
$data = date('Y-m-d');
$quantidade_horas = '';
$valor_total = '';

// Carrega os dados quando o usuário escolhe Editar.
if ($id > 0) {
    $consulta = $pdo->prepare('SELECT * FROM horas_extras WHERE id = ?');
    $consulta->execute([$id]);
    $registro = $consulta->fetch();

    if (!$registro) {
        http_response_code(404);
        exit('Registro não encontrado.');
    }

    $usina_id = $registro['usina_id'];
    $tecnico_id = $registro['tecnico_id'];
    $data = $registro['data'];
    $quantidade_horas = $registro['quantidade_horas'];
    $valor_total = $registro['valor_total'];
}

// Recebe e valida os campos enviados pelo formulário.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarToken();

    $usina_id = lerCampo($_POST, 'usina_id');
    $tecnico_id = lerCampo($_POST, 'tecnico_id');
    $data = lerCampo($_POST, 'data');
    $quantidade_horas = lerCampo($_POST, 'quantidade_horas');
    $valor_total = lerCampo($_POST, 'valor_total');

    if (!vinculoExiste($pdo, 'usinas', $usina_id)) {
        $erros[] = 'Usina: selecione um cadastro existente.';
    }

    if (!vinculoExiste($pdo, 'tecnicos', $tecnico_id)) {
        $erros[] = 'Técnico: selecione um cadastro existente.';
    }

    if (!dataValida($data)) {
        $erros[] = 'Data: informe uma data válida.';
    }

    if (!numeroValido($quantidade_horas)) {
        $erros[] = 'Quantidade de horas: informe um número positivo ou zero, com até duas casas decimais.';
    }

    if (!numeroValido($valor_total)) {
        $erros[] = 'Valor total (R$): informe um número positivo ou zero, com até duas casas decimais.';
    }

    // Só grava no banco se não houver erros de preenchimento.
    if (empty($erros)) {
        try {
            if ($id > 0) {
                $consulta = $pdo->prepare(
                    'UPDATE horas_extras
                     SET usina_id = ?, tecnico_id = ?, data = ?, quantidade_horas = ?, valor_total = ?
                     WHERE id = ?'
                );
                $consulta->execute([$usina_id, $tecnico_id, $data, $quantidade_horas, $valor_total, $id]);
            } else {
                $consulta = $pdo->prepare(
                    'INSERT INTO horas_extras (usina_id, tecnico_id, data, quantidade_horas, valor_total)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $consulta->execute([$usina_id, $tecnico_id, $data, $quantidade_horas, $valor_total]);
            }

            redirecionar('views/horas_extras/index_horas_extras.php?msg=sucesso');
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

$consulta = $pdo->query('SELECT id, nome FROM usinas ORDER BY nome');
$usinas = $consulta->fetchAll();

$consulta = $pdo->query('SELECT id, nome FROM tecnicos ORDER BY nome');
$tecnicos = $consulta->fetchAll();

$titulo = 'Horas Extras - ' . ($id > 0 ? 'Editar' : 'Incluir');
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
                            <label for="usina_id">Usina</label>
                            <select id="usina_id" name="usina_id" required>
                                <option value="">Selecione</option>
                                <?php foreach ($usinas as $opcao): ?>
                                    <option value="<?= escapar($opcao['id']) ?>" <?= (string) $usina_id === (string) $opcao['id'] ? 'selected' : '' ?>>
                                        <?= escapar($opcao['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($usinas)): ?>
                                <small>Cadastre uma usina antes de incluir este lançamento.</small>
                            <?php endif; ?>
                        </div>

                        <div class="campo">
                            <label for="tecnico_id">Técnico</label>
                            <select id="tecnico_id" name="tecnico_id" required>
                                <option value="">Selecione</option>
                                <?php foreach ($tecnicos as $opcao): ?>
                                    <option value="<?= escapar($opcao['id']) ?>" <?= (string) $tecnico_id === (string) $opcao['id'] ? 'selected' : '' ?>>
                                        <?= escapar($opcao['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($tecnicos)): ?>
                                <small>Cadastre um técnico antes de incluir este lançamento.</small>
                            <?php endif; ?>
                        </div>

                        <div class="campo">
                            <label for="data">Data</label>
                            <input type="date" id="data" name="data"
                                   value="<?= escapar($data) ?>" min="1000-01-01" max="9999-12-31" required>
                        </div>

                        <div class="campo">
                            <label for="quantidade_horas">Quantidade de horas</label>
                            <input type="number" id="quantidade_horas" name="quantidade_horas"
                                   value="<?= escapar($quantidade_horas) ?>" min="0" max="9999999999.99" step="0.01" required>
                        </div>

                        <div class="campo">
                            <label for="valor_total">Valor total (R$)</label>
                            <input type="number" id="valor_total" name="valor_total"
                                   value="<?= escapar($valor_total) ?>" min="0" max="9999999999.99" step="0.01" required>
                        </div>

                    </div>

                    <div class="acoes">
                        <button type="submit" class="botao">Gravar</button>
                        <a class="botao secundario" href="index_horas_extras.php">Voltar para pesquisa</a>
                    </div>
                </form>
            </section>
<?php require __DIR__ . '/../footer.php'; ?>
```

## public_html/views/horas_extras/index_horas_extras.php

```php
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
        $consulta = $pdo->prepare('DELETE FROM horas_extras WHERE id = ?');
        $consulta->execute([$id]);

        if ($consulta->rowCount() === 0) {
            throw new RuntimeException('Registro não encontrado.');
        }

        redirecionar('views/horas_extras/index_horas_extras.php?msg=sucesso');
    } catch (PDOException $excecao) {
        $_SESSION['mensagem_erro'] = mensagemBanco($excecao);
        redirecionar('views/horas_extras/index_horas_extras.php?error=1');
    } catch (RuntimeException $excecao) {
        $_SESSION['mensagem_erro'] = $excecao->getMessage();
        redirecionar('views/horas_extras/index_horas_extras.php?error=1');
    }
}

// Consulta dos registros que serão exibidos na tabela.
$consulta = $pdo->query(
    'SELECT r.*, u.nome AS nome_usina, t.nome AS nome_tecnico
     FROM horas_extras r
     INNER JOIN usinas u ON u.id = r.usina_id
     INNER JOIN tecnicos t ON t.id = r.tecnico_id
     ORDER BY r.id DESC'
);
$registros = $consulta->fetchAll();
$titulo = 'Horas Extras';

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
                                <th>Técnico</th>
                                <th>Data</th>
                                <th>Quantidade de horas</th>
                                <th>Valor total (R$)</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registros as $registro): ?>
                                <tr class="linha-registro">
                                    <td><?= escapar($registro['id']) ?></td>
                                    <td><?= escapar($registro['nome_usina']) ?></td>
                                    <td><?= escapar($registro['nome_tecnico']) ?></td>
                                    <td><?= escapar(formatarData($registro['data'])) ?></td>
                                    <td><?= escapar(formatarNumero($registro['quantidade_horas'])) ?></td>
                                    <td><?= escapar(formatarNumero($registro['valor_total'])) ?></td>
                                    <td>
                                        <div class="acoes">
                                            <?php if (podeEditar()): ?>
                                                <a class="botao pequeno" href="horas_extras.php?id=<?= escapar($registro['id']) ?>">Editar</a>
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
                                <td colspan="7">Nenhum registro encontrado.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <?php if (podeEditar()): ?>
                    <a class="botao incluir" href="horas_extras.php">Incluir Novo</a>
                <?php endif; ?>
            </section>
<?php require __DIR__ . '/../footer.php'; ?>
```

## public_html/views/producao/index_producao.php

```php
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
```

## public_html/views/producao/producao.php

```php
<?php

require_once __DIR__ . '/../../biblioteca.php';

exigirLogin();
exigirEdicao();

$pdo = conectarBanco();
$id = obterId();
$erros = [];

// Valores iniciais para um novo cadastro.
$usina_id = '';
$data_producao = date('Y-m-d');
$kwh_gerado = '';

// Carrega os dados quando o usuário escolhe Editar.
if ($id > 0) {
    $consulta = $pdo->prepare('SELECT * FROM producao_energia WHERE id = ?');
    $consulta->execute([$id]);
    $registro = $consulta->fetch();

    if (!$registro) {
        http_response_code(404);
        exit('Registro não encontrado.');
    }

    $usina_id = $registro['usina_id'];
    $data_producao = $registro['data_producao'];
    $kwh_gerado = $registro['kwh_gerado'];
}

// Recebe e valida os campos enviados pelo formulário.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarToken();

    $usina_id = lerCampo($_POST, 'usina_id');
    $data_producao = lerCampo($_POST, 'data_producao');
    $kwh_gerado = lerCampo($_POST, 'kwh_gerado');

    if (!vinculoExiste($pdo, 'usinas', $usina_id)) {
        $erros[] = 'Usina: selecione um cadastro existente.';
    }

    if (!dataValida($data_producao)) {
        $erros[] = 'Data de produção: informe uma data válida.';
    }

    if (!numeroValido($kwh_gerado)) {
        $erros[] = 'Energia gerada (kWh): informe um número positivo ou zero, com até duas casas decimais.';
    }

    // Só grava no banco se não houver erros de preenchimento.
    if (empty($erros)) {
        try {
            if ($id > 0) {
                $consulta = $pdo->prepare(
                    'UPDATE producao_energia
                     SET usina_id = ?, data_producao = ?, kwh_gerado = ?
                     WHERE id = ?'
                );
                $consulta->execute([$usina_id, $data_producao, $kwh_gerado, $id]);
            } else {
                $consulta = $pdo->prepare(
                    'INSERT INTO producao_energia (usina_id, data_producao, kwh_gerado)
                     VALUES (?, ?, ?)'
                );
                $consulta->execute([$usina_id, $data_producao, $kwh_gerado]);
            }

            redirecionar('views/producao/index_producao.php?msg=sucesso');
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

$consulta = $pdo->query('SELECT id, nome FROM usinas ORDER BY nome');
$usinas = $consulta->fetchAll();

$titulo = 'Produção de Energia - ' . ($id > 0 ? 'Editar' : 'Incluir');
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
                            <label for="usina_id">Usina</label>
                            <select id="usina_id" name="usina_id" required>
                                <option value="">Selecione</option>
                                <?php foreach ($usinas as $opcao): ?>
                                    <option value="<?= escapar($opcao['id']) ?>" <?= (string) $usina_id === (string) $opcao['id'] ? 'selected' : '' ?>>
                                        <?= escapar($opcao['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($usinas)): ?>
                                <small>Cadastre uma usina antes de incluir este lançamento.</small>
                            <?php endif; ?>
                        </div>

                        <div class="campo">
                            <label for="data_producao">Data de produção</label>
                            <input type="date" id="data_producao" name="data_producao"
                                   value="<?= escapar($data_producao) ?>" min="1000-01-01" max="9999-12-31" required>
                        </div>

                        <div class="campo">
                            <label for="kwh_gerado">Energia gerada (kWh)</label>
                            <input type="number" id="kwh_gerado" name="kwh_gerado"
                                   value="<?= escapar($kwh_gerado) ?>" min="0" max="9999999999.99" step="0.01" required>
                        </div>

                    </div>

                    <div class="acoes">
                        <button type="submit" class="botao">Gravar</button>
                        <a class="botao secundario" href="index_producao.php">Voltar para pesquisa</a>
                    </div>
                </form>
            </section>
<?php require __DIR__ . '/../footer.php'; ?>
```

## public_html/views/sidebar.php

```php
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
```

## public_html/views/tecnicos/exemplo_tailwind.php

```php
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
```

## public_html/views/tecnicos/index_tecnicos.php

```php
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
        $consulta = $pdo->prepare('DELETE FROM tecnicos WHERE id = ?');
        $consulta->execute([$id]);

        if ($consulta->rowCount() === 0) {
            throw new RuntimeException('Registro não encontrado.');
        }

        redirecionar('views/tecnicos/index_tecnicos.php?msg=sucesso');
    } catch (PDOException $excecao) {
        $_SESSION['mensagem_erro'] = mensagemBanco($excecao);
        redirecionar('views/tecnicos/index_tecnicos.php?error=1');
    } catch (RuntimeException $excecao) {
        $_SESSION['mensagem_erro'] = $excecao->getMessage();
        redirecionar('views/tecnicos/index_tecnicos.php?error=1');
    }
}

// Consulta dos registros que serão exibidos na tabela.
$consulta = $pdo->query(
    'SELECT r.*
     FROM tecnicos r
     ORDER BY r.id DESC'
);
$registros = $consulta->fetchAll();
$titulo = 'Técnicos';

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
                                <th>CPF</th>
                                <th>Especialidade</th>
                                <th>Telefone</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registros as $registro): ?>
                                <tr class="linha-registro">
                                    <td><?= escapar($registro['id']) ?></td>
                                    <td><?= escapar($registro['nome']) ?></td>
                                    <td><?= escapar($registro['cpf']) ?></td>
                                    <td><?= escapar($registro['especialidade']) ?></td>
                                    <td><?= escapar($registro['telefone']) ?></td>
                                    <td>
                                        <div class="acoes">
                                            <?php if (podeEditar()): ?>
                                                <a class="botao pequeno" href="tecnicos.php?id=<?= escapar($registro['id']) ?>">Editar</a>
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
                    <a class="botao incluir" href="tecnicos.php">Incluir Novo</a>
                <?php endif; ?>
            </section>
<?php require __DIR__ . '/../footer.php'; ?>
```

## public_html/views/tecnicos/tecnicos.php

```php
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
```

## public_html/views/usinas/index_usinas.php

```php
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
        $consulta = $pdo->prepare('DELETE FROM usinas WHERE id = ?');
        $consulta->execute([$id]);

        if ($consulta->rowCount() === 0) {
            throw new RuntimeException('Registro não encontrado.');
        }

        redirecionar('views/usinas/index_usinas.php?msg=sucesso');
    } catch (PDOException $excecao) {
        $_SESSION['mensagem_erro'] = mensagemBanco($excecao);
        redirecionar('views/usinas/index_usinas.php?error=1');
    } catch (RuntimeException $excecao) {
        $_SESSION['mensagem_erro'] = $excecao->getMessage();
        redirecionar('views/usinas/index_usinas.php?error=1');
    }
}

// Consulta dos registros que serão exibidos na tabela.
$consulta = $pdo->query(
    'SELECT r.*
     FROM usinas r
     ORDER BY r.id DESC'
);
$registros = $consulta->fetchAll();
$titulo = 'Usinas';

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
                                <th>Tipo</th>
                                <th>Capacidade (kW)</th>
                                <th>Localização</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registros as $registro): ?>
                                <tr class="linha-registro">
                                    <td><?= escapar($registro['id']) ?></td>
                                    <td><?= escapar($registro['nome']) ?></td>
                                    <td><?= escapar($registro['tipo']) ?></td>
                                    <td><?= escapar(formatarNumero($registro['capacidade_kw'])) ?></td>
                                    <td><?= escapar($registro['localizacao']) ?></td>
                                    <td><?= escapar($registro['status']) ?></td>
                                    <td>
                                        <div class="acoes">
                                            <?php if (podeEditar()): ?>
                                                <a class="botao pequeno" href="usinas.php?id=<?= escapar($registro['id']) ?>">Editar</a>
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
                                <td colspan="7">Nenhum registro encontrado.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <?php if (podeEditar()): ?>
                    <a class="botao incluir" href="usinas.php">Incluir Novo</a>
                <?php endif; ?>
            </section>
<?php require __DIR__ . '/../footer.php'; ?>
```

## public_html/views/usinas/usinas.php

```php
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
```

## public_html/views/usuarios/index_usuarios.php

```php
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
```

## public_html/views/usuarios/usuarios.php

```php
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
```
