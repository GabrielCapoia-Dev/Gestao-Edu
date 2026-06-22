# Design System CRM -> Referencia para o Gestao-Edu

## Resumo Executivo

### O que foi analisado

A referencia visual deste documento foi extraida por leitura somente do CRM remoto em `/root/Projeto/CRM-AlterHub-Digital`, com foco nos arquivos que realmente definem tema, linguagem visual, componentes e padroes de pagina:

- `/root/Projeto/CRM-AlterHub-Digital/app/Providers/Filament/PainelPanelProvider.php`
- `/root/Projeto/CRM-AlterHub-Digital/resources/css/app.css`
- `/root/Projeto/CRM-AlterHub-Digital/resources/views/filament/pages/home.blade.php`
- `/root/Projeto/CRM-AlterHub-Digital/resources/views/filament/partials/crm-list-table-styles.blade.php`
- `/root/Projeto/CRM-AlterHub-Digital/resources/views/filament/partials/stock-theme-styles.blade.php`
- `/root/Projeto/CRM-AlterHub-Digital/resources/views/filament/resources/oportunidades/pages/kanban-oportunidades.blade.php`
- `/root/Projeto/CRM-AlterHub-Digital/app/Filament/Resources/Users/Tables/UsersTable.php`
- `/root/Projeto/CRM-AlterHub-Digital/app/Filament/Resources/Users/Schemas/UserForm.php`
- `/root/Projeto/CRM-AlterHub-Digital/resources/views/filament/resources/produtos/pages/manage-produtos.blade.php`

O contraponto do Gestao-Edu foi levantado no projeto local, com apoio de leitura do espelho remoto quando util para confirmar o estado real do servidor:

- [app/Providers/Filament/AdminPanelProvider.php](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\app\Providers\Filament\AdminPanelProvider.php)
- [resources/css/app.css](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\resources\css\app.css)
- [resources/views/filament/pages/dashboard.blade.php](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\resources\views\filament\pages\dashboard.blade.php)
- [resources/views/filament/pages/partials/access-management-styles.blade.php](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\resources\views\filament\pages\partials\access-management-styles.blade.php)
- [resources/views/filament/admin/pages/partials/page-header.blade.php](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\resources\views\filament\admin\pages\partials\page-header.blade.php)
- [app/Filament/Admin/Resources/Pedidos/Pages/ListPedidos.php](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\app\Filament\Admin\Resources\Pedidos\Pages\ListPedidos.php)
- [app/Filament/Admin/Resources/Users/Tables/UsersTable.php](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\app\Filament\Admin\Resources\Users\Tables\UsersTable.php)
- [app/Filament/Admin/Resources/Users/Schemas/UserForm.php](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\app\Filament\Admin\Resources\Users\Schemas\UserForm.php)
- [resources/views/filament/admin/resources/roles/pages/manage-roles.blade.php](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\resources\views\filament\admin\resources\roles\pages\manage-roles.blade.php)

### Padrao visual predominante do CRM

O CRM trabalha com um design system mais centralizado do que o Gestao-Edu atual. Os sinais mais fortes sao:

- tema Filament com tokens completos no provider, incluindo `primary`, `gray`, `danger`, `success`, `warning` e `info`;
- definicao explicita da fonte principal do painel com `Plus Jakarta Sans`;
- uso recorrente de classes semanticas por funcao visual, especialmente `crm-*` nas superficies operacionais;
- cards, tabelas e paineis com relevo leve, bordas suaves e contraste controlado;
- filtros, badges, pills e estados vazios com tratamento visual consistente;
- separacao melhor entre logica da tela e acabamento visual, com CSS concentrado em `resources/css/app.css` e partials dedicadas.

### Como isso orienta o Gestao-Edu

O Gestao-Edu ja compartilha parte da paleta institucional azul/cinza e tambem usa Filament 5 com Tailwind 4, mas a camada visual esta mais espalhada e mais dependente de CSS por tela, `style=""` inline e variacoes locais de linguagem. A direcao recomendada nao e copiar o CRM literalmente. A direcao correta e:

- consolidar tokens e convencoes visuais globais;
- criar componentes e partials reutilizaveis;
- reduzir estilo inline em views e `HtmlString`;
- preservar integralmente permissoes, filtros, queries, validacoes, notificacoes, exports e fluxo operacional.

