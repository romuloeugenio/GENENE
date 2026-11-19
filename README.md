# GERENE — versão local para XAMPP

## Atualização do banco: Despesas e Categorias

A migração de 24/09/2026 foi aplicada no banco local, após backup completo em `backups/`. Esse backup fica fora da pasta pública e dos ZIPs.

- `despesas_material` passou a se chamar `despesas`.
- `categoria` possui `id_categoria INT UNSIGNED AUTO_INCREMENT`, `descricao_categoria VARCHAR(120)` e `Centro_de_custo INT`.
- `despesas.categoria` é `INT UNSIGNED` e referencia `categoria.id_categoria`.
- `despesas.tecnico` é `INT UNSIGNED`, opcional, e referencia `tecnicos.id`.
- O centro de custo pode ficar vazio quando ainda não for conhecido. A descrição da categoria é única.
- As categorias textuais antigas são convertidas em registros da nova tabela, mantendo seus vínculos. Técnicos e centros de custo desconhecidos ficam nulos, sem valores fictícios.
- As chaves estrangeiras impedem excluir uma categoria ou técnico com despesas vinculadas.

Use **Categorias** no menu para cadastrar as opções. O formulário de **Despesas** usa comboboxes para categoria e técnico; a listagem exibe os nomes e o centro de custo. Os novos caminhos são `views/despesas/despesas.php` e `views/despesas/index_despesas.php`.

O menu **Produção Estimada** permite incluir, consultar, editar e excluir a previsão mensal de cada usina. `mes_estimado` é armazenado como DATE no primeiro dia do mês selecionado, e cada usina pode ter somente uma previsão por mês. A produção usa DECIMAL(12,2) e a chave estrangeira impede excluir usinas com previsões.

Diárias e Horas Extras foram retiradas dos dois menus. Suas tabelas e páginas continuam preservadas; os gráficos usam a nova estrutura de despesas. Os dois dashboards consultam a nova tabela `despesas` e exibem o total de despesas de todas as categorias.

Para atualizar outra instalação antiga, faça backup e execute, uma única vez, `migrations/20260924_despesas_categoria.sql` no banco correto. O script CLI `migrations/aplicar_despesas.php` usa a configuração da aplicação e recusa execução se `despesas` ou `categoria` já existir. Instalações novas usam somente o `schema.sql` atualizado; não precisam da migração. O SQL de migração não deve ser importado novamente no banco local já atualizado.

Para atualizar um banco já instalado com a Produção Estimada, execute `C:\xampp\php\php.exe migrations\aplicar_producao_estimada.php` no diretório do projeto. O script cria a tabela se ela ainda não existir.

Para adicionar o nível de usuário em um banco existente, execute `C:\xampp\php\php.exe migrations\aplicar_nivel_usuario.php`. A migração atribui o nível 1 aos usuários atuais; instalações novas já criam a coluna `Nivel_Usuario` com esse padrão. Os níveis disponíveis no formulário são 1 (Administrador), 2 (Gerência), 3 (Operador de usina), 5 (Técnico), 6 (Financeiro) e 7 (Suporte).

O relatório **Relatório GLPI** usa OAuth 2.0 na API v2.3. Configure `api_url`, `glpi_client_id`, `glpi_client_secret`, `glpi_username` e `glpi_password` localmente em `glpi.credentials.php`, arquivo ignorado pelo Git. O teste independente de autenticação e consulta é `php test_glpi_connection.php --allow-http`. O endpoint atual usa HTTP sem criptografia; credenciais e tokens podem ser interceptados na rede, então habilite HTTPS antes de usar em produção. O relatório agrupa os chamados pela data de abertura em 2026 e calcula a média de horas corridas até a solução, incluindo somente chamados solucionados até 31/12/2026. O indicador MTTR permite selecionar as datas inicial e final, calcula o tempo entre o início do atendimento (`takeintoaccountdate` ou `date_assign`) e a baixa (`date_close`, `closedate` ou `date_solve`) e mostra a média por dia, semana ou mês conforme o intervalo.

