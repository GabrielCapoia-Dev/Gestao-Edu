Sistema Administrativo - Funcionalidade de Manutenção

Pontos principais abordados na reunião do dia 13/02

    1 - Criar um fluxo de nível de Emergência no nos pedidos (Emergencial, Preventivo, Corretivo) -> Opção de Criar novos

    2 - Medição/Aferição da obra com uma empresa terceirizada, talvez um status separado
    "O PEDIDO Tem que ter um 'precisa ir la ver na tela de criar o status'

        - Adicionar um campo no pedido que verifica se o serviço passou por medição/aferição

    3 - Confirmação da Escola que o pedido foi executado.

    4 - Escola pode reabrir um pedido que foi inicialmente fechado

    5 - Escola pode ver = {
        Histórico parcial
        Opção de Reabrir pedido ja finalizado

    }

    6 - A escola pode opinar sobre o serviço

    7 - Tela de Criar as empresas

    9 - Status encaminhado para o serviço publico

    10 - Todos os status podem ter um texto de descrição

    11 - Notificações de pedidos abertos e não lidos

    12 - notificar a escola "Pedido foi feito?"

    12 - Endereço e Telefone das escolas


Estrutura de modelos necessárias:

    1 - Escola = Descrições[
            Gerenciar pedidos (Criar, Editar, Listar),
            Marcar como feito,
            Ver o Histórico,
            Dar Feedback,
            ...
    ],
    2 - TipoManutencao = Descroções[
            Gerenciar Tipos de Manutenções(CRUD com Nome e Descrição.)
            Vinculado ao pedido, pelo usuário da escola, durante a ação de criar pedido.
            Serve para filtragem na tela de pedidos, deve ser ovjetivo e simples, voltado ao adm do setor.
    ],
    3 - TipoStatus = Descroções[
            Funcionalidade que dita o fluxo que um pedido segue no sistema(Em aberto, ativo, inativo, lido, etc...)
            Deve ter um campo boolean que determina se é um status especifico para o obras ou da educação
                Se for um usuário da educação alterando um status então assume o fluxo da educação
                Se for um usuário do obras alterando um status então assume o fluxo do obras

            Deve ter um campo boolean que determina se esse status finaliza um pedido, por exemplo (Finalizado)
                Se um pedido for marcado como finalizado, essa alteração dispara um novo botão na tela de listagem de pedidos da escola, possibilitando dar um feedback do pedido com opção
                de Satifeito ou Insatisfeito, e um campo de descrição.

            Pedidos finalizados podem ser reabertos caso o problema reapareça, impedindo assim a criação de um novo fluxo.

            Criado o SETOR, que serve para filtrar a quais secretarias pertencem determinado status
    ],
    4 - Pedido = Descroções[
            Na criação, Necessário envio de fotos.
            Na criação, Deve ter uma descrição no pedido
            Na criação, Deve inserir o tipo de manutenção
            

            Na edição, sempre que alterado o status do pedido necessário preencher uma descrição sobre oque aconteceu com o status do pedido

            Na listagem, opção de ver pedido, aonde lista todo o fluxo do pedido e suas alterações, além de conter cada uma das descrições inseridas.

            
    ],
    5 - Empresas = Descroções[
            Secretaria de obras cadastra empresas que prestam serviços
            Dados de Contato, numero do contrato, Nome da empresa
    ],
