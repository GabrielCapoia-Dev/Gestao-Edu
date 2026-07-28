---
name: manutencao-fluxo-pedidos
description: Use para analisar ou evoluir pedidos de manutencao no Gestao-Edu, incluindo Pedido, PedidoService, status, historico, anexos, setores, pedidos adicionais, notificacoes, escopo por usuario e persistencia de arquivos.
---

# Manutencao Fluxo de Pedidos

Use esta skill antes de alterar pedidos de manutencao, transicoes de status, setores operacionais, anexos, historico, notificacoes ou regras de visibilidade.

## Objetivo

Mapear o ciclo de vida de `Pedido` para evitar regressao em:

- criacao, gestao, encaminhamento e conclusao
- reabertura e pedidos adicionais
- historico, anexos e fotos
- setor operacional e empresa responsavel
- notificacoes e observers
- escopo por usuario, setor, escola e permissao global

## Sequencia recomendada

1. Ler [`docs/context-skills/fluxo-pedidos-manutencao.md`](../../../docs/context-skills/fluxo-pedidos-manutencao.md).
2. Ler [`docs/context-skills/regras-criticas-pedidos.md`](../../../docs/context-skills/regras-criticas-pedidos.md).
3. Se a mudanca tocar permissao ou escopo, acionar `$gestao-edu-acesso-permissoes-flow`.
4. Confirmar a modelagem principal:
   - `app/Models/Pedido.php`
   - `app/Models/PedidoProblema.php`
   - `app/Models/PedidoHistorico.php`
   - `app/Models/PedidoArquivo.php`
   - `app/Models/TipoStatus.php`
   - `app/Models/Setor.php`
5. Revisar a orquestracao do fluxo:
   - `app/Services/PedidoService.php`
   - `app/Observers/PedidoObserver.php`
   - `app/Services/UserSetorAccessService.php`
   - `app/Services/SetorPedidoAccessService.php`
   - `app/Filament/Admin/Resources/Pedidos`
   - `app/Http/Controllers/PedidoArquivoController.php`
6. Se tocar anexos, fotos ou relatorio, acionar tambem `$manutencao-relatorios-feedback`.

## Checklist de analise

- Identificar qual metodo do `PedidoService` centraliza a regra.
- Confirmar quais actions/forms Filament chamam essa regra.
- Confirmar status inicial, status final e setor operacional esperado.
- Confirmar se pedidos adicionais devem aparecer no vinculo, relatorio e feedback.
- Confirmar se historico, observer e notificacoes sao preservados.
- Confirmar se anexos usam `Storage::disk('public')` e diretorios `pedidos`, `pedidos/adicionais` ou `pedidos/conclusao`.
- Confirmar acesso herdado pela hierarquia de setores e capacidade exigida para cada transicao.
- Tratar `PedidoArquivoUpload` como componente canonico de upload e preservar validacao por tipo de arquivo.
- Para upload real, preferir validar `PedidoService` com `UploadedFile::fake()` e `Storage::fake('public')`.

## Saida esperada

Ao final da analise, registrar:

- status, setores e permissoes afetadas
- ponto transacional no `PedidoService`
- efeitos em historico, notificacao, anexos e pedidos adicionais
- riscos de escopo por setor ou escola
- testes focados que precisam ser criados ou atualizados
