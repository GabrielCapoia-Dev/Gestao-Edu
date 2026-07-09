# Pessoas e Cargos

- Domínio: identidade de pessoas, cargos e extensão pedagógica (Professor)
- Status: `active` (rework 2026-07-09)
- Fontes: `app/Models/Pessoa.php`, `ProfessorMatricula.php`, `Professor.php`, `app/Services/Pessoa*`, hub Filament `ServidorResource`

## Objetivo

Centralizar a **identidade física** em `Pessoa` (tabela `servidores`) e tratar **cargo** como catálogo (`FuncaoAdministrativa`) mapeado para **roles Spatie no User**. O cargo Professor ganha agregados de matrícula (turno) e lotação por escola, sem alterar policies nem o conceito de `turma_componente_professor`.

## Modelo

```
Pessoa (servidores)
  cpf unique normalizado · nome · email · telefone · user_id · status
  └── professor_matriculas (≤2)
        matricula · turno (manha|tarde|integral)
        └── professores (lotação por escola)
              id_escola · professor_matricula_id
              └── turma_componente_professor
```

- FKs internas: `id` numérico (`servidor_id`, `professor_id`, …)
- Rastro de negócio: **CPF** (pessoa) e **matrícula** (cargo professor)
- `Servidor extends Pessoa` por compatibilidade
- Roles/permissões: **User** + **Policies** (inalterado)

## Regras do Professor

1. Máximo 2 matrículas distintas por pessoa
2. Turno pertence à matrícula (não à escola)
3. Cada matrícula pode ter N escolas
4. Mesma escola pode ter 2 matrículas (ex.: manhã e tarde)
5. Turmas do form: filtradas por escola + turno da matrícula (`integral` → manhã/tarde/integral)
6. `turma_componente_professor` permanece o vínculo pedagógico canônico

## Pontos de entrada

| Camada | Path |
|--------|------|
| Hub UI | `app/Filament/Admin/Resources/Servidores/` (label Pessoas) |
| Service professor | `app/Services/PessoaProfessorService.php` |
| Form hydrate | `app/Services/PessoaProfessorFormService.php` |
| Acesso/roles | `app/Services/PessoaAcessoService.php` |
| Facade | `app/Services/ServidorService.php` |
| Migration | `database/migrations/2026_07_09_000001_create_professor_matriculas_table.php` |

## Non-goals desta leva

- Rename físico `servidores` → `pessoas`
- Drop de colunas espelho `professores.nome/email/telefone`
- Outros cargos além de Professor
- Mudança de policies / catálogo de permissões
