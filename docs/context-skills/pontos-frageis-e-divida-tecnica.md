# Pontos Frageis e Divida Tecnica

## Objetivo

Consolidar riscos tecnicos e inconsistencias que merecem cuidado especial em manutencao futura.

## Onde isso aparece

- `README.md`
- `app/Services/PedidoService.php`
- `app/Observers/PedidoObserver.php`
- `database/seeders/DatabaseSeeder.php`
- `database/seeders/TipoStatusSeeder.php`
- `tests`

## Fragilidades principais

- Documentacao principal nao acompanha o escopo atual do produto.
- Forte acoplamento a nomes seedados de status e setores.
- Catalogo de permissoes disperso e parcialmente duplicado entre seeders/comando.
- Regras de dominio espalhadas entre model, service, observer e UI do Filament.
- Quase ausencia de testes automatizados relevantes.
- Dependencia de recurso externo no `SecretarioUnidadesSeeder`.

## Divida tecnica observada

- `PedidoObserver` tem ramo de reabertura que merece revisao cuidadosa.
- Policies de alguns recursos sao amplas; o filtro real acontece em services/querys e pode gerar falsa sensacao de seguranca.
- O projeto mistura varios dominios num mesmo painel sem isolamento forte por modulo.
- Seeders concentram conhecimento funcional essencial; isso facilita bootstrap, mas torna o comportamento dependente de dados iniciais especificos.

## Efeito pratico dessa divida

- Mudancas pequenas podem ter impacto maior do que aparentam.
- Refactors exigem leitura transversal de varios pontos.
- Bugs de permissao e visibilidade podem nascer da combinacao de policy, role e filtro de consulta.

## Quando consultar

- Antes de planejar refactor
- Em revisao tecnica
- Ao estimar risco de alteracao
