---
name: gestao-edu-professores-turmas-componentes-flow
description: Use para analisar ou evoluir professores, turmas, series, componentes curriculares, vinculos TurmaComponenteProfessor, matriculas de Pessoa/Professor, escopo por escola e relatorios pedagogicos no Gestao-Edu.
---

# Gestao-Edu Professores, Turmas e Componentes Flow

Use esta skill antes de mexer em professores, turmas, series, componentes curriculares ou nos vinculos entre professor, componente e turma.

## Objetivo

Mapear o fluxo pedagogico estrutural para evitar regressao em:

- cadastro e escopo de professores
- cadastro de turmas e series
- componentes curriculares da serie
- vinculo `TurmaComponenteProfessor`
- acesso do professor via usuario
- relatorios de professor, componente e turma
- filtros por escola e permissoes granulares de edicao

## Sequencia recomendada

1. Revisar os services que orquestram as telas:
   - `app/Services/PessoaProfessorService.php`
   - `app/Services/PessoaProfessorFormService.php`
   - `app/Services/ProfessorService.php`
   - `app/Services/TurmaService.php`
   - `app/Services/ProfessorEscolaVinculoService.php`
   - `app/Services/UserService.php`
   - `app/Services/ProfessorMovimentacaoService.php`
2. Confirmar os resources Filament:
   - `app/Filament/Admin/Resources/Professors/ProfessorResource.php`
   - `app/Filament/Admin/Resources/Turmas/TurmaResource.php`
   - `app/Filament/Admin/Resources/Series/SerieResource.php`
   - `app/Filament/Admin/Resources/ComponenteCurriculars/ComponenteCurricularResource.php`
3. Confirmar modelagem e pivots:
   - `app/Models/Professor.php`
   - `app/Models/Turma.php`
   - `app/Models/Serie.php`
   - `app/Models/ComponenteCurricular.php`
   - `app/Models/TurmaComponenteProfessor.php`
   - `app/Models/User.php`
4. Se tocar relatorios, revisar:
   - `app/Filament/Admin/Pages/Relatorios/RelatorioProfessorComponenteTurma.php`
   - `app/Filament/Admin/Pages/Relatorios/RelatorioComponenteProfessorFaltando.php`
   - `app/Filament/Admin/Pages/Relatorios/RelatoriosDashboard.php`
5. Verificar testes existentes:
   - `tests/Feature/Turmas/TurmaResourceScopeTest.php`
   - `tests/Feature/Relatorios/RelatoriosDashboardTest.php`
   - `tests/Feature/Pessoas/PessoaProfessorCadastroTest.php`
   - `tests/Feature/Pessoas/PessoaProfessorMatriculaInvariantesTest.php`

## Checklist de analise

- Confirmar se a tabela usa filtro por escola do usuario.
- Confirmar se editar escola, dados da turma, matricula ou nome exige permissao especifica.
- Confirmar se alterar serie recalcula componentes da turma.
- Confirmar se o vinculo professor-componente-turma preserva componentes sem professor.
- Confirmar se o professor possui `user_id` e acesso coerente as turmas.
- Tratar `Pessoa`, `Professor`, `PessoaMatricula` e `ProfessorMatricula` como fluxo integrado; nao recriar vinculos legados em paralelo.
- Preservar roles imutaveis e invariantes de matricula ao consolidar ou movimentar professor.
- Listar impactos em avaliacoes quando componentes ou vinculos forem alterados.

## Saida esperada

Ao final da analise, registrar:

- entidades e pivots afetados
- regras atuais de escopo e edicao
- pontos seguros para alterar formulario, tabela ou service
- impactos em avaliacoes e relatorios
- testes que devem ser criados ou atualizados