O teste inicial da API REST do Zabbix é `php test_zabbix_connection.php`. Configure o endereço completo que termina em `/api_jsonrpc.php` em `zabbix.credentials.php`, arquivo ignorado pelo Git. O script chama `apiinfo.version` via JSON-RPC e informa o status HTTP e a versão da API; esse teste não exige usuário, senha ou token. Para consultar problemas não resolvidos por período, configure `username` e `password` no arquivo local `zabbix.json` (também ignorado pelo Git), no formato `{"username":"seu_usuario","password":"sua_senha"}`, e acesse **Problemas Zabbix** pelo menu, que abre `public_html/views/zabbix_ticket.php`. A página lê essas credenciais diretamente do arquivo e encerra a sessão de API após a consulta. Para uma instalação HTTP, confirme o risco executando `php test_zabbix_connection.php --allow-http`; HTTPS é recomendado. Como a instância configurada usa HTTP, as credenciais trafegam sem criptografia pela rede.

Para consultar tickets da Auvo em um período, preencha `api_key` e `api_token` em `auvo.credentials.php`, arquivo ignorado pelo Git, e acesse **Autenticar Auvo** como administrador. Selecione as datas inicial e final. A página envia as credenciais em JSON para `https://api.auvo.com.br/v2/login`, lê o campo `token` (com suporte alternativo a `result.accessToken`), grava o token em `auvo.tokens.json` e consulta `/tickets` e `/tasks` com `paramFilter` para o período selecionado, percorrendo as páginas. Os horários de atendimento vêm das tarefas relacionadas ao ticket por `ticketId`: primeira entrada (`checkInDate`) e última saída (`checkOutDate`). O arquivo do token é ignorado pelo Git e fica fora da pasta pública; o token não é exibido na página.

Para gerar a página HTML dos chamados abertos de 01/08/2026 a 31/08/2026, com abertura, fechamento e horas corridas até o fechamento, execute `php chamados_glpi_agosto_2026.php --allow-http > chamados_agosto_2026.html` e abra esse arquivo no navegador. Chamados ainda abertos aparecem sem data de fechamento e sem duração.

O teste `C:\xampp\php\php.exe tests/migracao_despesas.php` passou em 9 verificações de preservação de dados e chaves estrangeiras em um banco temporário. A suíte HTTP contém 175 verificações; as verificações do schema, migração, cadastro/edição do nível de usuário, visibilidade de menu e estado de configuração do relatório GLPI passaram, mas a execução atual foi interrompida ao testar a inclusão de horas extras. A sincronização de DashDark e Dash Select passou em 80 verificações JavaScript.

Acesse **http://localhost/gerene/**.

- E-mail inicial: `admin@usina.com`
- Senha inicial: `admin`
- Banco local: `gerene_local`
- MySQL: `127.0.0.1`, porta `3306`, usuário `root`, senha vazia.

Esta versão foi reescrita em PHP procedural, seguindo a organização dos exemplos fornecidos: processamento no início da página e HTML logo abaixo. Não há framework, gerador de formulário em execução ou catálogo dinâmico de módulos. As consultas usam PDO e parâmetros preparados.

## Como está instalado

Apache e MySQL do XAMPP devem permanecer iniciados. A pasta `C:\xampp\htdocs\gerene` é uma junção para `C:\Users\romulo.er\GERENE\public_html`. Os arquivos continuam no projeto aberto no editor: salvar uma alteração e atualizar o navegador basta.

O sistema utiliza o banco `gerene_local`. Cadastre usinas, técnicos e categorias; depois registre produção e despesas. As alterações de estrutura preservam os dados já existentes.


O XAMPP encontrado nesta máquina usa PHP 8.0.30 e MariaDB 10.4.32. O código funciona nesse ambiente e também utiliza sintaxe compatível com PHP 8.1+. Nenhuma versão do PHP da máquina foi alterada.

## Onde alterar cada parte

