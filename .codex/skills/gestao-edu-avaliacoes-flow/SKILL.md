---
name: gestao-edu-avaliacoes-flow
description: Use para analisar ou evoluir avaliacoes, tipos, periodos, pautas, alternativas, respostas, informacoes complementares, tela do professor, dashboard, exportacao de parecer, PDF e CSV no Gestao-Edu.
---

# Gestao-Edu Avaliacoes Flow

Use esta skill antes de mexer em avaliacoes, pareceres, pautas, alternativas, respostas de professor ou exportacoes pedagogicas.

## Objetivo

Mapear o fluxo avaliativo para evitar regressao em:

- criacao e escopo de avaliacoes
- tipos, periodos, pautas e alternativas
- respostas e informacoes complementares
- tela `Minhas Avaliacoes`
- progresso por turma, serie, escola e professor
- exportacao de parecer em PDF ou CSV
- dashboards e logs de exportacao

## Sequencia recomendada

1. Confirmar as telas principais:
   - `app/Filament/Admin/Pages/GestaoAvaliacoes.php`
   - `app/Filament/Admin/Pages/AvaliacoesProfessor.php`
   - `app/Filament/Admin/Pages/ExportarAvaliacoes.php`
   - `app/Filament/Admin/Pages/Relatorios/DashboardAvaliacoes.php`
2. Revisar resources auxiliares:
   - `app/Filament/Admin/Resources/Avaliacoes/AvaliacaoResource.php`
   - `app/Filament/Admin/Resources/Pautas/PautaResource.php`
   - `app/Filament/Admin/Resources/Alternativas/AlternativaResource.php`
3. Confirmar models e relacoes:
   - `app/Models/Avaliacao.php`
   - `app/Models/Pauta.php`
   - `app/Models/Alternativa.php`
   - `app/Models/AvaliacaoResposta.php`
   - `app/Models/AvaliacaoInformacaoComplementar.php`
   - `app/Models/TurmaComponenteProfessor.php`
4. Validar exportacoes:
   - `app/Services/Avaliacoes/AvaliacaoDocumentoExportService.php`
   - `app/Http/Controllers/AvaliacaoDocumentoExportController.php`
   - `resources/views/relatorios/Avaliacoes/documento.blade.php`
5. Conferir testes focados:
   - `tests/Feature/Avaliacoes`
   - `tests/Feature/Seeders/AvaliacaoFluxoSeederTest.php`

## Checklist de analise

- Confirmar se a avaliacao esta ativa e dentro do periodo esperado.
- Confirmar se o escopo vem de series, turmas, escolas e componentes.
- Confirmar se pautas e alternativas respeitam componente, serie e override.
- Confirmar se professor ve apenas turmas/componentes vinculados quando aplicavel.
- Confirmar se autosave, acao manual, acao em massa e exclusao de resposta aplicam a mesma regra.
- Confirmar impacto em PDF, CSV, dashboard, log de exportacao e fila.

## Saida esperada

Ao final da analise, registrar:

- escopo avaliativo afetado
- regras atuais de resposta e bloqueio
- tabelas pivot e models envolvidos
- impactos em parecer, exportacao e dashboard
- testes focados a executar