## Inventario do Design System do CRM

### Cores

No `PainelPanelProvider`, o CRM define um conjunto mais completo de cores do que o Gestao-Edu:

| Grupo | Referencia principal | Papel observado |
| --- | --- | --- |
| `primary` | `#17368D` como brand, `#3A6DD6` em tons medios | identidade principal, links, foco, acoes prioritarias |
| `gray` | `#F4F5F9` ate `#0F1729` | fundo, superficie, divisorias, tipografia auxiliar |
| `danger` | `#E53E6B` | erro, acao destrutiva, estados criticos |
| `success` | `#00C97B` | confirmacao, ganhos, estados positivos |
| `warning` | `#F5A623` | alerta, atencao, acao secundaria com destaque |
| `info` | `#3A6DD6` | informacao contextual, alinhada ao primary medio |

No Gestao-Edu, o provider define apenas `primary` e `gray`, embora varias telas usem cores adicionais manualmente em CSS local ou inline.

### Tipografia

O CRM explicita `->font('Plus Jakarta Sans')` no provider. Nas telas, a hierarquia observada usa:

- fonte principal sans para titulos, cards e formularios;
- eyebrow em caixa alta com tracking mais aberto;
- uso pontual de `Courier New` em marcadores pequenos de status.

No Gestao-Edu, `resources/css/app.css` define `Instrument Sans` via `@theme`, mas o provider nao fixa essa fonte no painel. Isso cria margem para inconsistencias entre o que o CSS base declara e o que o painel efetivamente renderiza.

### Espacamentos

Os padroes mais recorrentes no CRM ficam entre:

- `0.35rem` a `0.9rem` em espacamento interno de texto;
- `1rem` a `1.5rem` em cards, paineis e blocos de formulario;
- grids com `0.75rem` a `1rem` para filtros, metricas e cards.

O resultado e uma interface compacta, mas sem aspecto apertado.

### Bordas e radius

O CRM usa radius de forma previsivel:

- `0.5rem` a `0.9rem` em linhas de tabela, pills e blocos menores;
- `1rem` a `1.25rem` em cards, panels e sections;
- `1.5rem` em hero e superficies principais.

No Gestao-Edu, esse padrao existe parcialmente, mas com mais oscilacao entre telas e com varios ajustes diretos em CSS isolado.

### Sombras

O CRM privilegia sombras leves:

- sombra curta para list records;
- sombra media para cards e modais;
- sombra mais alta apenas em overlays e janelas de modal.

Ha sensacao de profundidade, sem efeito pesado.

### Layouts

Os layouts do CRM se repetem em estruturas claras:

- `hero` institucional no topo da pagina;
- barra de filtros destacada e separada do conteudo;
- cards ou tabelas com superficie branca e borda sutil;
- dashboards com metricas em blocos reutilizaveis;
- paginas de operacao com resumo, detalhes e acoes visiveis.

### Componentes

Os componentes de maior relevancia visual no CRM sao:

- hero de pagina;
- quick-access cards da home;
- list records customizados em card;
- filtros com pills e selects consistentes;
- badges de status;
- paineis de resumo financeiro;
- modal/slideover com acabamento reutilizavel;
- kanban com loading overlay e comutador lista/kanban.

### Padroes de pagina

- Home com linguagem institucional forte e cartoes de acesso rapido.
- Resources com hero tecnico, descricao curta e conteudo operacional abaixo.
- Listagens com densidade alta, mas leitura melhorada por cards, grids e metadados.
- Paginas operacionais com alternancia de visualizacao, filtros sempre visiveis e resumo agregado.

### Padroes de interacao

- hover discreto com elevacao leve;
- botoes e pills indicando estado ativo sem ruido visual;
- overlays de carregamento quando a operacao pode bloquear interacao;
- filtros persistentes e legiveis;
- acoes de mudanca de visualizacao tratadas como parte da barra superior.

## Mapa de Componentes

