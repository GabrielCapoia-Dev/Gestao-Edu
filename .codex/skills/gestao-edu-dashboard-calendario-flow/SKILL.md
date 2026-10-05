---
name: gestao-edu-dashboard-calendario-flow
description: Use para analisar ou evoluir dashboard, calendario, eventos manuais e agregados, avisos, publico-alvo, importacao e exportacao de calendario, reservas de veiculos, motoristas e alocacao de transporte no Gestao-Edu.
---

# Gestao-Edu Dashboard e Calendario Flow

## Objetivo

Manter coerencia entre eventos, fontes agregadas, publico-alvo, escolas, permissoes e transporte.

## Sequencia recomendada

1. Ler `app/Filament/Admin/Pages/Dashboard.php`, `GerenciarEventos.php` e `ImportarEventosCalendario.php`.
2. Revisar `CalendarEventAggregator` e as fontes em `app/Services/Dashboard/Calendar/Sources`.
3. Para eventos manuais, revisar `EventoCalendarioService`, `EventoCalendarioWorkflowService`, `EventoCalendarioAccessService` e `EventoCalendarioEscolaService`.
4. Para publico-alvo e avisos, revisar `PublicoAlvoService`, `PublicoAlvoOptionsService`, `AvisoService` e `AvisoBannerService`.
5. Para transporte, revisar `ReservaVeiculoService`, `EventoTransporteAlocacaoService`, `EventoTransporteDisponibilidadeService` e policies relacionadas.
6. Executar testes focados em `tests/Feature/Dashboard`.

## Regras criticas

- Distinguir evento persistido de evento projetado por uma fonte do agregador.
- Aplicar escopo e policy tanto na listagem quanto nas actions e exports.
- Assessoria pode ler eventos de toda a rede e criar eventos para qualquer escola, mas só altera os eventos que criou. RH pode consultar os eventos gerais de toda a rede, sem acesso a eventos agregados de manutenção ou avaliações, e gerencia apenas os próprios eventos, avisos e reservas. Validar permissões das fontes e ownership em todas as mutações secundárias e serviços de seleção escolar.
- Preservar escola, publico-alvo, recorrencia, origem e historico do evento.
- Impedir conflito de veiculo, motorista ou alocacao conforme a disponibilidade calculada pelo service.
- Reservas recorrentes de veículos são materializadas como ocorrências concretas, agrupadas por `grupo_recorrencia`; toda repetição usa data inicial e data final (não há término por contagem), com limite de 366 ocorrências e 10 anos. Manter criação atômica: um único conflito rejeita toda a série. A edição e o cancelamento atuais afetam uma ocorrência, não todo o grupo.
- Ao alterar disponibilidade recorrente, gerar a mesma lista de datas no formulário e no serviço de criação; validar conflitos em lote, mantendo a trava transacional do veículo e sem uma consulta por ocorrência.
- Manter importacao por linha rastreavel e idempotente.

## Saida esperada

- fontes e eventos afetados
- regras de visibilidade e publico-alvo
- impacto em importacao, exportacao e transporte
- testes focados e riscos de conflito