| Arquivo | Conteúdo |
| --- | --- |
| `public_html/config.php` | Banco, nome da empresa, endereço `/gerene` e sessão. |
| `public_html/db.php` | Função de conexão PDO. |
| `public_html/biblioteca.php` | Funções comuns de sessão, permissões, validação e formatação. |
| `public_html/index.php` | Login. Após autenticar, abre o DashDark. |
| `public_html/logout.php` | Encerra a sessão por POST. |
| `public_html/views/usinas/index_usinas.php` | Consulta, tabela e exclusão de usinas. |
| `public_html/views/usinas/usinas.php` | Recebe os campos, valida, inclui/edita e mostra o formulário. |
| `public_html/views/usuarios/`, `tecnicos/`, `producao/`, `producao_estimada/`, `despesas/`, `horas_extras/`, `diarias/` | Mesmo padrão de páginas independentes. |
| `public_html/views/dashboard.php` | Filtro de datas e consultas dos gráficos. |
| `public_html/views/dashdark.php` | Novo dashboard escuro, seleção de usina e consultas da janela anual. |
| `public_html/assets/js/dashdark.js` | Agrupamento por dia/mês e sincronização dos três gráficos pelos botões de período. |
| `public_html/views/header.php`, `sidebar.php`, `footer.php` | Partes comuns do HTML. |
| `public_html/assets/css/custom.css` | Estilos indentados por seletor e propriedade. |
| `public_html/assets/js/main.js` | Menu, pesquisa, confirmação de exclusão e gráficos. |
| `public_html/schema.sql` | Criação das tabelas e do administrador. |

Todas as funções PHP e JavaScript têm comentários com descrição, parâmetros e retorno. Funções sem parâmetros também informam quais dados da sessão ou da página utilizam. O SQL de cada cadastro está escrito no próprio arquivo.

Para entender o fluxo, comece por `views/usinas/usinas.php`: carregamento do registro → leitura do POST → validações → INSERT/UPDATE → formulário HTML.

## Regras do sistema

- `pode_editar = S` permite incluir e editar. `pode_excluir = S` permite excluir. A validação é feita também no servidor.
- A gestão de usuários exige as duas permissões. Não é permitido excluir o próprio usuário nem retirar as permissões do último gestor.
- `Nivel_Usuario` aceita 1 (Administrador), 2 (Gerência), 3 (Operador de usina), 5 (Técnico), 6 (Financeiro) ou 7 (Suporte). Novos usuários e registros migrados recebem nível 1 por padrão.
- A opção **Usuários** só aparece nos menus para o nível 1 (Administrador), além de exigir as permissões de edição e exclusão já existentes.
- Senhas são armazenadas com hash. Senha em branco ao editar mantém a atual. Novas senhas devem ter entre 8 e 72 bytes. Troque a senha inicial após entrar.
- Sessões expiram após 30 minutos sem atividade. Todos os POSTs usam token CSRF.
- CPF, e-mail, datas, números e vínculos são validados. CPF e e-mail são únicos. Há um lançamento de produção por usina/data.
- As chaves estrangeiras impedem excluir uma usina ou técnico que ainda tenha lançamentos.
- A pesquisa instantânea filtra as linhas já carregadas; a tabela tem altura máxima de 400 px.
- O dashboard mostra cards de todas as usinas por ID. Cada card apresenta os totais de energia gerada e estimada no período selecionado. Os gráficos individuais de produção exibem a energia realizada e a estimativa mensal da mesma usina; em períodos diários, a estimativa aparece uma vez no primeiro dia visível do mês. Soma cada medida separadamente, incluindo zeros para períodos sem dados. Intervalos de até 63 dias inclusivos agrupam por dia; maiores agrupam por mês. No DashDark, o gráfico acumulado abaixo de Produção da Usina reinicia em 01/01 do ano da data inicial e acumula produção realizada e estimada até a data final selecionada. Na série realizada, meses posteriores ao último período com produção não são representados na linha. O limite é 3.660 dias.
- O comparativo soma por dia ou mês as despesas das categorias 4 MATERIAIS, 5 SERVIÇOS e 6 MAT/SERVIÇO em linhas separadas, além da linha de HORAS EXTRAS. As barras do comparativo mostram somente DIARIAS. Um gráfico acumulado separado mantém essas cinco séries como linhas e reinicia a soma no início do período selecionado; cada linha termina após o último mês em que há despesa. O gráfico inferior mostra todas as despesas em barras e inclui a produção de energia da tabela `producao_energia` como área em eixo independente de mWh, sem somar novamente as séries do comparativo; a área termina após o último período com produção.
- Tailwind e Chart.js continuam sendo carregados por CDN, conforme a solicitação original. Os gráficos precisam de internet para carregar a biblioteca; as tabelas de valores permanecem disponíveis. O CSS local mantém o layout básico.

