# Checklist para Nova Feature

> Observacao: este checklist continua util como apoio rapido, mas o fluxo canonico de implementacao agora esta em `docs/cross-cutting/playbook-nova-feature.md` e no template `docs/templates/feature-brief-template.md`.

## Objetivo

Orientar criacao de nova funcionalidade sem quebrar fluxos existentes nem duplicar regra ja presente no projeto.

## Onde aplicar

- Novos resources do Filament
- Extensoes de fluxo existente
- Integracoes com modulo de manutencao, merenda ou pedagogico

## Checklist

- Descubra primeiro em qual dominio a feature realmente entra.
- Revise as skills `arquitetura-geral`, `mapa-de-modulos` e a skill especifica do dominio.
- Procure regra existente antes de criar nova camada paralela.
- Decida se a regra deve viver em model, service, policy ou UI do Filament e mantenha consistencia com o padrao local.
- Verifique se a feature depende de permissao nova, policy nova ou filtro por escola/setor.
- Verifique se precisa de historico, notificacao, exportacao, anexo ou scheduler.
- Atualize seeders se a feature depender de dados iniciais ou vocabulario funcional novo.
- Planeje validacao manual cobrindo perfil Admin e perfil restrito.
- Se a feature tocar fluxo existente, rode o checklist de logica sensivel.

## Riscos e cuidados

- Nao introduzir regra nova ignorando nomes e estados ja usados pelo sistema.
- Nao colocar toda regra apenas na tela se ela tiver impacto de dominio.
- Nao assumir que policy sozinha resolve visibilidade; o projeto usa filtros adicionais em query/service.

## Quando consultar

- No inicio do desenho da feature
- Antes de abrir o primeiro patch de implementacao
