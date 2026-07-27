# Fluxos e Permissoes do Painel Admin (operacional)

Este documento lista, de forma simples e objetiva, os principais fluxos do painel `admin` (Filament) e quais permissoes/policies controlam cada acao.

Ele complementa a visao por camadas em `docs/cross-cutting/matriz-permissoes-e-escopo.md`: aqui a pergunta e "qual permissao preciso para fazer X?".

## Como ler

- **Pode**: usuario com a permissao indicada (ou role `Admin`) e com acesso efetivo ao painel (ver "Gate do painel").
- **Nao pode**: usuario sem a permissao indicada; a UI tende a ocultar o item/acao, e a policy/gate deve negar execucao quando chamada.
- **Escopo**: mesmo com permissao, alguns fluxos restringem leitura/escrita por `id_escola`/`setor`/papel (quando implementado).

Fontes principais:

- Gate do painel: `app/Models/User.php`, `app/Providers/Filament/AdminPanelProvider.php`
- Policies (capacidade ampla): `app/Policies/*` e registro em `app/Providers/AppServiceProvider.php`
- Catalogo de permissoes (strings): `app/Console/Commands/CriarPermissoes.php`
- Preset de permissoes por role: `app/Support/SecretarioPermissionPreset.php`

## Gate do painel (antes de qualquer tela)

**Fluxo**: acessar qualquer rota do painel `admin` (ex.: `/admin`)

- Pode: usuario autenticado **e** com acesso ao painel liberado por `User::canAccessPanel()` / `User::canAccessAdminPanel()`.
- Nao pode: usuario autenticado, mas sem acesso liberado -> sistema faz logout e redireciona para login com notificacao.

Observacao:

- A regra de acesso ao painel combina `email_approved`, permissao `Acessar Painel` e/ou role `Acessar Painel`. Ver `app/Models/User.php`.

## Fluxos por recurso (Filament + policy)

Regras abaixo descrevem o "pode fazer" de alto nivel. Filtros de escopo e visibilidade real podem existir fora da policy (service/query/UI).

### Acesso -> Usuarios (Filament `UserResource`, slug `usuarios`)

Policy: `UserPolicy` (registrada em `AppServiceProvider`)

- Listar/Ver: `Listar Usuários`
- Criar: `Criar Usuários`
- Editar: `Editar Usuários`
- Excluir: `Excluir Usuários`

### Acesso -> Niveis de acesso (Filament `RoleResource`, slug `niveis-de-acesso`)

Policy: `RolePolicy` (registrada em `AppServiceProvider`)

- Listar/Ver: `Listar Níveis de Acesso`
- Criar: `Criar Níveis de Acesso`
- Editar: `Editar Níveis de Acesso`
- Excluir: `Excluir Níveis de Acesso`

Observacao:

- A tela tambem permite atribuir permissoes (Spatie) para o nivel; a capacidade de abrir a tela continua sendo a policy acima.

### Acesso -> Permissoes de execucao (Spatie `Permission`)

Policy: `PermissionPolicy` (registrada em `AppServiceProvider`)

- Listar/Ver: `Listar Permissões de Execução`
- Criar: `Criar Permissões de Execução`
- Editar: `Editar Permissões de Execução`
- Excluir: `Excluir Permissões de Execução`

Observacao:

- Nao foi encontrado `PermissionResource` no Filament. Validar se a gestao de permissoes ocorre apenas via `RoleResource` ou em outra tela.

### Acesso -> Dominios permitidos (Filament `DominioEmailResource`, slug `dominio-emails`)

Policy: `DominioEmailPolicy` (registrada em `AppServiceProvider`)

- Listar/Ver: `Listar Dominios de Email`
- Criar: `Criar Dominios de Email`
- Editar: `Editar Dominios de Email`
- Excluir: `Excluir Dominios de Email`

Observacao:

- A tela de bulk delete esta visivel somente para role `Admin` (checagem na propria UI).

### Estrutura -> Escolas

Policy: `EscolaPolicy` (registrada em `AppServiceProvider`)

- Listar/Ver: `Listar Escolas`
- Criar: `Criar Escolas`
- Editar: `Editar Escolas`
- Excluir: `Excluir Escolas`

### Estrutura -> Series

Policy: `SeriePolicy` (registrada em `AppServiceProvider`)

- Listar/Ver: `Listar Séries`
- Criar: `Criar Séries`
- Editar: `Editar Séries`
- Excluir: `Excluir Séries`

