# Gestão Edu 2.0

Projeto independente do Gestão Edu legado. O build, as dependências e o Compose desta pasta não são referenciados pelos arquivos da raiz.

O diretório próprio não cria automaticamente uma URL dentro do site Laravel. A API está separada e acessível pela porta local indicada; publicar por um caminho/domínio exige uma configuração futura de reverse proxy no servidor.

## Backend inicial

O backend está em `backend/` e fornece uma API NestJS versionada em `/api/v1`. Nesta etapa, os endpoints de saúde validam disponibilidade; ainda não há CRUD de pessoas nem login. Os dados pessoais não devem ser expostos antes das histórias de autenticação e autorização.

### Subir o ambiente de desenvolvimento

1. Copie `.env.example` para `.env` e substitua todos os segredos.
2. Na pasta `Gestão Edu 2.0`, execute `docker compose up --build -d`.
3. A API fica em `http://localhost:3030/api/v1/health` e a prontidão do banco em `/api/v1/ready`.
4. Para desligar: `docker compose down`. O volume do banco é mantido; não use `down -v` em dados que queira preservar.

As portas de MySQL e Redis não são publicadas no host. O Compose tem redes e volumes próprios e não depende do Compose da raiz. A API tem saída de rede para futuras integrações OAuth; banco e Redis ficam apenas na rede privada.

### Migrations

As migrations são explícitas: não há `synchronize` nem execução automática no boot da API. Compile a versão e execute no serviço backend `npm run migration:run`. Em atualizações, faça backup, aplique migrations aprovadas antes de liberar a versão e mantenha o procedimento de rollback específico da migration. O comando do Compose pode ser executado com `docker compose exec backend npm run migration:run`.

## Modelagem inicial

Ver [`documentation/02-rh-e-pessoas.md`](documentation/02-rh-e-pessoas.md) e [`documentation/03-backend.md`](documentation/03-backend.md). A modelagem é uma fundação, não substitui validação das regras de RH. Dúvidas relevantes estão registradas antes de se tornarem restrições irreversíveis.