| Componente do CRM | Onde aparece no CRM | Equivalente atual no Gestao-Edu | Adaptacao recomendada |
| --- | --- | --- | --- |
| Hero institucional | `resources/views/filament/pages/home.blade.php` | `resources/views/filament/pages/dashboard.blade.php` | Manter a ideia de hero, mas padronizar a estrutura em partial reutilizavel do painel |
| Card de acesso rapido | Home do CRM | Cards da home do Gestao-Edu | Unificar espacamento, hover, tipografia e escala de icones |
| List record em card | `crm-list-table-styles.blade.php` + `UsersTable.php` | `ListPedidos.php` usa tabs e estilos inline; `UsersTable.php` ainda e mais padrao | Criar estilo de listagem reutilizavel para superficies que pedem leitura em card |
| Hero de resource | `stock-theme-styles.blade.php` + views de produtos/insumos | `page-header.blade.php` + blocos especificos como `am-*` | Criar um header base de resource e abandonar variações desconexas |
| Barra de filtros | Kanban de oportunidades | filtros acima do conteudo em users/pedidos/feedbacks | Definir convencao unica para filtros, busca, contadores e limpar filtros |
| Kanban / troca de visualizacao | `kanban-oportunidades.blade.php` | nao ha equivalente direto no Gestao-Edu analisado | Reaproveitar apenas conceitos de toolbar, pills, resumo e overlay, nunca a logica comercial |
| Modal com acabamento padrao | `manage-produtos.blade.php` | varios modais e slideovers espalhados | Extrair classes comuns de modal visual e manter schemas/logica intactos |
| Painel de metricas | `stock-theme-styles.blade.php` | `access-management-styles.blade.php` e dashboards diversos | Padronizar cards de resumo e estados vazios |

## Comparativo CRM x Gestao-Edu

### O que ja converge

- Ambos usam Laravel 12, Filament 5, Tailwind 4 e Vite.
- Ambos trabalham com base azul/cinza parecida.
- Ambos usam cards de acesso rapido e superficies com gradiente leve em partes do painel.
- Ambos mostram tendencia a criar experiences mais ricas que o Filament default em paginas importantes.

### Inconsistencias visuais do Gestao-Edu

- `resources/css/app.css` quase nao carrega componentes do sistema. A maior parte da identidade visual vive espalhada em views e partials.
- O dashboard principal concentra um bloco grande de CSS inline no proprio Blade.
- `ListPedidos.php` gera badges e titulos com `HtmlString` e `style=""` inline, o que dificulta reuso e governanca.
- Ha multiplas familias de prefixo e linguagem visual na mesma base: `hero/nav-card`, `am-*`, `gi-*`, `pedido-*`, `mobile/*`, `public/*`, `relatorios/*`.
- O provider do Gestao-Edu define apenas `primary` e `gray`, enquanto o CRM centraliza tambem estados auxiliares.
- Algumas telas do Gestao-Edu ja sao visivelmente tratadas, como a de niveis de acesso, mas outras ainda dependem do visual default do Filament.

### Componentes que pedem padronizacao

- headers de pagina e heroes de resource;
- filtros e barra de busca;
- badges de status e contadores de tabs;
- cards de resumo;
- empty states;
- listagens que hoje misturam tabela default, cards customizados e HTML inline;
- acabamento de modais e slideovers.

### Telas que fogem mais do padrao

