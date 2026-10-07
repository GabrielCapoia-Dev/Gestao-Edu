# Backend e API — Gestão Edu 2.0

## Limite do projeto

O backend fica em `backend/`, com package manager, imagem Docker, configuração e dependências independentes. Não se adiciona referência no `package.json`, Docker Compose ou scripts da raiz Laravel. O frontend Angular será uma aplicação irmã em `frontend/` quando essa etapa começar; esta API não serve assets do legado.

## Base técnica inicial

- NestJS e TypeScript, API REST com prefixo `/api/v1`.
- TypeORM com schema via migrations versionadas; `synchronize=false` e `migrationsRun=false`.
- MySQL 8.4 como banco inicial; Redis já separado para preparação de cache/sessões, sem fluxo de autenticação ainda.
- Driver e schema foram escolhidos para começar, não constituem garantia de migração transparente para Oracle. Validar tipos, índices, constraints e migrations em uma prova de portabilidade antes de trocar o banco.
- Endpoints disponíveis: `GET /api/v1/health` (processo vivo) e `GET /api/v1/ready` (MySQL e Redis respondem).

## Organização do código

`src/health` contém probes; `src/infrastructure` contém adaptador Redis; `src/database` mantém datasource e migrations. À medida que os casos de uso forem definidos, cada domínio deverá separar entrada HTTP, aplicação/casos de uso, modelo de domínio e persistência. Não criar abstrações genéricas sem necessidade comprovada; manter FKs e invariantes explícitas.

## Segurança e operação

- Nenhum CRUD de PII é exposto antes de autenticação Google, autorização e auditoria.
- Não armazenar tokens Google. A decisão vigente para sessão é identificador opaco em cookie HttpOnly/Secure/SameSite e estado server-side em Redis, duração máxima de 4h; política de expiração por inatividade ainda em aberto.
- Segredos somente por ambiente/secret store; `.env` ignorado pelo Git. Banco e cache ficam na rede privada e não têm portas publicadas; a API possui egress para integrações externas, como OAuth Google.
- Container de runtime não root e filesystem somente leitura, exceto `/tmp`.
- Migrations devem ser revisadas e executadas como etapa controlada do deploy, com backup e plano de recuperação. Não usar `down -v` para atualização comum.

## US e CT da fundação

### US003 — Estruturar identidade e vínculo municipal para a gestão de pessoas

Como responsável pela plataforma, quero uma fundação de dados que separe pessoa, conta, servidor, matrícula e históricos, para permitir que os domínios usem os mesmos conceitos sem duplicar dados pessoais nem apagar mudanças funcionais.

**Critérios de aceite**

- Pessoa usa UUID de 128 bits, nome obrigatório e CPF opcional, sem status de ativo.
- Conta de acesso opcional referencia pessoa 1:1; status de acesso separado e identidade Google referenciada por `sub`, sem tokens armazenados.
- Perfil municipal é opcional e pode ter múltiplas matrículas; cada matrícula pertence a um perfil.
- Contratação, regime, cargo, faixa, local de trabalho e status são referências próprias, com histórico temporal onde aplicável.
- A API não disponibiliza CRUD de dados pessoais antes de autenticação/autorização.
- Schema é aplicado exclusivamente por migrations explícitas e pode ser inicializado sem afetar o Laravel existente.

**Casos de teste**

- CT01: criar pessoa sem CPF, conta ou matrícula; identidade não depende de vínculo.
- CT02: permitir no máximo uma conta por pessoa; rejeitar conta sem pessoa e status fora da lista suportada.
- CT03: criar perfil municipal para pessoa e várias matrículas para o perfil; rejeitar matrícula sem perfil.
- CT04: manter contratação e regime como referências e impedir IDs inexistentes por FK.
- CT05: relacionar períodos de cargo, local, condições e faixa à matrícula e rejeitar datas invertidas.
- CT06: registrar transições de status como períodos sem apagar o histórico anterior; implementar validação contra períodos conflitantes antes do CRUD.
- CT07: aplicar migrations explicitamente, sem sincronização automática nem execução no boot.
- CT08: health responde como vivo; readiness só responde pronto quando MySQL e Redis respondem.

**Nota de cobertura:** CT06 exige validação transacional contra sobreposição e ainda não tem fluxo de escrita; não considerar completa a regra de negócio por existir apenas a estrutura das tabelas. Os testes automatizados e integração com banco devem ser executados no Hub de Testes, conforme as regras do repositório.