### Estrutura -> Turmas

Policy: `TurmaPolicy` (registrada em `AppServiceProvider`)

- Listar/Ver: `Listar Turmas`
- Criar: `Criar Turmas`
- Editar: `Editar Turmas`
- Excluir: `Excluir Turmas`

### Estrutura -> Alunos

Policy: `AlunoPolicy` (registrada em `AppServiceProvider`)

- Listar: `Listar Alunos`
- Ver: `Listar Alunos` + **escopo** (ver abaixo)
- Criar: `Criar Alunos`
- Editar: `Editar Alunos` + **escopo**
- Excluir: `Excluir Alunos` + **escopo**

Escopo adicional (na policy):

- Role `Admin`: acesso total.
- Se `User::ehProfessor()`: aluno precisa pertencer a turma com componente vinculado a um dos professores do usuario.
- Se `user.id_escola` estiver preenchido: aluno precisa pertencer a uma turma da mesma escola.

### Estrutura -> Professores

Policy: `ProfessorPolicy` (registrada em `AppServiceProvider`)

- Listar/Ver: `Listar Professores`
- Criar: `Criar Professores`
- Editar: `Editar Professores`
- Excluir: `Excluir Professores`

### Manutencao -> Pedidos

Policy: `PedidoPolicy` (registrada em `AppServiceProvider`)

- Listar/Ver: `Listar Pedidos`
- Criar: `Criar Pedidos`
- Editar: `Editar Pedidos`
- Excluir: `Excluir Pedidos`

Observacao:

- `docs/cross-cutting/matriz-permissoes-e-escopo.md` indica que `PedidoService` pode restringir visibilidade real por `id_escola` e `setor` alem da policy.

### Manutencao -> Arquivos de pedido (download/visibilidade)

Policy: `PedidoArquivoPolicy` (registrada em `AppServiceProvider`)

- Download de arquivo: permissao `Exportar Arquivos Pedido` (ability `download` na policy).

Observacao:

- Este fluxo normalmente aparece como botao/acao dentro do pedido (nao como menu proprio).

### Manutencao -> Setores

Policy: `SetorPolicy` (registrada em `AppServiceProvider`)

- Listar/Ver: `Listar Setores`
- Criar: `Criar Setores`
- Editar: `Editar Setores`
- Excluir: `Excluir Setores`
- Excluir em massa: `Excluir Setores em Massa`

### Pedagogico -> Alternativas

Policy: `AlternativaPolicy` (registrada em `AppServiceProvider`)

- Listar/Ver: `Listar Alternativas`
- Criar: `Criar Alternativas`
- Editar: `Editar Alternativas`
- Excluir: `Excluir Alternativas`

### Pedagogico -> Avaliacoes

Policy: `AvaliacaoPolicy` (registrada em `AppServiceProvider`)

- Listar/Ver: `Listar Avaliações`
- Criar: `Criar Avaliações`
- Editar: `Editar Avaliações`
- Excluir: `Excluir Avaliações`

### Pedagogico -> Pautas

Policy: `PautaPolicy` (registrada em `AppServiceProvider`)

- Listar/Ver: `Listar Pautas`
- Criar: `Criar Pautas`
- Editar: `Editar Pautas`
- Excluir: `Excluir Pautas`

### Início -> Eventos da agenda

Policy: `EventoCalendarioPolicy` (registrada em `AppServiceProvider`)

- Listar próprios eventos: `Listar Eventos: Meus Eventos`
- Listar solicitações com transporte: `Listar Eventos: Transporte`
- Criar qualquer evento: `Criar Eventos`
- Criar somente solicitação com transporte: `Criar Eventos: Transporte`
- Publicar, desativar e rejeitar transporte: permissões específicas com o sufixo `: Transporte`

Observações:

- Quem possui somente `Criar Eventos: Transporte` precisa selecionar escolas e solicitar transporte; o serviço rejeita eventos comuns.
- O nível `Transporte` analisa, publica, desativa e rejeita solicitações de transporte sem acesso aos cadastros pedagógicos.
- O nível `Assessoria Pedagógica` possui leitura pedagógica de rede e cria solicitações de transporte, mas não publica nem aloca veículos.

### Pedagógico -> Reserva de veículos

Policies: `ReservaVeiculoPolicy` e `VeiculoTransportePolicy` (registradas em `AppServiceProvider`)

