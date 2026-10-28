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
 * @returns {void} Atualiza as linhas visíveis, o aviso de período e o total em mWh.
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
 * @param {Object} usina Dados com id, rotulos e energia em mWh.
 * @returns {void} Desenha o gráfico de produção da usina.
 */
function criarGraficoEnergia(usina) {
    new Chart(document.getElementById('energia-' + usina.id), {
        type: 'bar',
        data: {
            labels: usina.rotulos,
            datasets: [
                {
                    type: 'bar',
                    label: 'Energia realizada (mWh)',
                    data: usina.energia,
                    backgroundColor: '#168a72',
                    order: 2
                },
                {
                    type: 'line',
                    label: 'Produção estimada (mWh)',
                    data: usina.estimada,
                    borderColor: '#f97316',
                    backgroundColor: '#f97316',
                    borderWidth: 2,
                    pointRadius: usina.estimada.some(valor => valor !== null) ? 4 : 0,
                    pointHoverRadius: 5,
                    tension: 0.25,
                    fill: false,
                    spanGaps: false,
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
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'mWh' }
                }
            }
        }
    });
}

/**
 * Compara diárias em barras e despesas operacionais em linhas, em eixos independentes.
 * @param {Object} usina Dados com id, rotulos e séries de despesas em reais.
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
                },
                {
                    type: 'line',
                    label: 'MATERIAIS (R$)',
                    data: usina.materiais,
                    yAxisID: 'horas',
                    borderColor: '#8b5cf6',
                    backgroundColor: '#8b5cf6',
                    borderWidth: 2,
                    tension: 0.25,
                    fill: false,
                    order: 1
                },
                {
                    type: 'line',
                    label: 'SERVIÇOS (R$)',
                    data: usina.servicos,
                    yAxisID: 'horas',
                    borderColor: '#0ea5e9',
                    backgroundColor: '#0ea5e9',
                    borderWidth: 2,
                    tension: 0.25,
                    fill: false,
                    order: 1
                },
                {
                    type: 'line',
                    label: 'MAT/SERVIÇO (R$)',
                    data: usina.mat_servico,
                    yAxisID: 'horas',
                    borderColor: '#ec4899',
                    backgroundColor: '#ec4899',
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
                    title: { display: true, text: 'Despesas operacionais (R$)' }
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
 * @param {Object} dados Rótulos do período e lista de usinas com nome e energia em mWh.
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
                y: { beginAtZero: true, title: { display: true, text: 'mWh' } }
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', iniciarPagina);
