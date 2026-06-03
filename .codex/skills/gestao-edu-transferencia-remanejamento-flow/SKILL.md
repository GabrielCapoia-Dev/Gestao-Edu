---
name: gestao-edu-transferencia-remanejamento-flow
description: Use para analisar ou evoluir matricula pendente, transferencia, remanejamento, bloqueio e desbloqueio de dados avaliativos, parecer de transferencia, notificacoes e impedimento de matricula no Gestao-Edu.
---

# Gestao-Edu Transferencia e Remanejamento Flow

Use esta skill antes de mexer em movimentacao de alunos, matricula pendente, transferencia, remanejamento, bloqueios avaliativos ou parecer de transferencia.

## Objetivo

Mapear o fluxo de movimentacao de alunos para evitar regressao em:

- criacao de matricula e importacao de alunos
- impedimento por CGM ativo
- matricula pendente por transferencia
- remanejamento dentro da mesma escola e serie
- transferencia e geracao de parecer
- bloqueio de respostas na origem
- copia avaliativa no destino
- notificacoes para escola/professores

## Sequencia recomendada

1. Confirmar a modelagem e tela de alunos:
   - `app/Models/Aluno.php`
   - `app/Filament/Admin/Resources/Alunos/AlunoResource.php`
   - `app/Services/AlunoService.php`
   - `app/Services/Alunos/AlunoImportacaoSpreadsheetService.php`
2. Revisar movimentacao e pendencias:
   - `app/Services/AlunoMovimentacaoService.php`
   - `app/Services/AlunoTransferenciaPendenteService.php`
   - `app/Exceptions/MatriculaAlunoBloqueadaException.php`
   - `app/Http/Middleware/BloquearProfessorPendenciaTransferencia.php`
3. Revisar parecer e exportacao:
   - `app/Filament/Admin/Pages/ParecerTransferenciaAluno.php`
   - `app/Services/AlunoTransferenciaParecerService.php`
   - `app/Services/Avaliacoes/AvaliacaoDocumentoExportService.php`
4. Se tocar respostas, parecer ou progresso, acionar tambem `$gestao-edu-avaliacoes-flow`.
5. Conferir testes focados:
   - `tests/Feature/Alunos/AlunoMovimentacaoFluxoTest.php`
   - `tests/Feature/Alunos/AlunoTransferenciaPendenteServiceTest.php`
   - `tests/Feature/Avaliacoes/AvaliacaoAlunoStatusTest.php`

## Checklist de analise

- Confirmar se o aluno esta matriculado, pendente, transferido, remanejado ou historico.
- Confirmar se a regra usa `pendencia_origem_aluno_id`, `aluno_origem_id`, `movimentacao_origem` e `bloqueio_tipo`.
- Preservar aluno pendente visivel, mas bloqueado para resposta em avaliacoes.
- Preservar origem bloqueada apos transferencia ou remanejamento.
- Permitir edicao apenas da copia de destino quando `bloqueio_tipo = transferencia` e o parecer ja foi gerado.
- Manter dados de remanejamento bloqueados quando a regra exigir historico imutavel.
- Confirmar notificacoes e permissoes de gerar parecer, transferir e remanejar.

## Saida esperada

Ao final da analise, registrar:

- status e movimentacoes afetadas
- regras de bloqueio/desbloqueio avaliativo
- impacto em parecer, PDF, notificacoes e progresso
- riscos de duplicidade por CGM
- testes focados e validacao manual necessaria