## Instalar em outro XAMPP

1. Inicie Apache e MySQL.
2. Copie o conteúdo de `public_html` para `C:\xampp\htdocs\gerene`.
3. Crie o banco `gerene_local` no phpMyAdmin e importe `schema.sql` dentro desse banco vazio.
4. Confira os dados de conexão em `config.php`.
5. Acesse `http://localhost/gerene/`.

Alternativamente, no diretório deste projeto, execute `C:\xampp\php\php.exe instalar_local.php` para criar o banco. O instalador não altera um banco que já contenha tabelas. Ele pressupõe a configuração padrão local do MySQL indicada acima.

O pacote `gerene-xampp.zip` contém uma pasta `gerene`, pronta para extração em `C:\xampp\htdocs`. O arquivo `CODIGO_COMPLETO.md` contém os arquivos de publicação, cada um identificado pelo caminho.

## Testes

Execute no diretório do projeto:

```powershell
C:\xampp\php\php.exe tests\integration.php
```

A suíte contém **175 verificações**, usando o PHP do XAMPP. Ela cria um banco `gerene_teste_<aleatório>`, testa login, CRUD, validações, permissões, proteção CSRF/XSS/SQL injection e agregações dos dashboards, e remove somente esse banco ao terminar. Também verifica login, listagens, arquivos estáticos e bloqueio HTTP de arquivos internos no Apache real. Essa última parte pressupõe o administrador inicial ainda com a senha `admin`.

Os arquivos PHP alterados passaram na verificação de sintaxe do PHP do XAMPP. A etapa atual é local; nenhuma publicação na Hostinger foi realizada.

Os cadastros usam o nome da pasta: o formulário é <pasta>.php e a listagem é index_<pasta>.php. Exemplo: horas_extras/horas_extras.php e horas_extras/index_horas_extras.php.

## DashDark e Dash Select

O DashDark é a página inicial após o login e está em **http://localhost/gerene/views/dashdark.php**. O menu também oferece **Dash Select**, uma cópia do DashDark que mantém o mesmo layout, filtros e gráficos, alterando apenas a paleta escura para clara.