- Listar reservas e acessar o filtro `Veículos` da agenda: `Listar Reservas de Veículos`
- Criar reservas, inclusive para vários dias: `Criar Reservas de Veículos`
- Editar reservas ativas: `Editar Reservas de Veículos`
- Cancelar reservas e liberar o horário: `Cancelar Reservas de Veículos`
- Cadastrar, editar, ativar e desativar a frota: `Gerenciar Frota de Veículos`

Observações:

- O preset `Assessoria Pedagógica` recebe essas permissões.
- Reservas destinadas a uma escola ou CMEI aparecem na agenda da unidade.
- Reservas com outro local aparecem para o solicitante e no escopo de rede.
- O backend impede duas reservas ativas sobrepostas para o mesmo veículo.

### Manutencao -> Tipos de manutencao

Policy: `TipoManutencaoPolicy` (registrada em `AppServiceProvider`)

- Listar/Ver: `Listar Tipo Manutenção`
- Criar: `Criar Tipo Manutenção`
- Editar: `Editar Tipo Manutenção`
- Excluir: `Excluir Tipo Manutenção`

### Cadastros -> Servidores

Policy: `ServidorPolicy` (registrada em `AppServiceProvider`)

- Listar/Ver: `Listar Servidores`
- Criar: `Criar Servidores`
- Editar: `Editar Servidores`
- Excluir: `Excluir Servidores`
- Gerenciar vínculos funcionais: `Gerenciar Funções de Servidores`

Escopo adicional:

- `ServidorService::aplicarEscopoVisibilidade()` restringe por escola ou setor quando o usuário não possui escopo global.

### Estrutura -> Componente curricular

Policy: `ComponenteCurricularPolicy` (registrada em `AppServiceProvider`)

- Listar/Ver: `Listar Componente Curricular`
- Criar: `Criar Componente Curricular`
- Editar: `Editar Componente Curricular`
- Excluir: `Excluir Componente Curricular`

### Cadastros -> Funcoes administrativas

Policy: `FuncaoAdministrativaPolicy` (registrada em `AppServiceProvider`)

- Listar/Ver: permissao por fragmento `listar funcoes administrativas`
- Criar: permissao por fragmento `criar funcoes administrativas`
- Editar: permissao por fragmento `editar funcoes administrativas`
- Excluir: permissao por fragmento `excluir funcoes administrativas`
- Excluir em massa: permissao por fragmento `excluir funcoes administrativas em massa`

### Estoque/Merenda -> Itens

Policy: `ItemPolicy` (registrada em `AppServiceProvider`)

- Listar/Ver/Criar/Editar: permissao por fragmento `listar gestao de estoque` (ver `User::hasPermissionLike()`).
- Excluir: permissao por fragmento `excluir itens`
- Excluir em massa: permissao por fragmento `excluir itens em massa`

Observacao:

- Este modelo usa permissao "por fragmento" e nao uma string unica. Isso tolera variacoes de acentuacao/cadastro, mas aumenta risco de falso positivo.

## Itens com policy existente mas registro pendente

Os arquivos abaixo existem, mas nao aparecem registrados em `app/Providers/AppServiceProvider.php` neste momento. Isso pode tornar o menu inacessivel (Gate nega) ou tornar a regra divergente do esperado.

- `app/Policies/ContratoPolicy.php` (modelo `Contrato`)
- `app/Policies/PedidoMerendaPolicy.php` (modelo `PedidoMerenda`)

Antes de assumir o comportamento, validar:

- se esses recursos usam `canAccess()` custom no Filament, ou
- se existe outro ponto de registro de policy no projeto.

## Manutencao deste documento

Atualize este arquivo sempre que:

- uma permissao nova for adicionada em `app/Console/Commands/CriarPermissoes.php`
- uma policy for criada/alterada em `app/Policies/*`
- uma policy for registrada/removida em `app/Providers/AppServiceProvider.php`
- uma tela Filament nova for adicionada em `app/Filament/Admin/*` com regra de acesso propria (`canAccess()`, `shouldRegisterNavigation`, gates custom)

Checklist rapido:

1. Registrar a permissao (string) e o "verbo" (Listar/Criar/Editar/Excluir/Exportar/Visualizar).
2. Indicar a policy/gate que realmente bloqueia a execucao.
3. Indicar escopo contextual (escola/setor/papel) quando existir.
4. Registrar qualquer excecao de UI (acao visivel apenas para `Admin`, permissao por fragmento etc).
