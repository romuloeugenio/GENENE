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
 * Filtra e agrupa as medidas com as mesmas datas de início e fim.
 * @param {Array<Object>} serie Série diária com energia, horas extras, diárias e despesas em reais.
 * @param {string} inicio Data inicial ISO.
 * @param {string} fim Data final ISO.
 * @param {Object} estimativas Produção estimada indexada por mês AAAA-MM.
 * @returns {Object} Rótulos, séries agrupadas, totais e indicação de agrupamento mensal.
 */
function agruparDark(serie, inicio, fim, estimativas = {}) {
    const mensal = diasDark(inicio, fim) > 62;
    const grupos = new Map();
    const mesesEstimadosAdicionados = new Set();
    const chaves = ['energia', 'horas', 'diarias', 'despesas'];
    const resultado = {
        mensal: mensal,
        rotulos: [],
        energia: [],
        estimada: [],
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
        const mes = periodo.slice(0, 7);
        const temEstimativaNoMes = Object.prototype.hasOwnProperty.call(estimativas, mes);
        const primeiraFaixaDoMes = !mesesEstimadosAdicionados.has(mes);
        resultado.estimada.push(
            temEstimativaNoMes && (mensal || primeiraFaixaDoMes) ? Number(estimativas[mes]) : null
        );
        mesesEstimadosAdicionados.add(mes);
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
    const nomes = { energia: 'Produção (mWh)', comparativo: 'Diárias (R$)', despesas: 'Despesas totais (R$)' };
    const unidades = { energia: 'mWh', comparativo: 'R$', despesas: 'R$' };
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

    if (chave === 'energia') {
        configuracao.data.datasets.push({
            type: 'line',
            label: 'Produção estimada (mWh)',
            data: agrupado.estimada,
            borderColor: '#f97316',
            backgroundColor: '#f97316',
            borderWidth: 2,
            fill: false,
            tension: 0.3,
            pointRadius: agrupado.estimada.some(valor => valor !== null) ? 4 : 0,
            pointHoverRadius: 5,
            spanGaps: false
        });
        configuracao.options.plugins.legend = {
            display: true,
            position: 'bottom',
            labels: { color: '#cbd5e1', usePointStyle: true, boxWidth: 8, padding: 16 }
        };
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
 * @returns {Object} Configuração de linhas em mWh com uma cor e legenda por usina.
 */
function configuracaoTodasUsinasDark(agrupado) {
    const configuracao = configuracaoDark('energia', agrupado);
    const cores = ['#a78bfa', '#38bdf8', '#2dd4bf', '#fbbf24', '#fb7185', '#cbd5e1'];
    configuracao.data.datasets = dadosDark.todasUsinas.map((usina, indice) => {
        const serie = dadosDark.serie.map(dia => ({
            data: dia.data,
            energia: Number(usina.valores[dia.data] || 0),
            estimada: null,
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
    const agrupado = agruparDark(dadosDark.serie, inicioDark, dadosDark.fim, dadosDark.producaoEstimada);
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

    document.getElementById('inicio').value = dataTextoDark(inicioDark);
    document.getElementById('fim').value = dataTextoDark(dadosDark.fim);
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
