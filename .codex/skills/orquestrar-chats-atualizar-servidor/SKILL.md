---
name: orquestrar-chats-atualizar-servidor
description: Orquestrar chats de desenvolvimento do Gestao-Edu, consolidar branches na main e atualizar o hub de testes com validação de conflitos e saúde dos containers.
---

# Orquestrar chats e atualizar servidor

Use esta skill quando o usuário indicar chats do projeto para revisar, integrar na `main` e publicar no hub de testes.

## Objetivo

Entregar uma `main` consolidada, publicada no repositório remoto e refletida no hub de testes, sem perder alterações de outros chats e sem concluir com o container `app` insalubre.

## Fluxo

1. Localizar os chats indicados com `list_threads` e ler o estado recente de cada um com `read_thread`.
2. No repositório, verificar branch atual, branches relacionadas, commits, working tree e `origin/main`.
3. Identificar se cada chat tem commits próprios ou se deixou alterações não commitadas no working tree compartilhado. Nunca presumir que branches diferentes contenham históricos independentes.
4. Revisar `git diff`, arquivos afetados e validações registradas pelos chats. Não descartar alterações não relacionadas sem esclarecer o escopo.
5. Consolidar alterações em uma branch apropriada, criando commit apenas quando necessário para preservar trabalho já realizado. Fazer merge na `main`; se houver conflito, resolver por análise do conteúdo e registrar o que foi preservado. Nunca usar `reset --hard`, `checkout --` para apagar trabalho ou merge forçado sem revisão.
6. Executar validações proporcionais: `git diff --check`, `php -l` nos PHP alterados e testes focados disponíveis. Registrar testes bloqueados por dependências ou ambiente.
7. Publicar a `main` no remoto somente quando essa atualização fizer parte do pedido.
8. Antes de atualizar o app no hub, inspecionar `docker-compose.yml`/`compose.yaml` e o entrypoint para identificar cron e workers que compartilham banco e cache de permissões. Se o boot executar migrations, seeders ou sincronização de roles/permissões, parar graciosamente os serviços `cron` e workers antes de iniciar o deploy; não deixar processos antigos consultar ou repovoar o cache durante a sincronização. Não matar processos à força nem interromper trabalhos sem respeitar o encerramento gracioso configurado.
9. Atualizar para `origin/main`, reconstruir/recriar o app e executar o procedimento de migrations e limpeza de caches definido pelo projeto. Aguardar o app ficar saudável e o entrypoint terminar antes de iniciar novamente cron e workers.
10. Verificar `docker compose ps` e o health status explícito do container `laravel-app-gestao-edu`. Além da saúde do container, validar que o cache de permissões do Spatie corresponde ao catálogo e às permissões atribuídas à role Admin no banco, e que uma permissão administrativa representativa é reconhecida pelo guard correto. Se banco e cache divergirem, não declarar sucesso: invalidar o cache após a sincronização, revalidar e investigar qualquer processo concorrente antes de reativar workers.
11. Só então iniciar/recriar cron e workers e confirmar que ficaram saudáveis. Só considerar concluído quando o app estiver `running healthy`, as permissões conferirem e os serviços em segundo plano estiverem operacionais; investigar logs e corrigir/reiniciar serviços se estiverem `starting`, `unhealthy`, parados ou causando 502.
12. Enviar a cada chat analisado um resumo com commit, alterações integradas, conflitos, validações e estado do hub.

## Regras de segurança

- Usar somente os chats e branches explicitamente indicados pelo usuário.
- Preservar permissões, regras de negócio e alterações existentes.
- Não expor senhas, tokens ou credenciais em mensagens, commits ou logs.
- Não acessar produção, executar comandos destrutivos ou remover containers órfãos sem solicitação específica.
- Nunca atualizar somente o serviço `app` enquanto cron/workers antigos continuam ativos quando o boot altera permissões compartilhadas; isso permite corrida na invalidação e repopulação do cache Redis do Spatie.
- Se houver conflito sem resolução segura, working tree ambíguo ou container que não fique saudável, parar e informar a pendência em vez de declarar sucesso.

## Relatório final

Informar apenas:

- alterações integradas e commit final;
- resultado do push e da atualização do hub;
- validações executadas e seus resultados;
- conflitos, riscos ou pendências relevantes.
