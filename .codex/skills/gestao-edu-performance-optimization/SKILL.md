---
name: gestao-edu-performance-optimization
description: "Analisa e otimiza gargalos de desempenho no Gestao-Edu, especialmente Livewire, avaliacao, queries, N+1, payloads, autosave e carga concorrente, preservando as regras de negocio."
---

# Gestao Edu Performance Optimization

Use esta skill quando o pedido envolver lentidao, CPU, memoria, N+1, requests redundantes, reconstrucoes de tela, payload excessivo, autosave, consultas SQL, indices, Livewire/Filament/Octane ou testes concorrentes no Gestao-Edu.

## Limites

- Preserve regras de negocio, autorizacao, escopos, validacoes, versionamento, relacionamentos e formato dos dados. Troque a forma de leitura ou persistencia somente quando o resultado observado continuar equivalente.
- Diferencie analise de implementacao. Em pedido somente diagnostico, nao edite codigo nem altere banco.
- Nao trate um benchmark isolado como prova de causa. Registre ambiente, versao, dataset, numero de usuarios, rampa, duracao e limites usados.
- Para carga real do projeto, use o Hub de Testes autorizado e o MySQL do Docker; nao use SQLite, mocks ou um caminho especial de teste para mascarar o resultado. Nunca execute carga em producao sem autorizacao explicita.
- Nao desabilite rate limiting, autorizacao, CSRF, controle de versao ou locks para fazer o teste passar. Se um limitador externo interferir, aumente a rampa e registre a interferencia.
- Antes de alterar configuracao de infraestrutura, confirme o caminho real da requisicao. `default.conf` e `octane.conf` sao arquivos do Nginx; Octane/Swoole e o processo PHP. Nao presuma PHP-FPM.

## Metodo

1. Defina o fluxo e o criterio de sucesso. Separe abertura, navegacao, interacao Livewire, autosave, leitura de dashboard, exportacao e login.
2. Estabeleca uma linha de base no mesmo ambiente e dados. Colete latencia P50/P95/P99, throughput, erros, conflitos, queries, tempo de query, CPU e memoria do app, banco, Redis, fila e Pulse.
3. Inspecione somente os arquivos relevantes. Procure loops que consultam banco, `load()`/`find()` dentro de loops, `wire:model.live` em colecoes grandes, arrays publicos do Livewire, renderizacoes completas apos cada mudanca, eager loading sem restricao e dados carregados que nao chegam a ser usados.
4. Formule a causa com evidencia: trecho de codigo, contagem de queries, log/Pulse, `EXPLAIN`, metricas de container ou comparacao antes/depois. Classifique o gargalo como app, banco, proxy, fila, cache ou limite de infraestrutura.
5. Implemente a menor alteracao segura. Preserve a resposta da tela e o dado salvo; evite refatoracao transversal sem necessidade.
6. Valide sintaxe, diff, testes focados e um fluxo real. Se a imagem nao tiver PHPUnit ou `artisan test`, informe a limitacao e use as validacoes disponiveis, sem declarar testes inexistentes.
7. Repita a carga em degraus. Use contas distintas e dados persistentes no MySQL; confirme que respostas gravadas, versoes e regras de autorizacao continuam validas.

## Pontos prioritarios

### Interface e Livewire

- Reduza o estado publico serializado. Mantenha no componente somente o necessario para a interacao atual; indexe colecoes com `keyBy`/mapas e evite varreduras aninhadas.
- Carregue turmas, pautas, alunos, alternativas, professores e informacoes complementares em lote, com escopo restrito ao que sera exibido. Use lazy loading, paginacao ou carregamento por expansao quando a tela nao precisa de tudo no primeiro render.
- Evite `wire:model.live` indiscriminado em tabelas grandes. Use debounce, agrupamento e autosave direto somente quando a validacao, autorizacao, CSRF e controle otimista de versao permanecerem intactos.
- Depois de salvar uma resposta, atualize apenas os indicadores derivados necessarios em memoria. Nao force a reconstrução integral da pagina para recalcular percentuais.
- Calcule uma consolidacao de progresso por ciclo/componente em uma passagem, em vez de repetir contagens independentes para cada indicador.

### Banco e persistencia

- Substitua N+1 por `whereIn`, joins/eager loading restrito, mapas em memoria e consultas agrupadas. Cache a mesma leitura durante a requisicao; nao reutilize cache entre usuarios sem isolamento.
- Para autosave, reutilize o contexto validado da requisicao, mantenha transacao e versionamento, e prefira escrita relacional atomica quando o legado JSON nao for necessario. Nao remova verificacoes de escopo.
- Para snapshots, PDFs, CSVs e dashboard, leia ciclos, respostas, informacoes, professores e alternativas em lote. Nunca invoque um leitor ou `obterOuCriar()` individualmente dentro do loop de alunos sem medir e justificar.
- Rode `EXPLAIN` nas consultas reais. Indexe colunas de filtros, joins, unicidade e ordenacao conforme seletividade e ordem de uso; nao crie indices redundantes nem conclua que um indice faltante existe apenas pelo nome da tabela.
- Compare contagem de queries e tempo total antes/depois. O numero nao deve crescer proporcionalmente ao numero de alunos ou pautas exibidos.

### Runtime e infraestrutura

- Confirme se o app usa PHP-FPM ou Octane/Swoole, quantos workers existem, limites de CPU/memoria, conexoes do banco, fila, Redis e proxy. Em uma maquina de 1 vCPU, app e MySQL competem pelo mesmo nucleo: percentuais de containers sao concorrentes e podem somar mais de 100%.
- Nao tente resolver um gargalo de renderizacao apenas alterando Nginx. Cache de arquivos estaticos e buffers ajudam rede, mas nao reduzem hidratacao do Livewire ou custo de query.
- Teste `OCTANE_WORKERS=1` e `2` separadamente em 1 vCPU; mais workers podem aumentar throughput ou apenas causar troca de contexto. Registre o resultado, nao aplique uma regra fixa.
- Use Pulse com amostragem conhecida e trate eventos amostrados como evidencia parcial. Combine-o com k6, logs de acesso e `docker stats`.

## Resultado esperado

Entregue um resumo curto contendo: gargalo comprovado, evidencia, arquivos/consultas alterados, impacto de CPU/latencia/queries, testes executados, persistencia confirmada e riscos ou pendencias. Declare separadamente sucesso funcional e atendimento do SLA; um fluxo pode nao ter erros e ainda falhar no P95.

Para o roteiro detalhado de medicao e interpretacao, leia [references/benchmark-and-observability.md](references/benchmark-and-observability.md).
