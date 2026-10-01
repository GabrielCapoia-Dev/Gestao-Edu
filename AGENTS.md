# Instruções do projeto

- Nunca execute testes localmente. Execute testes somente no Hub de Testes.
- Não inicie serviços ou ambientes locais para validar o projeto; use o Hub de Testes para execução dos testes e validação funcional.

## Manutenção evolutiva das skills

- Ao concluir uma implementação, avalie se o pedido ampliou um fluxo, introduziu regra de negócio duradoura, mudou uma responsabilidade de domínio ou revelou que uma skill existente ficou desatualizada.
- Se houver mudança duradoura, atualize somente a skill ou as skills diretamente afetadas; use `skill-creator` para revisar instruções e validar que continuem específicas e úteis.
- Não altere skills por ajustes isolados de interface, dados pontuais ou decisões temporárias. Evite duplicar regras já cobertas por `AGENTS.md` ou outra skill.
- Ao introduzir um domínio ou fluxo novo, atualize também o mapa de módulos e o roteamento do `gestao-edu-kit`, se aplicável.
- Faça uma revisão final das skills afetadas no mesmo fluxo da implementação e encerre quando o conhecimento novo estiver documentado; não monitore em segundo plano nem amplie tarefas não relacionadas.
