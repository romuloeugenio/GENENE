// Execute este arquivo antes de dashdark.js em um contexto JavaScript isolado.
// Os objetos abaixo simulam somente DOM, Chart.js e histórico, sem alterar o banco.
const elementosTeste = new Map();
const graficosTeste = [];
const eventosTeste = {};

/**
 * Cria um elemento mínimo para testar eventos e atualização de texto.
 * Parâmetros: nenhum.
 * @returns {Object} Elemento simulado com filhos, atributos e eventos.
 */
function elementoTeste() {
    return {
        textContent: '',
        value: '',
        children: [],
        attributes: {},
        listeners: {},
        dataset: {},
        appendChild(filho) { this.children.push(filho); },
        replaceChildren() { this.children = []; },
        setAttribute(chave, valor) { this.attributes[chave] = valor; },
        addEventListener(nome, funcao) { this.listeners[nome] = funcao; }
    };
}

const document = {
    addEventListener(nome, funcao) { eventosTeste[nome] = funcao; },
    getElementById(id) {
        if (!elementosTeste.has(id)) {
            elementosTeste.set(id, elementoTeste());
        }
        return elementosTeste.get(id);
    },
    querySelectorAll() { return []; },
    createElement() { return elementoTeste(); }
};

const window = {
    location: { href: 'http://localhost/gerene/views/dashdark.php' },
    history: {
        replaceState(estado, titulo, url) { window.location.href = String(url); }
    }
};

/**
 * Simula o armazenamento e a atualização de um gráfico Chart.js.
 * @param {Object} canvas Elemento onde o gráfico seria desenhado.
 * @param {Object} configuracao Configuração recebida da aplicação.
 * @returns {Object} Instância com os dados e contador de atualizações.
 */
function Chart(canvas, configuracao) {
    this.data = configuracao.data;
    this.options = configuracao.options;
    this.atualizacoes = 0;
    this.update = atualizarGraficoTeste;
    graficosTeste.push(this);
}

/**
 * Registra uma atualização solicitada pela aplicação ao gráfico simulado.
 * Parâmetros: nenhum.
 * @returns {void} Incrementa o contador da instância atual.
 */
function atualizarGraficoTeste() {
    this.atualizacoes++;
}

/**
 * Confere cálculos de período e a sincronização real dos eventos da aplicação.
 * Parâmetros: nenhum. Utiliza as funções de dashdark.js e os objetos simulados.
 * @returns {Array<string>} Verificações aprovadas; lança erro na primeira falha.
 */
