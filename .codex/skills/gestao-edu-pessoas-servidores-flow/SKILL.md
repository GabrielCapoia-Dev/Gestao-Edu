---
name: gestao-edu-pessoas-servidores-flow
description: Use para analisar ou evoluir Pessoa, Servidor, Professor, usuarios vinculados, matriculas, funcoes administrativas, equipes gestoras, escopo por escola ou setor, consolidacao e normalizacao de cadastros legados no Gestao-Edu.
---

# Gestao-Edu Pessoas e Servidores Flow

## Objetivo

Preservar a identidade unica da pessoa e seus vinculos funcionais sem duplicar registros ou ampliar acesso indevidamente.

## Sequencia recomendada

1. Confirmar models: `Pessoa`, `PessoaMatricula`, `Servidor`, `Professor`, `ServidorFuncaoAdministrativa` e `User`.
2. Ler os services `PessoaVinculoService`, `PessoaScopeService`, `PessoaUsuarioService`, `PessoaConsolidacaoService` e o service especifico do cargo.
3. Revisar `app/Filament/Admin/Resources/Servidores` e as actions/schemas de acesso, matricula e equipe gestora.
4. Para professores, acionar `$gestao-edu-professores-turmas-componentes-flow`.
5. Para roles, permissoes ou preview, acionar `$gestao-edu-acesso-permissoes-flow`.
6. Localizar testes em `tests/Feature/Pessoas` e `tests/Feature/Servidores`.

## Regras criticas

- Nao criar Pessoa paralela quando CPF, email ou vinculo permitem consolidacao segura.
- Preservar invariantes de matricula, escola, setor, cargo e periodo.
- Somente Professor pode ter `jornada`; cargos administrativos usam matrículas funcionais comuns. Uma pessoa pode ter até duas matrículas nos turnos manhã+tarde, ou uma matrícula integral (40h).
- Para um cargo funcional novo, sincronizar `FuncaoAdministrativa`, role/preset, criação/edição da Pessoa, formulários Filament e Livewire, filtro de cargo, acesso de leitura/escrita e o fluxo de reconciliação de roles. Cargo RH não recebe vínculo escolar.
- RH autorizado a gerenciar vínculos estruturais pode associar escolas ao cadastro de Assessoria Pedagógica; essa associação é informativa e não deve ser usada como escopo de leitura do assessor.
- Assessoria Pedagógica e RH com consulta a toda a rede podem usar todos os filtros de cargo na listagem de servidores. Isso é somente leitura: não concede `Gerenciar Vínculos Estruturais de Pessoas` nem libera ações de edição de cargos.
- Separar identidade, vinculo funcional e credencial de acesso.
- Tratar normalizacao e backfill como operacoes idempotentes e auditaveis.
- Confirmar impacto em equipe gestora, professor, manutencao e obras antes de excluir vinculo.
- Trocas de cargo devem encerrar os vínculos incompatíveis sem apagar a identidade nem registros pedagógicos; manter movimentações append-only com antes/depois de cargo, escola, lotação, matrícula/turno e atribuições pedagógicas, exibidas e exportáveis no escopo autorizado.
- A ficha funcional exportada deve ser PDF autenticado/autorizado pelo mesmo escopo `view` do servidor; reunir identificação, contatos, vínculos atuais e históricos, lotação, matrículas, atribuições pedagógicas, perfis de acesso e histórico de saldo, sem exportar credenciais ou segredos.
- Não inventar timeline retroativa: dados antigos só podem ser apresentados como histórico quando houver fonte persistida confiável; registrar alterações novas na mesma transação da edição.

## Saida esperada

- identidade e vinculos afetados
- escopos e acessos preservados
- estrategia de consolidacao ou movimentacao
- testes focados e risco para dados legados
