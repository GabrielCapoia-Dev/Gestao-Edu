---
name: gestao-edu-exportacoes-flow
description: Use para analisar ou evoluir a infraestrutura compartilhada de exportacoes do Gestao-Edu, incluindo ExportRequest, handlers, fila, armazenamento, download autorizado, retencao, monitoramento de travamentos e telas de acompanhamento.
---

# Gestao-Edu Exportacoes Flow

## Objetivo

Preservar o contrato comum de exportacoes sincronas ou em fila e evitar arquivos inacessiveis, vazamento de dados ou jobs presos.

## Sequencia recomendada

1. Confirmar `ExportRequest`, `ExportRequestService`, `ExportManager`, `ExportFileStorage` e `ExportFileResult`.
2. Revisar `app/Contracts/Exports/ExportHandler.php` e o handler do dominio em `app/Services/Exports/Handlers`.
3. Revisar `ProcessExportRequestJob`, `ExportRequestController`, `ExportRequestPolicy`, `MinhasExportacoes` e `StalledExportRequestMonitorService`.
4. Confirmar registro do handler, disk, nome de arquivo, MIME type, ownership e politica de retencao.
5. Executar primeiro `tests/Feature/Exports/ExportRequestServiceTest.php` e o teste focado do handler alterado.

## Regras criticas

- O job deve ser idempotente e registrar falha sem deixar requisicao indefinidamente em processamento.
- Download e listagem devem validar o proprietario ou permissao global.
- Nunca expor caminho fisico ou aceitar caminho fornecido pelo usuario.
- Limpeza e monitoramento devem respeitar requisicoes ativas e arquivos ainda validos.
- O handler deve devolver `ExportFileResult`; nao acoplar o job ao formato de um dominio.

## Saida esperada

- tipo e handler afetados
- transicoes de status e estrategia de falha
- armazenamento, autorizacao e retencao
- testes focados e riscos operacionais
