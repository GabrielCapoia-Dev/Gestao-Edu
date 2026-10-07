# RH e gestão de pessoas — fundação inicial

> Estado: proposta implementada como primeira fundação técnica; regras marcadas como provisórias aguardam validação do RH.

## Conceitos e cardinalidades

- **Pessoa** é a identidade central e pode existir sem conta, matrícula ou vínculo municipal. Possui UUID v4 (128 bits), nome completo e CPF opcional. Não tem status ativo/inativo.
- **Conta de usuário** é opcional e no máximo uma por pessoa nesta primeira modelagem (`user_accounts.person_uuid` é PK/FK). Status de acesso pertence à conta. Identidade Google usa `sub` (`google_subject`); tokens não são persistidos. Client ID é configuração do provedor, não dado individual.
- **Servidor municipal** é um perfil opcional da pessoa. O perfil pode possuir várias matrículas; cada matrícula pertence a um perfil.
- **Matrícula** possui identificador UUID próprio e número funcional, datas e referências a tipo de contratação e regime jurídico. O número foi modelado como único globalmente de forma provisória; confirmar escopo e reutilização histórica.
- **Cargo** é catálogo separado. **Faixa** depende de cargo na modelagem inicial, mas plano de carreira e regras de progressão ainda precisam ser descobertos.
- **Local de trabalho** é catálogo próprio; lotação/local e cargo têm períodos históricos independentes, ambos vinculados à matrícula.
- **Condições de trabalho** guardam carga horária e turno por período. A matrícula não sobrescreve o histórico quando estes dados mudam.
- **Status da matrícula** tem catálogo e períodos de vigência. Ativo, afastado e exonerado são os estados iniciais citados; afastamento não encerra necessariamente a matrícula.

## Invariantes iniciais

1. Nenhuma matrícula duplica nome ou CPF: os dados pessoais ficam em `people`.
2. Pessoa pode existir sem ser servidor e servidor pode existir sem conta de acesso.
3. Relações críticas usam FKs explícitas, sem associação polimórfica genérica `type + id`.
4. Mudanças funcionais são registradas em períodos, em vez de apagar o valor anterior.
5. Tabelas de catálogo não recebem opções jurídicas sem validação do RH.

## Em aberto antes de fechar regras

- CPF pode repetir, ser corrigido ou ficar ausente em quais cenários? Política de normalização e unicidade.
- E-mail de login é único globalmente? Pode mudar? O e-mail institucional será obrigatório?
- Uma pessoa pode ter mais de uma matrícula ativa ao mesmo tempo? Número de matrícula pode ser reutilizado após exoneração?
- Tipo de contratação (CLT/PSS/estatutário) e regime jurídico (RJU/RAE/celetista) são dimensões independentes ou conceitos sobrepostos? Confirmar com RH/jurídico.
- Cargo pode mudar independentemente da matrícula? Faixa depende só do cargo ou também de plano/carreira e categoria?
- A lotação é sempre uma unidade/local físico, ou existe lotação administrativa independente do local de trabalho?
- O servidor municipal pode permanecer cadastrado após todas as matrículas serem encerradas? Como entram vínculos estaduais e externos posteriormente?
- Quais são os estados completos, transições, razões obrigatórias, retroatividade e documentos comprobatórios?
- Como modelar jornada, turnos múltiplos, carga horária variável e vigências sobrepostas?
- Nome completo e CPF podem ser alterados? Quem pode fazê-lo e como auditar valores anteriores?

Essas respostas devem orientar próximas revisões da US003 e migrations futuras; não alterar esta migration inicial depois de aplicada em ambientes compartilhados. Corrigir schema com migration aditiva.
