---
name: gestao-edu-backup-phpmyadmin
description: Use para preparar dumps SQL do Gestao-Edu para testes via phpMyAdmin, dividindo comandos completos em partes sequenciais e compactando cada parte como .sql.bz2 dentro do limite de upload.
---

# Gestao-Edu Backup para phpMyAdmin

Prepare backups exportados pelo phpMyAdmin para importacao em um banco de testes vazio. O resultado deve preservar comandos SQL completos e ser importado na ordem numerica.

## Execucao

Use o script da skill:

```powershell
python .codex/skills/gestao-edu-backup-phpmyadmin/scripts/prepare_phpmyadmin_backup.py "C:\caminho\backup.sql"
```

Opcoes relevantes:

- `--max-kb 2048`: limite por arquivo compactado; `2048` e o padrao.
- `--parts 2`: exige exatamente duas partes. Sem essa opcao, inicia em duas e aumenta somente se necessario.
- `--output-dir "C:\destino"`: grava em outro diretorio.
- `--force`: substitui partes com os mesmos nomes; use apenas quando a solicitacao autorizar a regeneracao.
- `--data-only`: gera apenas `INSERT INTO`, sem reconstruir tabelas. Use somente quando o banco de destino ja possuir a estrutura correta e todas as tabelas estiverem vazias.

## Invariantes

- Nao corte o arquivo por bytes ou linhas.
- A parte 1 deve conter todas as estruturas `CREATE TABLE` antes dos dados.
- A ultima parte deve aplicar `ALTER TABLE`, indices, `AUTO_INCREMENT` e chaves estrangeiras depois dos dados.
- Cada parte deve iniciar com verificacoes de chave estrangeira e unicidade desativadas e terminar com `COMMIT` e restauracao das verificacoes.
- Nao altere, filtre ou deduplique dados do dump.
- No modo `--data-only`, nao inclua `DROP TABLE`, `CREATE TABLE` nem `ALTER TABLE`; nomeie as saidas como `- dados - parte N.sql.bz2`.
- Nao importe o backup automaticamente. A skill apenas prepara arquivos para um banco de testes.
- Se o script rejeitar comandos nao suportados, nao os descarte. Informe a limitacao ou ajuste o script de forma testada.

## Validacao e entrega

Considere concluido somente quando o script confirmar:

- assinatura BZIP2 e descompactacao integral;
- todos os `CREATE TABLE`, `INSERT INTO` e `ALTER TABLE` contabilizados;
- nenhuma parte acima do limite configurado.

Informe os caminhos, tamanhos e a ordem de importacao. Oriente a usar um banco vazio e importar `parte 1`, `parte 2` e assim por diante.