function executarTestesDashDark() {
    const resultados = [];

    /**
     * Registra uma asserção desta execução.
     * @param {boolean} condicao Condição esperada como verdadeira.
     * @param {string} descricao Nome do comportamento verificado.
     * @returns {void} Registra sucesso ou lança uma exceção.
     */
    function conferirDark(condicao, descricao) {
        if (!condicao) {
            throw new Error(descricao);
        }
        resultados.push(descricao);
    }

    conferirDark(diasDark('2024-01-01', '2024-12-31') === 366, 'Contagem inclusiva em ano bissexto');
    conferirDark(calcularInicioDark('2024-12-31', 900, '2024-01-02') === '2024-01-02', 'Ampliação limitada a 365 dias');
    conferirDark(calcularInicioDark('2024-12-31', 0, '2024-01-02') === '2024-12-31', 'Redução limitada a um dia');
    conferirDark(proximaEtapaDark(365, 1) === 365, 'Período não ultrapassa um ano');
    conferirDark(proximaEtapaDark(1, -1) === 1, 'Período não reduz abaixo de um dia');
    conferirDark(proximaEtapaDark(31, 1) === 60 && proximaEtapaDark(31, -1) === 30, 'Período manual entra na próxima etapa');

    const serie = [];
    for (let indice = 0; indice < 365; indice++) {
        const data = new Date(Date.parse('2025-01-01T00:00:00Z') + indice * 86400000).toISOString().slice(0, 10);
        serie.push({ data: data, energia: 10, horas: 2, despesas: 3, diarias: 1 });
    }

    const mensal = agruparDark(serie, '2025-01-01', '2025-12-31');
    conferirDark(mensal.mensal && mensal.rotulos.length === 12, 'Ano completo agrupado em doze meses');
    conferirDark(mensal.energia[1] === 280 && mensal.horas[1] === 56, 'Fevereiro agregado corretamente');
    conferirDark(mensal.totais.energia === 3650 && mensal.totais.despesas === 1095, 'Totais anuais');
    const parcial = agruparDark(serie, '2025-01-15', '2025-04-10');
    conferirDark(parcial.energia[0] === 170 && parcial.energia[3] === 100, 'Meses parciais respeitam as datas');
    const diario = agruparDark(serie, '2025-12-25', '2025-12-31');
    conferirDark(!diario.mensal && diario.rotulos.length === 7, 'Intervalo curto agrupado por dia');

    document.getElementById('dados-dark').textContent = JSON.stringify({
        inicio: '2025-12-02',
        fim: '2025-12-31',
        limiteInicial: '2025-01-01',
        usinaId: 1,
        serie: serie,
        todasUsinas: [
            { nome: 'Usina A', valores: { '2025-01-01': 100, '2025-12-31': 20 } },
            { nome: 'Usina B', valores: { '2025-12-31': 70 } },
            { nome: 'Sem produção', valores: {} }
        ],
        producao: [
            { id: 2, data_producao: '2025-12-15', kwh_gerado: '10.00' },
            { id: 1, data_producao: '2025-01-01', kwh_gerado: '10.00' }
        ]
    });
    iniciarDark();
    conferirDark(graficosTeste.length === 4, 'Quatro gráficos inicializados');
    const area = document.getElementById('area-producao');
    conferirDark(typeof area.listeners.wheel === 'undefined', 'Gráfico não intercepta a roda do mouse');

    for (const dias of [7, 30, 90, 180, 365]) {
        selecionarPeriodoDark({ currentTarget: { dataset: { dias: String(dias) } } });
        conferirDark(diasDark(inicioDark, dadosDark.fim) === dias, 'Botão aplica período de ' + dias + ' dias');
        const agrupado = agruparDark(dadosDark.serie, inicioDark, dadosDark.fim);
        conferirDark(graficosTeste.every(grafico => grafico.data.labels.length === agrupado.rotulos.length), 'Quatro gráficos sincronizados em ' + dias + ' dias');
    }

    conferirDark(graficosTeste[0].data.datasets[0].data.reduce((a, b) => a + b, 0) === 3650, 'Botão 1A recupera dados fora do filtro inicial');
    conferirDark(graficosTeste[3].data.datasets[0].data.reduce((a, b) => a + b, 0) === 120, 'Comparação soma os meses do ano');
    conferirDark(document.getElementById('tabela-producao').children.length === 2, 'Tabela acompanha o botão 1A');
    conferirDark(window.location.href.includes('inicio=2025-01-01'), 'URL preserva o intervalo visível');
    selecionarPeriodoDark({ currentTarget: { dataset: { dias: '30' } } });
    conferirDark(document.getElementById('tabela-producao').children.length === 1, 'Tabela acompanha a redução para 30D');
    const misto = graficosTeste[1];
    conferirDark(misto.data.datasets.length === 2, 'Comparativo possui duas séries');
    conferirDark(misto.data.datasets[0].type === 'bar' && misto.data.datasets[1].type === 'line', 'Despesas em barras e horas extras em linha');
    conferirDark(misto.data.datasets[0].data.reduce((a, b) => a + b, 0) === 30, 'Barras incluem somente diárias no período de 30 dias');
    conferirDark(misto.data.datasets[1].data.reduce((a, b) => a + b, 0) === 60, 'Linha respeita o período de 30 dias');
    conferirDark(misto.options.scales.diarias.position === 'left' && misto.options.scales.horas.position === 'right', 'Dois eixos verticais independentes');
    conferirDark(misto.data.datasets[0].yAxisID === 'diarias' && misto.data.datasets[1].yAxisID === 'horas', 'Cada série usa seu próprio eixo');
    conferirDark(graficosTeste[2].data.datasets[0].data.reduce((a, b) => a + b, 0) === 90, 'Despesas totais incluem todas as categorias no período');
    const todas = graficosTeste[3].data.datasets;
    conferirDark(todas.length === 3 && todas[1].label === 'Usina B', 'Cada usina tem sua própria linha');
    conferirDark(todas[0].data.reduce((a, b) => a + b, 0) === 20 && todas[1].data.reduce((a, b) => a + b, 0) === 70, 'Período filtra cada usina sem misturar valores');
    conferirDark(todas[2].data.every(valor => valor === 0), 'Usina sem lançamentos aparece com zeros');
    return resultados;
}