Os dois painéis mantêm a mesma composição Tailwind via CDN; DashDark usa tema escuro e Dash Select aplica tema claro. O gráfico principal usa [linha com preenchimento no Chart.js](https://www.chartjs.org/docs/latest/charts/line.html) para produção de energia em mWh. Logo abaixo, Produção Acumulada da Usina usa o mesmo estilo com a produção realizada e estimada acumuladas desde 01/01 do ano até a data final selecionada. O painel comparativo usa DIARIAS em barras no eixo esquerdo e HORAS EXTRAS, MATERIAIS (4), SERVIÇOS (5) e MAT/SERVIÇO (6) em linhas no eixo direito, todos em R$. O painel seguinte acumula essas cinco séries em linhas desde o início do período. Em ambos os temas, os painéis de despesas totais e da distribuição de despesas por categoria aparecem lado a lado; a distribuição lista os valores por categoria e apresenta o total do período em um card.

1. Escolha as datas no formato **DD/MM/AAAA** e a usina no topo. O período inicial é de 01/01/2026 a 31/12/2026. Todas as usinas cadastradas estão disponíveis; a UEG (ID 2) é selecionada inicialmente, ou a primeira usina se a UEG não estiver cadastrada.
2. Clique em **Aplicar período**. Alterar a usina também aplica o formulário automaticamente quando JavaScript está ativo.
3. Use os botões **7D**, **30D**, **3M**, **6M** e **1A** para ajustar o período. A roda do mouse apenas rola a página e não altera os gráficos.
4. Os atalhos **7D**, **30D**, **3M**, **6M** e **1A** funcionam também em dispositivos sem roda do mouse. Representam 7, 30, 90, 180 e 365 dias. Com o gráfico focado pelo teclado, menos amplia e mais reduz.
5. Os gráficos e indicadores seguem o período selecionado. No gráfico de Produção da usina, a linha laranja contínua mostra as estimativas mensais da usina selecionada; quando o período é diário, a estimativa aparece uma vez no primeiro dia visível daquele mês. Até 62 dias a produção realizada é diária; acima disso é mensal. Meses parciais incluem somente os dias visíveis.
6. Abra **Consultar valores da tabela de produção de energia**, abaixo dos gráficos, para ver os lançamentos da usina no período.

O limite de um ano corresponde a **365 dias inclusivos**, também em anos bissextos. O servidor carrega até 365 dias encerrados na data final escolhida, permitindo ampliar além do filtro inicial sem inventar valores nem misturar usinas. Para consultar outro ano ou avançar no tempo, altere a data final e aplique o filtro. A URL acompanha os botões, permitindo recarregar ou compartilhar a seleção. A roda do mouse rola a página mesmo sobre o gráfico; Ctrl + roda mantém o zoom do navegador.

Sem lançamentos, os gráficos mostram zeros e a tabela informa a ausência de registros. Sem usinas, há um estado vazio com acesso ao cadastro. Sem JavaScript ou Chart.js, os totais e a tabela inicial continuam disponíveis. A versão clássica conserva suas regras de período anteriores.

Além dos testes HTTP, **80 verificações JavaScript** em `tests/dashdark.test.js` passaram em contexto isolado com DOM e Chart.js simulados. Elas cobrem anos bissextos, limites, meses parciais, estimativas de produção, botões de período, atualização dos gráficos, totais, tabela e URL. A conferência visual por navegador não foi concluída porque o conector disponível solicitou nova autenticação.

## Massa de teste de Horas Extras

Foram acrescentadas 100 despesas na categoria HORAS EXTRAS: 20 por cada uma das cinco usinas, em datas aleatórias nos nove meses de janeiro a setembro de 2026. Os valores seguem a faixa anterior de R$ 100 a R$ 1.000. O lote é identificado por [TESTE-HORAS-EXTRAS-2026-JAN-SET] e o script tests/gerar_despesas_horas_extras.php impede repetir o mesmo lote. Esses registros pertencem à tabela despesas, e não à tabela histórica horas_extras. Os lançamentos anteriores foram preservados.

## Gráficos financeiros com dois eixos

Nos dashboards claro e escuro, o comparativo usa DIARIAS em barras no eixo esquerdo e coloca HORAS EXTRAS, MATERIAIS (4), SERVIÇOS (5) e MAT/SERVIÇO (6) em linhas no eixo direito. As escalas verticais são independentes e identificadas pelas categorias; a altura visual de séries em eixos diferentes não implica valores iguais.

Abaixo dele, Despesas Totais mostra todas as categorias em barras e inclui a produção de energia da tabela `producao_energia` como área no eixo direito de mWh, usando o mesmo período e usina. Os botões 7D, 30D, 3M, 6M e 1A atualizam todos os gráficos: no DashDark sem recarregar, no dashboard claro aplicando o filtro GET. A roda do mouse continua sem alterar o período.

Validação desta alteração: sintaxe PHP validada; os testes confirmaram que o nível 1 vê a opção Usuários e que o nível 5 não a vê nos menus. O menu GLPI e a página sem tokens também foram testados sem abrir conexão externa. A suíte HTTP continua interrompendo no caso de inclusão de horas extras antes de completar as 175 verificações.