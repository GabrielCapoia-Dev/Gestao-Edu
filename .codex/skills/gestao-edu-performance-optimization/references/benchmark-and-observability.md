# Benchmark e observabilidade

Use esta referencia quando o pedido incluir carga concorrente, comparacao antes/depois ou investigacao de saturacao.

## Preparacao

- Identifique URL, branch/commit, container do app, banco, Redis, fila, Pulse e proxy efetivamente usados.
- Registre o dataset, avaliacao/turma, numero de usuarios distintos, credenciais de teste, estado inicial das respostas e se os dados devem permanecer persistentes.
- Gere fixtures no banco do Hub. Cada usuario deve ter um vinculo e um conjunto de alunos proprios ou claramente isolado; nao reutilize contas para simular usuarios distintos.
- Use uma rampa gradual. Se houver limitador de login ou proxy, aumente a duracao da rampa; nao o desative.

## Metricas minimas

| Area | Medir |
|---|---|
| HTTP/k6 | requests, iteracoes, checks, erros, P50/P95/P99 e throughput |
| Fluxo | login, abertura, atualizacao Livewire, autosave, conflitos e sucesso |
| PHP | CPU, memoria, workers, fila de requisicoes, tempo de aplicacao e tamanho do payload |
| MySQL | CPU, memoria, queries por requisicao, tempo total, slow queries, locks e deadlocks |
| Redis/fila | CPU, memoria, hits/misses, jobs pendentes, falhas e tempo de espera |
| Pulse/Nginx | rota, metodo, duracao, upstream, excecoes e requests lentos |

P95 de 1,2 s significa que 95% das operacoes terminaram em ate 1,2 s; nao significa que todas terminaram nesse tempo. Informe tambem P99 quando houver cauda longa.

## Degraus recomendados

Comece com uma referencia funcional e aumente gradualmente, por exemplo 20, 30, 35, 40 e 80 usuarios. Em cada degrau:

1. Recarregue workers sem reiniciar componentes fora do escopo.
2. Execute o mesmo fluxo organico e mantenha a mesma frequencia de autosave.
3. Observe o momento em que P95, erros, conflitos ou CPU mudam de comportamento.
4. Ao terminar, valide que o numero esperado de respostas e versoes foi persistido.
5. Remova apenas arquivos temporarios; preserve dados persistentes quando isso fizer parte do experimento.

## Interpretacao

- CPU do Docker e percentual por container em relacao ao host; em 1 vCPU, app 80% e banco 30% indicam disputa concorrente, nao uma CPU global de exatamente 110%.
- App alto com banco moderado aponta renderizacao, hidratacao, serializacao, autenticacao ou trabalho PHP. Banco alto com app aguardando aponta query, indice, lock ou conexao.
- Memoria estavel durante a carga nao prova ausencia de custo; observe latencia, GC, workers e crescimento ao longo de varias requisicoes.
- Um request lento no Pulse pode ser amostra. Compare com a distribuicao completa do k6 e com logs de acesso.
- Falha de threshold nao e igual a erro funcional. Reporte as duas dimensoes separadamente.

## Evidencia de banco

Para cada query candidata, preserve a consulta real e verifique:

- tabelas e joins usados;
- filtros de tenant, usuario, avaliacao, turma e status;
- ordem dos campos no indice;
- estimativa e quantidade de linhas examinadas;
- uso de filesort, temporary table, full scan ou subquery correlacionada;
- existencia de indice equivalente antes de criar outro.

Nao use um indice novo para esconder um loop PHP que poderia ser eliminado com uma leitura em lote.
