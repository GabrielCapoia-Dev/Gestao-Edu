# Testes e Debug

## Objetivo

Registrar o estado atual de validacao automatizada e os caminhos mais uteis para reproduzir problemas localmente.

## Onde isso vive no codigo

- `tests`
- `phpunit.xml`
- `docker-compose.yml`
- `bootstrap/app.php`
- `app/Console/Commands`
- `app/Livewire/TopbarNotifications.php`

## Estado atual de testes

- A suite de testes existe, mas esta praticamente no estado padrao do Laravel.
- Hoje nao ha cobertura automatizada relevante para manutencao, merenda, autenticacao ou permissao.
- O risco atual de regressao e alto e a validacao depende muito de leitura de codigo e teste manual.

## Como debugar o projeto

- Subir containers pelo `docker-compose.yml`.
- Garantir banco MySQL saudavel antes da aplicacao e do container `cron`.
- Verificar scheduler no container `cron`, pois notificacoes dependem dele.
- Conferir tabela `notifications` ao investigar alertas de pedidos.
- Reproduzir fluxos de pedidos e merenda pelo painel Filament, porque parte da regra vive nas actions da interface.
- Em bugs de acesso, revisar:
  - `email_approved`
  - roles/permissoes
  - `id_escola`
  - policies
  - dominios autorizados

## Sinais uteis de observabilidade

- `PedidoHistorico` ajuda a reconstruir sequencia de alteracoes.
- `notifications` mostra efeitos colaterais de observer e commands.
- `EstoqueMovimentacao` ajuda a depurar entradas e saidas de estoque.
- `ContratoItem` ajuda a verificar incoerencia de saldo reservado/utilizado.

## Riscos e cuidados

- Sem testes fortes, evite refactor amplo sem roteiro manual de validacao.
- Fluxos com scheduler e cache podem parecer inconsistentes se o ambiente nao estiver completo.
- Alguns problemas sao de seed/configuracao e nao de codigo de interface.

## Quando consultar

- Antes de validar mudanca sensivel
- Ao montar plano de reproducao de bug
- Ao decidir quais testes automatizados faltam primeiro