- [resources/views/filament/pages/dashboard.blade.php](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\resources\views\filament\pages\dashboard.blade.php)
- [app/Filament/Admin/Resources/Pedidos/Pages/ListPedidos.php](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\app\Filament\Admin\Resources\Pedidos\Pages\ListPedidos.php)
- [resources/views/filament/pages/partials/access-management-styles.blade.php](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\resources\views\filament\pages\partials\access-management-styles.blade.php)
- [resources/views/mobile/*](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\resources\views\mobile)
- [resources/views/public/*](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\resources\views\public)

### Estilos duplicados ou improvisados

- titulos e badges de status inline em PHP;
- heroes repetidos em Blade com pequenas variacoes;
- cards de resumo recriados por tela;
- modal styling manual por contexto;
- classes locais que resolvem o mesmo problema visual com nomes diferentes.

### Oportunidades de reaproveitamento conceitual

- usar o modelo do CRM para centralizar tokens e classes em `resources/css/app.css`;
- usar partials de resource hero no lugar de varios headers customizados;
- usar o modelo de list record do CRM como base conceitual para listagens densas do Gestao-Edu;
- usar o modelo de filtro/toolbar do CRM para paginas com tabs, busca e contadores.

### Pontos que devem mudar apenas em camada visual

- provider de tema, fonte e paleta;
- partials de header, hero, cards e empty states;
- CSS base do painel;
- view wrappers de paginas Filament;
- classes de modais, badges e listagens.

### Pontos com risco de impacto em regra de negocio

- mover a apresentacao dos status de `ListPedidos.php` para classes CSS sem perder a logica de cores e ordenacao;
- alterar views de filtros sem tocar em `modifyQueryUsing`, services ou policies;
- mexer em users/roles mantendo intactas as permissoes e o comportamento do `UserService`;
- unificar visuais de telas mobile, publicas e relatorios sem misturar contratos de exibicao com regra de negocio.

## Padroes Recomendados para o Gestao-Edu

### Recomendacoes de UI

- Definir um prefixo visual global novo para componentes compartilhados, por exemplo `ge-ui-*`.
- Reservar prefixos de modulo apenas para casos em que o componente realmente for exclusivo do dominio.
- Centralizar cores de estado no provider, nao apenas `primary` e `gray`.
- Fixar a fonte do painel de forma explicita no provider.
- Padronizar hero, page header, summary cards, list cards, badges e empty states como blocos reutilizaveis.

### Recomendacoes de UX

- Manter filtros sempre visiveis e semanticamente rotulados.
- Usar uma unica convencao para "buscar", "limpar filtros", "tabs com contagem" e "acoes principais".
- Tornar estados vazios mais claros e menos improvisados.
- Evitar microvariacoes de hover, radius e espacamento entre telas equivalentes.

### Recomendacoes para Filament

- Usar `renderHook` para estilos globais de painel e nao para resolver sintomas isolados em excesso.
- Extrair headers e wrappers de pagina para views parciais reutilizaveis.
- Preferir classes CSS e partials a `HtmlString` com blocos de estilo inline.
- Aplicar o design system primeiro nas paginas mais centrais do painel, depois nas bordas do sistema.

### Recomendacoes para Blade, CSS e Tailwind

- Consolidar os componentes reutilizaveis em `resources/css/app.css` ou partials de estilo claramente nomeadas.
- Reduzir CSS inline em views Blade.
- Nomear classes por funcao, nao por pagina isolada, quando o componente tiver vida util compartilhada.
- Nao espalhar novos gradientes e novas paletas por modulo sem justificativa institucional.

### Convencoes de nomenclatura

- `ge-ui-hero-*` para hero/header padrao.
- `ge-ui-card-*` para cards de resumo e cards de acesso.
- `ge-ui-list-*` para listagens customizadas.
- `ge-ui-filter-*` para filtros e toolbars.
- `ge-ui-status-*` para badges e estados.
- `ge-ui-empty-*` para estados vazios.

## Plano de Implementacao Futura

### Fase 1 - Tokens e tema base

- ampliar `AdminPanelProvider` com estados auxiliares de cor;
- fixar a fonte do painel;
- criar tokens e classes base no CSS do painel;
- padronizar logo, superficies e contraste geral.

### Fase 2 - Componentes globais

- criar partial/header base para resources e pages;
- criar summary cards, empty state e toolbar de filtros reutilizaveis;
- criar badges de status e tabs com classes, substituindo estilo inline.

### Fase 3 - Telas criticas

- dashboard principal;
- listagem de pedidos;
- users/roles/permissoes;
- telas administrativas com maior uso diario e maior visibilidade.

### Fase 4 - Refinamentos

- convergir mobile/public quando fizer sentido;
- revisar relatorios e views especiais;
- eliminar duplicacoes visuais residuais;
- revisar novas telas para garantir aderencia ao padrao.

## Skills e Agents Criados

### Skills

- `.codex/skills/gestao-edu-ui-screen-analysis/SKILL.md`
- `.codex/skills/gestao-edu-design-system-application/SKILL.md`
- `.codex/skills/gestao-edu-filament-visual-review/SKILL.md`
- `.codex/skills/gestao-edu-ui-copy-review/SKILL.md`
- `.codex/skills/gestao-edu-visual-safety-guard/SKILL.md`

### Agents

- `.agents/gestao-edu-visual-auditor/openai.yaml`
- `.agents/gestao-edu-design-system-applier/openai.yaml`
- `.agents/gestao-edu-screen-consistency-reviewer/openai.yaml`
- `.agents/gestao-edu-ui-governance-manager/openai.yaml`
- `.agents/gestao-edu-visual-deviation-detector/openai.yaml`

### Quando usar

- `gestao-edu-ui-screen-analysis`: antes de qualquer ajuste visual em uma tela.
- `gestao-edu-design-system-application`: quando a tarefa for aplicar o padrao visual definido aqui.
- `gestao-edu-filament-visual-review`: quando a tarefa envolver forms, tables, tabs, actions, badges e pages do Filament.
- `gestao-edu-ui-copy-review`: quando a tarefa envolver texto visivel em contexto visual.
- `gestao-edu-visual-safety-guard`: quando houver risco de uma mudanca visual encostar em regra de negocio.

## Lista de Arquivos Relevantes Analisados

### CRM remoto

- `/root/Projeto/CRM-AlterHub-Digital/app/Providers/Filament/PainelPanelProvider.php`
  - fonte, paleta, hooks, plugin de auth e padrao central do painel.
- `/root/Projeto/CRM-AlterHub-Digital/resources/css/app.css`
  - classes base e componentes reutilizaveis do design system.
- `/root/Projeto/CRM-AlterHub-Digital/resources/views/filament/pages/home.blade.php`
  - linguagem institucional da home e dos cards de acesso.
- `/root/Projeto/CRM-AlterHub-Digital/resources/views/filament/partials/crm-list-table-styles.blade.php`
  - list record customizado como card.
- `/root/Projeto/CRM-AlterHub-Digital/resources/views/filament/partials/stock-theme-styles.blade.php`
  - hero de resource, summary cards e paineis operacionais.
- `/root/Projeto/CRM-AlterHub-Digital/resources/views/filament/resources/oportunidades/pages/kanban-oportunidades.blade.php`
  - toolbar, filtros, pills, overlay e visualizacao operacional rica.
- `/root/Projeto/CRM-AlterHub-Digital/app/Filament/Resources/Users/Tables/UsersTable.php`
  - aplicacao do design system em listagem administrativa.
- `/root/Projeto/CRM-AlterHub-Digital/app/Filament/Resources/Users/Schemas/UserForm.php`
  - padrao de form, helper texts e sections.
- `/root/Projeto/CRM-AlterHub-Digital/resources/views/filament/resources/produtos/pages/manage-produtos.blade.php`
  - acabamento de modais e wrapper de resource.

### Gestao-Edu local e espelho remoto

- [app/Providers/Filament/AdminPanelProvider.php](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\app\Providers\Filament\AdminPanelProvider.php)
  - tema atual, hooks, logo, menu do usuario e pontos de extensao visual.
- [resources/css/app.css](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\resources\css\app.css)
  - mostra que o CSS base do sistema ainda nao carrega o design system real.
- [resources/views/filament/pages/dashboard.blade.php](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\resources\views\filament\pages\dashboard.blade.php)
  - home principal com muito estilo inline local.
- [resources/views/filament/pages/partials/access-management-styles.blade.php](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\resources\views\filament\pages\partials\access-management-styles.blade.php)
  - exemplo de tela rica, mas especifica demais para virar base global sem refino.
- [resources/views/filament/admin/pages/partials/page-header.blade.php](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\resources\views\filament\admin\pages\partials\page-header.blade.php)
  - ponto candidato para virar header padrao do painel.
- [app/Filament/Admin/Resources/Pedidos/Pages/ListPedidos.php](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\app\Filament\Admin\Resources\Pedidos\Pages\ListPedidos.php)
  - exemplo claro de visual acoplado a `HtmlString` e estilo inline.
- [app/Filament/Admin/Resources/Users/Tables/UsersTable.php](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\app\Filament\Admin\Resources\Users\Tables\UsersTable.php)
  - listagem administrativa ainda mais proxima do Filament default.
- [app/Filament/Admin/Resources/Users/Schemas/UserForm.php](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\app\Filament\Admin\Resources\Users\Schemas\UserForm.php)
  - schema mais complexo, com maior risco de tocar regra se houver refactor amplo.
- [resources/views/filament/admin/resources/roles/pages/manage-roles.blade.php](C:\Users\gabriel.capoia\Documents\2026\Projetos Laravel\Gestao-Edu\resources\views\filament\admin\resources\roles\pages\manage-roles.blade.php)
  - tela com boa intencao visual, util como base interna do que ja foi customizado.

### Observacao sobre guias internos do CRM

Na estrutura lida do CRM nao apareceu `.codex` nem `.agents`. A pasta `docs` existe, mas esta etapa nao identificou nela um guia visual canonico equivalente ao que o Gestao-Edu precisa para governanca continua. Por isso esta referencia operacional foi criada no proprio Gestao-Edu.

## Riscos e Cuidados

### O que nao deve ser copiado diretamente

- logica comercial do kanban de oportunidades;
- nomes de classes `crm-*` e `ub-*` como se fossem nomenclatura definitiva do Gestao-Edu;
- textos, labels e estruturas especificas do dominio do CRM;
- qualquer simplificacao de superficie que remova informacao critica do Gestao-Edu.

### O que pode quebrar comportamento no Gestao-Edu

- trocar wrappers de pages e partials sem preservar actions, routes e hooks do Filament;
- refatorar `ListPedidos.php` mexendo em filtros, tabs e query junto com o visual;
- unificar tabelas e forms sem manter os contratos de `UserService`, `PedidoService` e policies;
- alterar views de relatorio, mobile e publico como se fossem apenas clones do painel admin.

### O que precisa ser validado antes de implementar

- se o novo token de cor cobre todos os estados de pedidos, feedbacks, inventario e avaliacoes;
- se os headers reutilizaveis nao quebram actions, breadcrumbs ou tabs;
- se badges de status continuam refletindo as cores reais do dominio;
- se a consolidacao de CSS nao reduz legibilidade em telas densas;
- se as telas mobile e publicas vao aderir ao mesmo design system ou manter subtema controlado.

## Checklist Final de Padronizacao

- Confirmar a tela canonica antes de alterar.
- Ler provider, page/resource, partials e CSS envolvidos.
- Separar o que e acabamento visual do que e regra.
- Centralizar token ou componente antes de repetir CSS em outra tela.
- Evitar `style=""` inline novo.
- Preferir classes reutilizaveis a `HtmlString` estilizado.
- Nao alterar services, models, policies, migrations ou queries sem autorizacao explicita.
- Validar diff final para garantir escopo visual.
- Revisar texto visivel e labels apos o ajuste.
- Registrar no proprio PR ou diff quais componentes passaram a seguir o design system.

## Estado Aplicado no Gestao-Edu

### Onda 1 - Admin + shared

- `public/css/geral.css` passou a concentrar as primitives compartilhadas do admin para hero, page header, actions, fields, empty states, status pills, tabs e dashboard cards.
- `app/Providers/Filament/AdminPanelProvider.php` recebeu a fonte explicita do painel e a paleta auxiliar de estados (`success`, `warning`, `danger`, `info`) para reduzir cor manual espalhada.
- `resources/views/filament/admin/pages/partials/page-header.blade.php` virou o header canonico de resources e pages com estrutura unica sobre `gi-*`.
- `resources/views/filament/pages/partials/access-management-styles.blade.php` ficou restrito ao acabamento especifico do modulo de acesso, reaproveitando a base compartilhada.
- `app/Filament/Admin/Resources/Pedidos/Pages/ListPedidos.php` trocou o estilo inline de titulo e tabs por classes reutilizaveis e partial visual dedicado.

### Extensoes mantidas por modulo

- `am-*` permanece como extensao visual do dominio de acesso, sem virar um segundo design system.
- `pedido-*`, `av-*`, `pm-*`, `nc-*`, `gm-*`, `fb-*` e `rel-*` continuam como wrappers de modulo onde ainda existe composicao especifica, mas agora sobre base compartilhada maior.

### Fase seguinte

- `resources/views/public/*` e `resources/views/mobile/*` ficaram fora desta onda e devem ser alinhados depois, mantendo por enquanto seus assets separados (`public.layout` e `public/css/mobile-app.css`).
