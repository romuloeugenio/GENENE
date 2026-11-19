# Campos usados nas consultas do GLPI

Este documento descreve os filtros enviados à API e os campos da resposta usados pelo relatório em `public_html/views/glpi_relatorio.php`.

## Endpoints e filtros

| Consulta | Endpoint | Filtro e paginação |
|---|---|---|
| Localização da categoria | `/v2.3/Dropdowns/ITILCategory` | `start` e `limit` (até 1.000 categorias) |
| Chamados para SLA e MTFR | `/v2.3/Assistance/Ticket` | `date=ge=<início>;date=lt=<fim>`; `start` e `limit` de 100 |
| Chamados concluídos para MTTR | `/v2.3/Assistance/Ticket` | `date_solve=ge=<início>;date_solve=lt=<fim>`; `start` e `limit` de 100 |

O horário final selecionado é inclusivo até o minuto inteiro: o limite superior enviado é o minuto seguinte, de forma exclusiva (`lt`). O relatório localiza a categoria `Clientes > Offline` e compara o identificador encontrado com `category.id` em cada chamado.

A consulta não envia uma lista de campos selecionados (`fields` ou equivalente). Ela lê os campos abaixo da resposta que a API disponibiliza.

## Campos da categoria

- `id`: identifica a categoria `Clientes > Offline`.
- `completename` ou `name`: usado para localizar o caminho da categoria. A leitura também aceita objetos aninhados e procura valores textuais recursivamente.

## Campos dos chamados

| Campo(s) | Uso |
|---|---|
| `category.id` | Mantém somente chamados da categoria selecionada. |
| `id` | Identificador exibido nas tabelas detalhadas. |
| `name`, `subject` ou `title` | Assunto do chamado; são alternativas em ordem de preferência. |
| `date` | Data e hora de abertura; filtro de período do conjunto usado para SLA e MTFR. |
| `date_solve` | Data e hora da solução; calcula o SLA e também serve como alternativa de data final do MTTR. |
| `take_into_account_date` ou `takeintoaccountdate` | Primeiro atendimento usado no MTFR e, como alternativas prioritárias, início do MTTR. |
| `date_close`, `closedate` ou `date_solve` | Data final da ordem usada no MTTR, na ordem indicada. |
| `date_assign`, `date_takeintoaccount` ou `date_assigned` | Alternativas de início do MTTR, após os campos de primeiro atendimento acima. |
| `client`, `customer`, `requester`, `entities_id`, `users_id_requester` ou `users_id_recipient` | Alternativas para identificar o cliente nas linhas detalhadas do MTTR. |

## Relação com os indicadores

- **SLA:** diferença em horas corridas entre `date` (abertura) e `date_solve` (solução).
- **MTFR:** diferença em horas entre o primeiro atendimento (`take_into_account_date` ou `takeintoaccountdate`) e a abertura ajustada pela função `dthora_comercial()`.
- **MTTR:** diferença em horas entre o início do atendimento (`take_into_account_date`, `takeintoaccountdate`, `date_assign`, `date_takeintoaccount` ou `date_assigned`) e a data de conclusão (`date_close`, `closedate` ou `date_solve`).

Chamados sem os horários necessários para o indicador não entram no respectivo cálculo. O relatório também valida a ordem cronológica dos horários. Não é feita uma filtragem direta pelo campo de status do chamado; os períodos são definidos pelos campos de data descritos acima.
