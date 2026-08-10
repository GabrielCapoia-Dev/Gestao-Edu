-- Corrige as pautas de habilidades do Parecer - 2º trimestre de 2026.
-- Fonte: Orientação Pedagógica nº 031/2026, SME de Umuarama, páginas 2 a 15.
-- Ambiente de referência: Hub de testes auditado em 10/08/2026 (MySQL 8.0.45).
--
-- Decisões de importação:
-- - grava somente o texto da habilidade; a numeração fica apenas nesta tabela temporária;
-- - preserva redação e pontuação da orientação, inclusive inconsistências da fonte;
-- - deduplica a repetição literal dos itens 1 e 3 de História/2º Ano;
-- - mantém como pauta própria a linha sem número de História/3º Ano (ordem 2.5);
-- - não transforma os blocos complementares de níveis de escrita/SND em pautas ou alternativas;
-- - substitui o conjunto ativo por série+componente sem apagar registros fisicamente;
-- - não amplia escopo de avaliação, turma, escola, série, componente ou professor;
-- - só sincroniza a avaliação `ativa` informada explicitamente e apenas na interseção
--   das séries e componentes que ela já possui;
-- - aborta todas as mutações se uma pauta obsoleta possuir referência avaliativa/histórica,
--   ou se uma avaliação que precisaria mudar já possuir documentos/fatos.
--
-- Configuração opcional:
-- - NULL: corrige apenas o catálogo; não altera nenhuma avaliação existente.
-- - ID: sincroniza somente essa avaliação, que deve ser Parecer e estar com status `ativa`.
-- Não reutilize este arquivo com NULL esperando sincronização automática: o Hub não possui
-- período de 2º trimestre e uma busca ampla poderia alcançar outra avaliação no futuro.
SET @avaliacao_alvo_id := NULL;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_habilidades_2tri_2026;
CREATE TEMPORARY TABLE tmp_habilidades_2tri_2026 (
    pagina_origem tinyint unsigned NOT NULL,
    serie_origem varchar(32) NOT NULL,
    componente_origem varchar(128) NOT NULL,
    serie_codigo varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    componente_codigo varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    ordem_origem decimal(5,1) NOT NULL,
    numero_origem smallint unsigned NULL,
    texto text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB;

INSERT INTO tmp_habilidades_2tri_2026
    (pagina_origem, serie_origem, componente_origem, serie_codigo,
     componente_codigo, ordem_origem, numero_origem, texto)
VALUES
(2, '1º Ano', 'ARTE', 'SER005', 'arte', 1.0, 1, 'Representa seu autorretrato.'),
(2, '1º Ano', 'ARTE', 'SER005', 'arte', 2.0, 2, 'Identifica as cores primárias.'),
(2, '1º Ano', 'ARTE', 'SER005', 'arte', 3.0, 3, 'Reconhece o retrato de Mona Lisa.'),
(2, '1º Ano', 'ARTE', 'SER005', 'arte', 4.0, 4, 'Representa obra que condiz com um retrato.'),
(2, '1º Ano', 'ARTE', 'SER005', 'arte', 5.0, 5, 'Participa expressivamente de brincadeiras cantadas.'),
(2, '1º Ano', 'ARTE', 'SER005', 'arte', 6.0, 6, 'Demonstra expressividade nas brincadeiras de faz de conta e/ou imitação.'),
(2, '2º Ano', 'ARTE', 'SER006', 'arte', 1.0, 1, 'Representa seres ou objetos através da técnica de dobradura.'),
(2, '2º Ano', 'ARTE', 'SER006', 'arte', 2.0, 2, 'Reconhece a dança circular como expressão artística.'),
(2, '2º Ano', 'ARTE', 'SER006', 'arte', 3.0, 3, 'Identifica algumas obras de Lygia Clark.'),
(2, '2º Ano', 'ARTE', 'SER006', 'arte', 4.0, 4, 'Representa formas e linhas em produções de desenho e pintura.'),
(3, '2º Ano', 'ARTE', 'SER006', 'arte', 5.0, 5, 'Reconhece o corpo elemento importante da comunicação e da expressão teatral.'),
(3, '2º Ano', 'ARTE', 'SER006', 'arte', 6.0, 6, 'Participa de rodas cantadas, acompanhando o ritmo da música.'),
(3, '3º Ano', 'ARTE', 'SER007', 'arte', 1.0, 1, 'Identifica diferentes formatos do teatro de bonecos.'),
(3, '3º Ano', 'ARTE', 'SER007', 'arte', 2.0, 2, 'Identifica obras do pintor Abraão Hachicho.'),
(3, '3º Ano', 'ARTE', 'SER007', 'arte', 3.0, 3, 'Brinca e se expressa utilizando o teatro de bonecos de forma criativa.'),
(3, '3º Ano', 'ARTE', 'SER007', 'arte', 4.0, 4, 'Identifica obras e características da pintura realista.'),
(3, '3º Ano', 'ARTE', 'SER007', 'arte', 5.0, 5, 'Reconhece algumas danças realizadas em pares.'),
(3, '3º Ano', 'ARTE', 'SER007', 'arte', 6.0, 6, 'Reconhece valores necessários para o bom andamento da dança em par, como respeito, cooperação e sintonia.'),
(3, '4º Ano', 'ARTE', 'SER008', 'arte', 1.0, 1, 'Diferencia as técnicas de alto-relevo e baixo-relevo em produções artísticas.'),
(3, '4º Ano', 'ARTE', 'SER008', 'arte', 2.0, 2, 'Reconhece diferentes manifestações das artes cênicas, como teatro, dança e ópera.'),
(3, '4º Ano', 'ARTE', 'SER008', 'arte', 3.0, 3, 'Reconhece elementos que compõem uma arte cênica, como personagens, cenário, figurino, movimentos e expressão corporal.'),
(3, '4º Ano', 'ARTE', 'SER008', 'arte', 4.0, 4, 'Reconhece características das obras de Tarsila do Amaral.'),
(3, '4º Ano', 'ARTE', 'SER008', 'arte', 5.0, 5, 'Relaciona obras de arte ao contexto histórico e cultural em que foram produzidas.'),
(3, '4º Ano', 'ARTE', 'SER008', 'arte', 6.0, 6, 'Compreende o conceito de estilo artístico nas artes visuais.'),
(3, '4º Ano', 'ARTE', 'SER008', 'arte', 7.0, 7, 'Identifica características da escrita musical antiga e da notação musical atual.'),
(3, '5º Ano', 'ARTE', 'SER009', 'arte', 1.0, 1, 'Realiza composições artísticas utilizando a técnica do pontilhismo com organização e criatividade.'),
(3, '5º Ano', 'ARTE', 'SER009', 'arte', 2.0, 2, 'Identifica elementos da Op Art em diferentes produções artísticas.'),
(3, '5º Ano', 'ARTE', 'SER009', 'arte', 3.0, 3, 'Identifica diferenças entre arte artesanal e produção industrial.'),
(3, '5º Ano', 'ARTE', 'SER009', 'arte', 4.0, 4, 'Demonstra criatividade e organização na elaboração do projeto da cadeira.'),
(3, '5º Ano', 'ARTE', 'SER009', 'arte', 5.0, 5, 'Reconhece a dança como linguagem artística presente no cinema.'),
(3, '1º Ano', 'EDUCAÇÃO FÍSICA', 'SER005', 'educacao_fisica', 1.0, 1, 'Emprega meios para deslocar o corpo ou um segmento do corpo, numa unidade de tempo cada vez menor.'),
(3, '1º Ano', 'EDUCAÇÃO FÍSICA', 'SER005', 'educacao_fisica', 2.0, 2, 'Coordena o sentido da visão com os movimentos dos membros superiores.'),
(3, '1º Ano', 'EDUCAÇÃO FÍSICA', 'SER005', 'educacao_fisica', 3.0, 3, 'Coordena o sentido da visão com os movimentos dos membros inferiores.'),
(3, '1º Ano', 'EDUCAÇÃO FÍSICA', 'SER005', 'educacao_fisica', 4.0, 4, 'Reconhece o seu lado dominante.'),
(4, '1º Ano', 'EDUCAÇÃO FÍSICA', 'SER005', 'educacao_fisica', 5.0, 5, 'Joga o jogo do dominó tradicional respeitando suas regras.'),
(4, '1º Ano', 'EDUCAÇÃO FÍSICA', 'SER005', 'educacao_fisica', 6.0, 6, 'Diferencia o sistema de distância (longe e perto).'),
(4, '1º Ano', 'EDUCAÇÃO FÍSICA', 'SER005', 'educacao_fisica', 7.0, 7, 'Diferencia o sistema de localização (dentro, fora, em cima, embaixo).'),
(4, '1º Ano', 'EDUCAÇÃO FÍSICA', 'SER005', 'educacao_fisica', 8.0, 8, 'Diferencia conceitos de sucessão temporal (antes, agora e depois).'),
(4, '2º Ano', 'EDUCAÇÃO FÍSICA', 'SER006', 'educacao_fisica', 1.0, 1, 'Utiliza estratégias para resolver os desafios dos jogos pré-desportivos.'),
(4, '2º Ano', 'EDUCAÇÃO FÍSICA', 'SER006', 'educacao_fisica', 2.0, 2, 'Participa do esporte golfe respeitando seus elementos (acertar uma bola estática com um taco; tentar acertar a bola dentro do alvo ou o mais próximo dele).'),
(4, '2º Ano', 'EDUCAÇÃO FÍSICA', 'SER006', 'educacao_fisica', 3.0, 3, 'Identifica as características que tornam um dos lados de seu corpo dominante (agilidade, força e destreza motora). (Exemplos de fala que o aluno pode ter para mostrar que identifica: tenho mais força; é melhor para fazer; é mais fácil; etc.).'),
(4, '2º Ano', 'EDUCAÇÃO FÍSICA', 'SER006', 'educacao_fisica', 4.0, 4, 'Joga o jogo do dominó tradicional respeitando suas regras.'),
(4, '2º Ano', 'EDUCAÇÃO FÍSICA', 'SER006', 'educacao_fisica', 5.0, 5, 'Joga o jogo da trilha.'),
(4, '2º Ano', 'EDUCAÇÃO FÍSICA', 'SER006', 'educacao_fisica', 6.0, 6, 'Participa do esporte halterofilismo respeitando seus elementos (segurar a barra do peso com as duas mãos; levantar o peso acima da cabeça).'),
(4, '3º Ano', 'EDUCAÇÃO FÍSICA', 'SER007', 'educacao_fisica', 1.0, 1, 'Expressa-se oralmente nos jogos de dramatização.'),
(4, '3º Ano', 'EDUCAÇÃO FÍSICA', 'SER007', 'educacao_fisica', 2.0, 2, 'Expressa-se corporalmente nos jogos de dramatização.'),
(4, '3º Ano', 'EDUCAÇÃO FÍSICA', 'SER007', 'educacao_fisica', 3.0, 3, 'Experimenta e frui danças do contexto local e regional.'),
(4, '3º Ano', 'EDUCAÇÃO FÍSICA', 'SER007', 'educacao_fisica', 4.0, 4, 'Identifica a esquerda e direita no seu corpo.'),
(4, '3º Ano', 'EDUCAÇÃO FÍSICA', 'SER007', 'educacao_fisica', 5.0, 5, 'Identifica a esquerda e direita no outro.'),
(4, '3º Ano', 'EDUCAÇÃO FÍSICA', 'SER007', 'educacao_fisica', 6.0, 6, 'Reconhece o esporte Handebol nos jogos pré-desportivos.'),
(4, '3º Ano', 'EDUCAÇÃO FÍSICA', 'SER007', 'educacao_fisica', 7.0, 7, 'Participa dos jogos pré-desportivos utilizando os fundamentos básicos do handebol (usar as mãos para manipular a bola; arremesso; passe e drible).'),
(4, '3º Ano', 'EDUCAÇÃO FÍSICA', 'SER007', 'educacao_fisica', 8.0, 8, 'Joga o jogo de damas.'),
(4, '3º Ano', 'EDUCAÇÃO FÍSICA', 'SER007', 'educacao_fisica', 9.0, 9, 'Participa do esporte bocha respeitando seus elementos (lançar as bolas de uma área determinada; fazer com que as bolas se aproximem do “bolim” que estará em um ponto determinado aleatoriamente).'),
(4, '3º Ano', 'EDUCAÇÃO FÍSICA', 'SER007', 'educacao_fisica', 10.0, 10, 'Reconhece o esporte Basquetebol nos jogos pré-desportivos.'),
(4, '3º Ano', 'EDUCAÇÃO FÍSICA', 'SER007', 'educacao_fisica', 11.0, 11, 'Participa dos jogos pré-desportivos utilizando os fundamentos básicos do basquetebol (usar as mãos para manipular a bola; arremesso; passe e drible).'),
(4, '4º Ano', 'EDUCAÇÃO FÍSICA', 'SER008', 'educacao_fisica', 1.0, 1, 'Realiza os movimentos relacionados à ginástica circense.'),
(4, '4º Ano', 'EDUCAÇÃO FÍSICA', 'SER008', 'educacao_fisica', 2.0, 2, 'Experimenta e frui danças do contexto brasileiro.'),
(4, '4º Ano', 'EDUCAÇÃO FÍSICA', 'SER008', 'educacao_fisica', 3.0, 3, 'Reconhece o esporte Handebol nos jogos pré-desportivos.'),
(4, '4º Ano', 'EDUCAÇÃO FÍSICA', 'SER008', 'educacao_fisica', 4.0, 4, 'Participa dos jogos pré-desportivos utilizando os fundamentos básicos do handebol (usar as mãos para manipular a bola; arremesso; passe e drible).'),
(5, '4º Ano', 'EDUCAÇÃO FÍSICA', 'SER008', 'educacao_fisica', 5.0, 5, 'Joga o jogo de damas respeitando as suas regras.'),
(5, '4º Ano', 'EDUCAÇÃO FÍSICA', 'SER008', 'educacao_fisica', 6.0, 6, 'Participa do esporte atletismo – lançamentos e arremessos - respeitando seus elementos básicos (realizar o lançamento do objeto de dentro da área estabelecida; lançar o objeto o mais longe possível).'),
(5, '4º Ano', 'EDUCAÇÃO FÍSICA', 'SER008', 'educacao_fisica', 7.0, 7, 'Participa do esporte da categoria campo e taco - respeitando seus elementos básicos (rebater uma bola lançada pelo adversário o mais longe possível; percorrer o maior número de vezes as bases ou a maior distância entre as bases.).'),
(5, '5º Ano', 'EDUCAÇÃO FÍSICA', 'SER009', 'educacao_fisica', 1.0, 1, 'Coopera com os outros alunos para obter sucesso, considerando-os como parceiros e não como adversários.'),
(5, '5º Ano', 'EDUCAÇÃO FÍSICA', 'SER009', 'educacao_fisica', 2.0, 2, 'Organiza o corpo no tempo e espaço na dança circular.'),
(5, '5º Ano', 'EDUCAÇÃO FÍSICA', 'SER009', 'educacao_fisica', 3.0, 3, 'Conhece algumas regras do esporte handebol (não usar os pés; fazer gol na baliza adversária; a partida é disputada dentro de um espaço determinado; apenas o goleiro pode ficar dentro da área).'),
(5, '5º Ano', 'EDUCAÇÃO FÍSICA', 'SER009', 'educacao_fisica', 4.0, 4, 'Utiliza os fundamentos básicos do esporte handebol (empunhadura; recepção; arremesso; passe; drible e passos) nas atividades.'),
(5, '5º Ano', 'EDUCAÇÃO FÍSICA', 'SER009', 'educacao_fisica', 5.0, 5, 'Participa do esporte da categoria rede/parede - respeitando seus elementos básicos (arremessar, lançar ou rebater a bola em direção a setores da quadra adversária nos quais o rival seja incapaz de devolvê-la ou que leve o adversário a cometer um erro dentro do período de tempo em que o objeto do jogo está em movimento).'),
(5, '5º Ano', 'EDUCAÇÃO FÍSICA', 'SER009', 'educacao_fisica', 6.0, 6, 'Participa do esporte atletismo – lançamento e arremesso - respeitando seus elementos básicos (lançar ou arremessar o objeto com ou sem corrida; realizar o lançamento do objeto de dentro da área estabelecida; lançar o objeto o mais longe possível).'),
(5, '5º Ano', 'EDUCAÇÃO FÍSICA', 'SER009', 'educacao_fisica', 7.0, 7, 'Participa do esporte da categoria campo e taco - respeitando seus elementos básicos (rebater uma bola lançada pelo adversário o mais longe possível; percorrer o maior número de vezes as bases ou a maior distância entre as bases.).'),
(5, '1º Ano', 'HISTÓRIA', 'SER005', 'historia', 1.0, 1, 'Reconhece os laços de afeto que unem uma família.'),
(5, '1º Ano', 'HISTÓRIA', 'SER005', 'historia', 2.0, 2, 'Diferencia famílias de outros tempos e da atualidade.'),
(5, '1º Ano', 'HISTÓRIA', 'SER005', 'historia', 3.0, 3, 'Identifica festas e comemorações familiares como momentos que fortalecem os vínculos de afeto.'),
(5, '1º Ano', 'HISTÓRIA', 'SER005', 'historia', 4.0, 4, 'Reconhece diferentes configurações familiares.'),
(5, '1º Ano', 'HISTÓRIA', 'SER005', 'historia', 5.0, 5, 'Identifica atitudes de respeito, cuidado e colaboração no convívio familiar.'),
(5, '2º Ano', 'HISTÓRIA', 'SER006', 'historia', 1.0, 1, 'Identifica espaços e situações de convivência familiar, reconhecendo a família como grupo de pertencimento.'),
(5, '2º Ano', 'HISTÓRIA', 'SER006', 'historia', 2.0, 2, 'Reconhece objetos e fotografias como registros que ajudam a contar histórias pessoais e familiares.'),
(6, '2º Ano', 'HISTÓRIA', 'SER006', 'historia', 4.0, 4, 'Reconhece os diferentes sujeitos que compõem a família.'),
(6, '2º Ano', 'HISTÓRIA', 'SER006', 'historia', 5.0, 5, 'Identifica relações de parentesco, afeto, cuidado e convivência no grupo familiar.'),
(6, '3º Ano', 'HISTÓRIA', 'SER007', 'historia', 1.0, 1, 'Identifica características do modo de vida da população Xetá.'),
(6, '3º Ano', 'HISTÓRIA', 'SER007', 'historia', 2.0, 2, 'Reconhece o povo indígena Xetá e outros grupos sociais e étnicos como parte da formação histórica, cultural e social do lugar onde vive.'),
(6, '3º Ano', 'HISTÓRIA', 'SER007', 'historia', 2.5, NULL, 'Identifica algumas formas de lazer realizadas na cidade e no campo.'),
(6, '3º Ano', 'HISTÓRIA', 'SER007', 'historia', 3.0, 3, 'Reconhece diferentes formas de lazer realizadas no campo.'),
(6, '3º Ano', 'HISTÓRIA', 'SER007', 'historia', 4.0, 4, 'Identifica atividades relacionadas à natureza e ao modo de vida em espaços rurais.'),
(6, '3º Ano', 'HISTÓRIA', 'SER007', 'historia', 5.0, 5, 'Reconhece diferentes manifestações culturais, como festas, brincadeiras, museus e gastronomia.'),
(6, '3º Ano', 'HISTÓRIA', 'SER007', 'historia', 6.0, 6, 'Identifica a importância das manifestações culturais para a história, a memória e a identidade dos grupos sociais.'),
(6, '4º Ano', 'HISTÓRIA', 'SER008', 'historia', 1.0, 1, 'Reconhece a importância das novas tecnologias e das rotas comerciais.'),
(6, '4º Ano', 'HISTÓRIA', 'SER008', 'historia', 2.0, 2, 'Reconhece a importância dos caminhos terrestres, fluviais e marítimos.'),
(6, '4º Ano', 'HISTÓRIA', 'SER008', 'historia', 3.0, 3, 'Identifica o papel de Portugal e Espanha nas Grandes Navegações.'),
(6, '4º Ano', 'HISTÓRIA', 'SER008', 'historia', 4.0, 4, 'Reconhece as rotas marítimas como importantes para a circulação de mercadorias, pessoas e culturas, bem como para as transformações da vida social e comercial.'),
(6, '5º Ano', 'HISTÓRIA', 'SER009', 'historia', 1.0, 1, 'Identifica a organização política do Egito Antigo.'),
(6, '5º Ano', 'HISTÓRIA', 'SER009', 'historia', 2.0, 2, 'Reconhece o papel das culturas e das religiões na composição identitária dos povos antigos.'),
(6, '5º Ano', 'HISTÓRIA', 'SER009', 'historia', 3.0, 3, 'Reconhece aspectos da organização política da Grécia Antiga.'),
(6, '5º Ano', 'HISTÓRIA', 'SER009', 'historia', 4.0, 4, 'Identifica a importância das cidades-Estado, da cidadania e da participação social para a formação da ideia de Estado.'),
(6, '5º Ano', 'HISTÓRIA', 'SER009', 'historia', 5.0, 5, 'Reconhece elementos da cultura e da religião grega, identificando sua importância para a formação da identidade e dos modos de vida do povo grego na Antiguidade.'),
(6, '1º Ano', 'LÍNGUA INGLESA', 'SER005', 'ingles', 1.0, 1, 'Compreende e utiliza expressões de cortesia em situações simples (Thank you, Sorry, Please, Excuse me).'),
(6, '1º Ano', 'LÍNGUA INGLESA', 'SER005', 'ingles', 2.0, 2, 'Reconhece e nomeia cores em inglês.'),
(6, '1º Ano', 'LÍNGUA INGLESA', 'SER005', 'ingles', 3.0, 3, 'Reconhece e relaciona imagens ao vocabulário de brinquedos em inglês.'),
(6, '1º Ano', 'LÍNGUA INGLESA', 'SER005', 'ingles', 4.0, 4, 'Reconhece e nomeia formas geométricas em inglês (circle, square, triangle, rectangle).'),
(7, '1º Ano', 'LÍNGUA INGLESA', 'SER005', 'ingles', 5.0, 5, 'Reconhece e fala alguns números em inglês de 1 a 10.'),
(7, '1º Ano', 'LÍNGUA INGLESA', 'SER005', 'ingles', 6.0, 6, 'Reconhece e nomeia materiais escolares/artísticos em inglês (paper, glue, paint, yarn).'),
(7, '2º Ano', 'LÍNGUA INGLESA', 'SER006', 'ingles', 1.0, 1, 'Compreende e utiliza expressões de cortesia em situações simples (Thank you, Please, Sorry, Excuse me).'),
(7, '2º Ano', 'LÍNGUA INGLESA', 'SER006', 'ingles', 2.0, 2, 'Reconhece e nomeia os números de 1 a 20 em inglês.'),
(7, '2º Ano', 'LÍNGUA INGLESA', 'SER006', 'ingles', 3.0, 3, 'Reconhece e nomeia objetos do cotidiano escolar em inglês (book, notebook, desk, chair).'),
(7, '2º Ano', 'LÍNGUA INGLESA', 'SER006', 'ingles', 4.0, 4, 'Reconhece e utiliza regras básicas de convivência no contexto da sala de aula.'),
(7, '2º Ano', 'LÍNGUA INGLESA', 'SER006', 'ingles', 5.0, 5, 'Reconhece e nomeia animais de estimação em inglês (cat, dog, fish).'),
(7, '2º Ano', 'LÍNGUA INGLESA', 'SER006', 'ingles', 6.0, 6, 'Participa de interações orais simples utilizando o vocabulário trabalhado em sala.'),
(7, '3º Ano', 'LÍNGUA INGLESA', 'SER007', 'ingles', 1.0, 1, 'Reconhece os números de 1 a 20 em inglês oralmente'),
(7, '3º Ano', 'LÍNGUA INGLESA', 'SER007', 'ingles', 2.0, 2, 'Reconhece oralmente comandos e expressões relacionados às regras da turma em inglês.'),
(7, '3º Ano', 'LÍNGUA INGLESA', 'SER007', 'ingles', 3.0, 3, 'Compreende palavras e expressões simples em inglês em atividades de escuta e oralidade.'),
(7, '3º Ano', 'LÍNGUA INGLESA', 'SER007', 'ingles', 4.0, 4, 'Relaciona imagens ao vocabulário de animais em inglês.'),
(7, '3º Ano', 'LÍNGUA INGLESA', 'SER007', 'ingles', 5.0, 5, 'Reconhece e relaciona os dias da semana ao contexto do calendário escolar.'),
(7, '3º Ano', 'LÍNGUA INGLESA', 'SER007', 'ingles', 6.0, 6, 'Reconhece e relaciona objetos da sala de aula à imagem e ao vocabulário correspondente em inglês.'),
(7, '4º Ano', 'LÍNGUA INGLESA', 'SER008', 'ingles', 1.0, 1, 'Identifica comandos e instruções comuns do cotidiano escolar em língua inglesa.'),
(7, '4º Ano', 'LÍNGUA INGLESA', 'SER008', 'ingles', 2.0, 2, 'Reconhece e nomeia os dias da semana.'),
(7, '4º Ano', 'LÍNGUA INGLESA', 'SER008', 'ingles', 3.0, 3, 'Reconhece e nomeia os meses do ano.'),
(7, '4º Ano', 'LÍNGUA INGLESA', 'SER008', 'ingles', 4.0, 4, 'Reconhece alguns esportes em inglês.'),
(7, '4º Ano', 'LÍNGUA INGLESA', 'SER008', 'ingles', 5.0, 5, 'Reconhece algumas profissões em inglês.'),
(7, '4º Ano', 'LÍNGUA INGLESA', 'SER008', 'ingles', 6.0, 6, 'Utiliza estruturas simples para identificar profissões (He’s a… / She’s a…).'),
(7, '4º Ano', 'LÍNGUA INGLESA', 'SER008', 'ingles', 7.0, 7, 'Participa de interações orais simples em língua inglesa.'),
(7, '5º Ano', 'LÍNGUA INGLESA', 'SER009', 'ingles', 1.0, 1, 'Compreende e responde a comandos e instruções simples do cotidiano escolar em língua inglesa.'),
(7, '5º Ano', 'LÍNGUA INGLESA', 'SER009', 'ingles', 2.0, 2, 'Utiliza a estrutura simples I like… para expressar preferências.'),
(7, '5º Ano', 'LÍNGUA INGLESA', 'SER009', 'ingles', 3.0, 3, 'Reconhece e nomeia vocabulário relacionado à alimentação em inglês.'),
(7, '5º Ano', 'LÍNGUA INGLESA', 'SER009', 'ingles', 4.0, 4, 'Reconhece e nomeia números em inglês de 1 a 100.'),
(7, '5º Ano', 'LÍNGUA INGLESA', 'SER009', 'ingles', 5.0, 5, 'Reconhece alimentos em inglês.'),
(8, '5º Ano', 'LÍNGUA INGLESA', 'SER009', 'ingles', 6.0, 6, 'Utiliza estruturas simples para expressar preferências (I like… / I don’t like…).'),
(8, '5º Ano', 'LÍNGUA INGLESA', 'SER009', 'ingles', 7.0, 7, 'Responde oralmente a perguntas simples sobre preferências (Do you like…?).'),
(8, '1º Ano', 'CIÊNCIAS', 'SER005', 'ciencias', 1.0, 1, 'Representa, por meio de desenho, partes do corpo humano.'),
(8, '1º Ano', 'CIÊNCIAS', 'SER005', 'ciencias', 2.0, 2, 'Relaciona as partes do corpo humano aos sentidos, reconhecendo o que podemos perceber por meio deles.'),
(8, '1º Ano', 'GEOGRAFIA', 'SER005', 'geografia', 1.0, 1, 'Utiliza o corpo como referência para localizar elementos do local de vivência considerando referenciais espaciais.'),
(8, '1º Ano', 'LÍNGUA PORTUGUESA', 'SER005', 'portugues', 1.0, 1, 'Lê palavra formada por sílaba canônica.'),
(8, '1º Ano', 'LÍNGUA PORTUGUESA', 'SER005', 'portugues', 2.0, 2, 'Lê palavra formada por sílaba não canônica.'),
(8, '1º Ano', 'LÍNGUA PORTUGUESA', 'SER005', 'portugues', 3.0, 3, 'Lê frases.'),
(8, '1º Ano', 'LÍNGUA PORTUGUESA', 'SER005', 'portugues', 4.0, 4, 'Escreve o nome completo'),
(8, '1º Ano', 'LÍNGUA PORTUGUESA', 'SER005', 'portugues', 5.0, 5, 'Identifica informações explícitas em texto ouvido.'),
(8, '1º Ano', 'LÍNGUA PORTUGUESA', 'SER005', 'portugues', 6.0, 6, 'Reconhece o assunto do texto.'),
(8, '1º Ano', 'LÍNGUA PORTUGUESA', 'SER005', 'portugues', 7.0, 7, 'Reconhece o gênero textual.'),
(8, '1º Ano', 'LÍNGUA PORTUGUESA', 'SER005', 'portugues', 8.0, 8, 'Reconhece a finalidade do texto.'),
(8, '1º Ano', 'LÍNGUA PORTUGUESA', 'SER005', 'portugues', 9.0, 9, 'Escreve palavras obedecendo aos princípios da ortografia.'),
(8, '1º Ano', 'MATEMÁTICA', 'SER005', 'matematica', 1.0, 1, 'Escrever números convencionalmente, compreendendo o princípio de valor posicional dos números no Sistema de Numeração Decimal (SND).'),
(9, '1º Ano', 'MATEMÁTICA', 'SER005', 'matematica', 2.0, 2, 'Lê números entre 0 e 50.'),
(9, '1º Ano', 'MATEMÁTICA', 'SER005', 'matematica', 3.0, 3, 'Escreve números entre 0 e 50, em ordem ascendente.'),
(9, '1º Ano', 'MATEMÁTICA', 'SER005', 'matematica', 4.0, 4, 'Associa a denominação do número a sua respectiva representação simbólica (em torno de 30 elementos).'),
(9, '1º Ano', 'MATEMÁTICA', 'SER005', 'matematica', 5.0, 5, 'Calcula fatos básicos da adição, com a ideia de juntar, registrando os resultados numericamente.'),
(9, '1º Ano', 'MATEMÁTICA', 'SER005', 'matematica', 6.0, 6, 'Calcula fatos básicos da adição, com a ideia de acrescentar, registrando os resultados numericamente.'),
(9, '1º Ano', 'MATEMÁTICA', 'SER005', 'matematica', 7.0, 7, 'Calcula fatos básicos da subtração, com a ideia de tirar, registrando os resultados numericamente.'),
(9, '1º Ano', 'MATEMÁTICA', 'SER005', 'matematica', 8.0, 8, 'Calcula fatos básicos da subtração, com a ideia de completar, registrando os resultados numericamente.'),
(9, '1º Ano', 'MATEMÁTICA', 'SER005', 'matematica', 9.0, 9, 'Calcula fatos básicos da subtração, com a ideia de comparar, registrando os resultados numericamente.'),
(9, '2º Ano', 'CIÊNCIAS', 'SER006', 'ciencias', 1.0, 1, 'Relaciona os animais ao ambiente que, naturalmente, se desenvolvem.'),
(9, '2º Ano', 'CIÊNCIAS', 'SER006', 'ciencias', 2.0, 2, 'Identifica as principais partes de uma planta (raiz, caule, folhas, flores e frutos).'),
(9, '2º Ano', 'CIÊNCIAS', 'SER006', 'ciencias', 3.0, 3, 'Relaciona as partes das plantas com suas funções.'),
(9, '2º Ano', 'CIÊNCIAS', 'SER006', 'ciencias', 4.0, 4, 'Identifica as etapas do ciclo de vida de plantas.'),
(9, '2º Ano', 'CIÊNCIAS', 'SER006', 'ciencias', 5.0, 5, 'Relaciona as plantas ao ambiente em que, naturalmente, se desenvolvem.'),
(9, '2º Ano', 'GEOGRAFIA', 'SER006', 'geografia', 1.0, 1, 'Localiza, no espaço da sala de aula, a posição de diferentes objetos e/ou pessoas utilizando os referenciais espaciais (frente e atrás, esquerda e direita, em cima e embaixo, dentro e fora).'),
(10, '2º Ano', 'GEOGRAFIA', 'SER006', 'geografia', 2.0, 2, 'Reconhece, em fotografias, os tipos de visão (vertical, oblíqua ou frontal) em que foram representados objetos do cotidiano.'),
(10, '2º Ano', 'GEOGRAFIA', 'SER006', 'geografia', 3.0, 3, 'Identifica diferentes formas de representação.'),
(10, '2º Ano', 'LÍNGUA PORTUGUESA', 'SER006', 'portugues', 1.0, 1, 'Lê palavra formada por sílaba canônica.'),
(10, '2º Ano', 'LÍNGUA PORTUGUESA', 'SER006', 'portugues', 2.0, 2, 'Lê palavra formada por sílaba não canônica.'),
(10, '2º Ano', 'LÍNGUA PORTUGUESA', 'SER006', 'portugues', 3.0, 3, 'Lê texto em voz alta fluência.'),
(10, '2º Ano', 'LÍNGUA PORTUGUESA', 'SER006', 'portugues', 4.0, 4, 'Escreve o nome completo'),
(10, '2º Ano', 'LÍNGUA PORTUGUESA', 'SER006', 'portugues', 5.0, 5, 'Identifica informações explícitas em texto ouvido.'),
(10, '2º Ano', 'LÍNGUA PORTUGUESA', 'SER006', 'portugues', 6.0, 6, 'Reconhece o assunto do texto.'),
(10, '2º Ano', 'LÍNGUA PORTUGUESA', 'SER006', 'portugues', 7.0, 7, 'Reconhece o gênero textual.'),
(10, '2º Ano', 'LÍNGUA PORTUGUESA', 'SER006', 'portugues', 8.0, 8, 'Reconhece a finalidade do texto.'),
(10, '2º Ano', 'LÍNGUA PORTUGUESA', 'SER006', 'portugues', 9.0, 9, 'Escreve palavras obedecendo aos princípios da ortografia.'),
(10, '2º Ano', 'MATEMÁTICA', 'SER006', 'matematica', 1.0, 1, 'Escrever números convencionalmente, compreendendo o princípio de valor posicional dos números no Sistema de Numeração Decimal (SND).'),
(11, '2º Ano', 'MATEMÁTICA', 'SER006', 'matematica', 2.0, 2, 'Lê números entre 0 e 500.'),
(11, '2º Ano', 'MATEMÁTICA', 'SER006', 'matematica', 3.0, 3, 'Escreve números entre 0 e 500, em ordem ascendente.'),
(11, '2º Ano', 'MATEMÁTICA', 'SER006', 'matematica', 4.0, 4, 'Reconhece que uma semana é composta por 7 dias.'),
(11, '2º Ano', 'MATEMÁTICA', 'SER006', 'matematica', 5.0, 5, 'Nomeia os dias da semana.'),
(11, '2º Ano', 'MATEMÁTICA', 'SER006', 'matematica', 6.0, 6, 'Identifica a localização de pessoas ou objetos no espaço a partir de pontos de referência, utilizando os termos direita e esquerda, à frente de.'),
(11, '2º Ano', 'MATEMÁTICA', 'SER006', 'matematica', 7.0, 7, 'Calcula fatos básicos da adição, registrando os resultados numericamente.'),
(11, '2º Ano', 'MATEMÁTICA', 'SER006', 'matematica', 8.0, 8, 'Calcula fatos básicos da subtração, registrando os resultados numericamente.'),
(11, '2º Ano', 'MATEMÁTICA', 'SER006', 'matematica', 9.0, 9, 'Reconhece os instrumentos de medida padronizados mais usuais e a sua função social (régua, fita métrica, trena, balança e outros).'),
(11, '3º Ano', 'CIÊNCIAS', 'SER007', 'ciencias', 1.0, 1, 'Reconhece características do modo como os animais se deslocam.'),
(11, '3º Ano', 'CIÊNCIAS', 'SER007', 'ciencias', 2.0, 2, 'Reconhece características do modo como os animais se alimentam.'),
(11, '3º Ano', 'CIÊNCIAS', 'SER007', 'ciencias', 3.0, 3, 'Reconhece características do modo como os animais se reproduzem.'),
(11, '3º Ano', 'CIÊNCIAS', 'SER007', 'ciencias', 4.0, 4, 'Identifica fases do ciclo de vida de animais.'),
(11, '3º Ano', 'CIÊNCIAS', 'SER007', 'ciencias', 5.0, 5, 'Identifica alterações do corpo de animais que passam por metamorfose.'),
(11, '3º Ano', 'CIÊNCIAS', 'SER007', 'ciencias', 6.0, 6, 'Identifica o corpo humano em suas diferentes fases de desenvolvimento.'),
(11, '3º Ano', 'GEOGRAFIA', 'SER007', 'geografia', 1.0, 1, 'Identifica diferentes tipos de paisagem na superfície terrestre.'),
(11, '3º Ano', 'GEOGRAFIA', 'SER007', 'geografia', 2.0, 2, 'Identifica elementos que compõem a paisagem natural e cultural.'),
(11, '3º Ano', 'LÍNGUA PORTUGUESA', 'SER007', 'portugues', 1.0, 1, 'Lê texto em voz alta com fluência.'),
(11, '3º Ano', 'LÍNGUA PORTUGUESA', 'SER007', 'portugues', 2.0, 2, 'Reconhece o assunto do texto lido'),
(11, '3º Ano', 'LÍNGUA PORTUGUESA', 'SER007', 'portugues', 3.0, 3, 'Reconhece o gênero textual.'),
(11, '3º Ano', 'LÍNGUA PORTUGUESA', 'SER007', 'portugues', 4.0, 4, 'Identifica a finalidade do texto.'),
(11, '3º Ano', 'LÍNGUA PORTUGUESA', 'SER007', 'portugues', 5.0, 5, 'Identifica informações explícitas em texto lido.'),
(11, '3º Ano', 'LÍNGUA PORTUGUESA', 'SER007', 'portugues', 6.0, 6, 'Infere uma informação do texto lido.'),
(11, '3º Ano', 'LÍNGUA PORTUGUESA', 'SER007', 'portugues', 7.0, 7, 'Escreve o nome completo.'),
(11, '3º Ano', 'LÍNGUA PORTUGUESA', 'SER007', 'portugues', 8.0, 8, 'Produz, individualmente, conto, considerando os elementos da narrativa.'),
(12, '3º Ano', 'MATEMÁTICA', 'SER007', 'matematica', 1.0, 1, 'Escrever números convencionalmente, compreendendo o princípio de valor posicional dos números no Sistema de Numeração Decimal (SND).'),
(12, '3º Ano', 'MATEMÁTICA', 'SER007', 'matematica', 2.0, 2, 'Lê números entre 1 e 5 000.'),
(12, '3º Ano', 'MATEMÁTICA', 'SER007', 'matematica', 3.0, 3, 'Escreve números entre 1 e 5 000.'),
(12, '3º Ano', 'MATEMÁTICA', 'SER007', 'matematica', 4.0, 4, 'Calcula adição com números de 2 ordens, sem agrupamento, utilizando o algoritmo.'),
(12, '3º Ano', 'MATEMÁTICA', 'SER007', 'matematica', 5.0, 5, 'Calcula adição com números de 3 ordens, sem agrupamento, utilizando o algoritmo.'),
(12, '3º Ano', 'MATEMÁTICA', 'SER007', 'matematica', 6.0, 6, 'Resolve situações-problema de adição que demandam a ideia de juntar, com registro por meio de algoritmo.'),
(12, '3º Ano', 'MATEMÁTICA', 'SER007', 'matematica', 7.0, 7, 'Resolve situações-problema de adição, que demandam a ideia de acrescentar, com registro por meio de algoritmo.'),
(12, '4º Ano', 'CIÊNCIAS', 'SER008', 'ciencias', 1.0, 1, 'Identifica o papel dos fungos e bactérias no processo de decomposição.'),
(12, '4º Ano', 'CIÊNCIAS', 'SER008', 'ciencias', 2.0, 2, 'Relaciona a atuação de microrganismos a algumas doenças humanas.'),
(12, '4º Ano', 'CIÊNCIAS', 'SER008', 'ciencias', 3.0, 3, 'Identifica atitudes e medidas para prevenir doenças transmitidas por microrganismos.'),
(12, '4º Ano', 'CIÊNCIAS', 'SER008', 'ciencias', 4.0, 4, 'Reconhece a importância econômica e ecológica de diferentes microrganismos.'),
(13, '4º Ano', 'EDUCAÇÃO DIGITAL E COMPUTAÇÃO (ROBÓTICA)', 'SER008', 'Computacao_robotica', 1.0, 1, 'Cria e simula algoritmos utilizando sequência, repetição e condição.'),
(13, '4º Ano', 'EDUCAÇÃO DIGITAL E COMPUTAÇÃO (ROBÓTICA)', 'SER008', 'Computacao_robotica', 2.0, 2, 'Reconhece formas de organização e representação de dados (listas, grafos e matrizes).'),
(13, '4º Ano', 'GEOGRAFIA', 'SER008', 'geografia', 1.0, 1, 'Localiza a Unidade de Federação - UF onde vive no mapa do Brasil, bem como sua capital.'),
(13, '4º Ano', 'GEOGRAFIA', 'SER008', 'geografia', 2.0, 2, 'Reconhece diferentes tipos de divisão política dos territórios (estados, regiões, regiões metropolitanas, municípios, bairros).'),
(13, '4º Ano', 'GEOGRAFIA', 'SER008', 'geografia', 3.0, 3, 'Identifica os países que fazem fronteira com a Unidade de Federação onde vive no mapa, bem como a fronteira marítima.'),
(13, '4º Ano', 'LÍNGUA PORTUGUESA', 'SER008', 'portugues', 1.0, 1, 'Lê texto em voz alta com fluência.'),
(13, '4º Ano', 'LÍNGUA PORTUGUESA', 'SER008', 'portugues', 2.0, 2, 'Reconhece o assunto do texto.'),
(13, '4º Ano', 'LÍNGUA PORTUGUESA', 'SER008', 'portugues', 3.0, 3, 'Reconhece o gênero textual.'),
(13, '4º Ano', 'LÍNGUA PORTUGUESA', 'SER008', 'portugues', 4.0, 4, 'Identifica a finalidade de um texto.'),
(13, '4º Ano', 'LÍNGUA PORTUGUESA', 'SER008', 'portugues', 5.0, 5, 'Localiza informações explícitas em texto.'),
(13, '4º Ano', 'LÍNGUA PORTUGUESA', 'SER008', 'portugues', 6.0, 6, 'Infere o sentido de palavras ou expressões desconhecidas em textos, com base no contexto da frase ou do texto.'),
(13, '4º Ano', 'LÍNGUA PORTUGUESA', 'SER008', 'portugues', 7.0, 7, 'Infere informações implícitas nos textos lidos.'),
(13, '4º Ano', 'LÍNGUA PORTUGUESA', 'SER008', 'portugues', 8.0, 8, 'Estabelece relações entre partes de um texto, identificando substituições lexicais (de substantivo por sinônimos) ou pronominais (uso de pronomes anafóricos – pessoais, possessivos, demonstrativos) que contribuem para a continuidade do texto.'),
(13, '4º Ano', 'LÍNGUA PORTUGUESA', 'SER008', 'portugues', 9.0, 9, 'Grafa palavras utilizando regras de correspondência fonema-grafema regulares diretas e contextuais (palavras terminadas em -ação, -essão ou -issão, -aço ou -aça).'),
(13, '4º Ano', 'LÍNGUA PORTUGUESA', 'SER008', 'portugues', 10.0, 10, 'Produz notícias sobre fatos ocorridos no universo escolar.'),
(13, '4º Ano', 'LÍNGUA PORTUGUESA', 'SER008', 'portugues', 11.0, 11, 'Utilizar adequadamente os sinais de pontuação na escrita de textos.'),
(13, '4º Ano', 'MATEMÁTICA', 'SER008', 'matematica', 1.0, 1, 'Ler números naturais entre 1 e 30 000.'),
(13, '4º Ano', 'MATEMÁTICA', 'SER008', 'matematica', 2.0, 2, 'Escrever números naturais entre 1 e 30 000.'),
(13, '4º Ano', 'MATEMÁTICA', 'SER008', 'matematica', 3.0, 3, 'Identifica eventos aleatórios cotidianos.'),
(13, '4º Ano', 'MATEMÁTICA', 'SER008', 'matematica', 4.0, 4, 'Lê dados contidos em tabela simples e de dupla entrada.'),
(13, '4º Ano', 'MATEMÁTICA', 'SER008', 'matematica', 5.0, 5, 'Calcular multiplicação até a unidade de milhar no multiplicando e unidade no multiplicador.'),
(13, '4º Ano', 'MATEMÁTICA', 'SER008', 'matematica', 6.0, 6, 'Resolve situações-problema de multiplicação com a ideia de configuração retangular.'),
(13, '4º Ano', 'MATEMÁTICA', 'SER008', 'matematica', 7.0, 7, 'Resolve situações-problema de multiplicação que demandam a ideia de comparação entre razões (proporcionalidade), com registro por meio de algoritmo.'),
(14, '4º Ano', 'MATEMÁTICA', 'SER008', 'matematica', 8.0, 8, 'Resolve situações-problema de multiplicação que demandam a ideia de análise combinatória, com registro por meio de algoritmo.'),
(14, '4º Ano', 'MATEMÁTICA', 'SER008', 'matematica', 9.0, 9, 'Calcula a duração de um intervalo de tempo.'),
(14, '4º Ano', 'MATEMÁTICA', 'SER008', 'matematica', 10.0, 10, 'Identifica a temperatura máxima e mínima diárias em sua cidade, da região ou estado.'),
(14, '5º Ano', 'CIÊNCIAS', 'SER009', 'ciencias', 1.0, 1, 'Relaciona os diferentes tipos de nutrientes à sua função no organismo.'),
(14, '5º Ano', 'CIÊNCIAS', 'SER009', 'ciencias', 2.0, 2, 'Identifica hábitos saudáveis de alimentação.'),
(14, '5º Ano', 'CIÊNCIAS', 'SER009', 'ciencias', 3.0, 3, 'Reconhece os níveis de organização do corpo humano (células, tecidos, órgãos, sistemas e organismos).'),
(14, '5º Ano', 'EDUCAÇÃO DIGITAL E COMPUTAÇÃO (ROBÓTICA)', 'SER009', 'Computacao_robotica', 1.0, 1, 'Cria e simula algoritmos utilizando sequência, repetição e condição.'),
(14, '5º Ano', 'EDUCAÇÃO DIGITAL E COMPUTAÇÃO (ROBÓTICA)', 'SER009', 'Computacao_robotica', 2.0, 2, 'Reconhece formas de organização e representação de dados (listas, grafos e matrizes).'),
(14, '5º Ano', 'GEOGRAFIA', 'SER009', 'geografia', 1.0, 1, 'Reconhece as características do espaço urbano brasileiro.'),
(14, '5º Ano', 'GEOGRAFIA', 'SER009', 'geografia', 2.0, 2, 'Reconhece os diversos usos do espaço urbano.'),
(14, '5º Ano', 'GEOGRAFIA', 'SER009', 'geografia', 3.0, 3, 'Compreende o processo de urbanização.'),
(14, '5º Ano', 'GEOGRAFIA', 'SER009', 'geografia', 4.0, 4, 'Identifica transformações na paisagem a partir de diferentes representações.'),
(14, '5º Ano', 'GEOGRAFIA', 'SER009', 'geografia', 5.0, 5, 'Analisa diferentes mapas temáticos.'),
(14, '5º Ano', 'GEOGRAFIA', 'SER009', 'geografia', 6.0, 6, 'Compreende a ocorrência de problemas ambientais característicos de áreas rurais.'),
(14, '5º Ano', 'GEOGRAFIA', 'SER009', 'geografia', 7.0, 7, 'Compreende a ocorrência de problemas ambientais característicos de áreas urbanas.'),
(14, '5º Ano', 'LÍNGUA PORTUGUESA', 'SER009', 'portugues', 1.0, 1, 'Lê texto em voz alta com fluência.'),
(14, '5º Ano', 'LÍNGUA PORTUGUESA', 'SER009', 'portugues', 2.0, 2, 'Identifica o assunto do texto lido.'),
(14, '5º Ano', 'LÍNGUA PORTUGUESA', 'SER009', 'portugues', 3.0, 3, 'Identifica o gênero textual.'),
(14, '5º Ano', 'LÍNGUA PORTUGUESA', 'SER009', 'portugues', 4.0, 4, 'Identifica a finalidade de um texto.'),
(14, '5º Ano', 'LÍNGUA PORTUGUESA', 'SER009', 'portugues', 5.0, 5, 'Localiza informações explícitas em texto.'),
(14, '5º Ano', 'LÍNGUA PORTUGUESA', 'SER009', 'portugues', 6.0, 6, 'Infere o sentido de palavras ou expressões desconhecidas em textos, com base no contexto da frase ou do texto.'),
(14, '5º Ano', 'LÍNGUA PORTUGUESA', 'SER009', 'portugues', 7.0, 7, 'Infere informações implícitas nos textos lidos.'),
(15, '5º Ano', 'LÍNGUA PORTUGUESA', 'SER009', 'portugues', 8.0, 8, 'Estabelece relações entre partes de um texto, identificando substituições lexicais (de substantivo por sinônimos) ou pronominais (uso de pronomes anafóricos – pessoais, possessivos, demonstrativos) que contribuem para a continuidade do texto.'),
(15, '5º Ano', 'LÍNGUA PORTUGUESA', 'SER009', 'portugues', 9.0, 9, 'Escreve palavras com som da letra S, mas grafadas com as letras S, C, X, SS, Z, Ç, SÇ, SC, XC.'),
(15, '5º Ano', 'LÍNGUA PORTUGUESA', 'SER009', 'portugues', 10.0, 10, 'Produzir, individualmente, notícia.'),
(15, '5º Ano', 'LÍNGUA PORTUGUESA', 'SER009', 'portugues', 11.0, 11, 'Utilizar adequadamente os sinais de pontuação na escrita de textos.'),
(15, '5º Ano', 'MATEMÁTICA', 'SER009', 'matematica', 1.0, 1, 'Descreve a localização de pessoas ou objetos no espaço, por meio de mapas e coordenadas geográficas.'),
(15, '5º Ano', 'MATEMÁTICA', 'SER009', 'matematica', 2.0, 2, 'Calcula adição com números de até 6 ordens, sem e com agrupamento.'),
(15, '5º Ano', 'MATEMÁTICA', 'SER009', 'matematica', 3.0, 3, 'Calcula subtração com números de até 6 ordens, sem e com desagrupamento.'),
(15, '5º Ano', 'MATEMÁTICA', 'SER009', 'matematica', 4.0, 4, 'Calcula multiplicação até a centena de milhar no multiplicando e unidade no multiplicador.'),
(15, '5º Ano', 'MATEMÁTICA', 'SER009', 'matematica', 5.0, 5, 'Lê números fracionários.'),
(15, '5º Ano', 'MATEMÁTICA', 'SER009', 'matematica', 6.0, 6, 'Escreve números fracionários.'),
(15, '5º Ano', 'MATEMÁTICA', 'SER009', 'matematica', 7.0, 7, 'Representa frações na reta numérica.'),
(12, '3º Ano', 'LÍNGUA PORTUGUESA', 'SER007', 'portugues', 9.0, 9, 'Grafa palavras utilizando regras de correspondência fonema-grafema regulares diretas e contextuais (palavras com r/rr. s/ss).');

-- Resolve por códigos e contabiliza cada referência. Componentes não têm UNIQUE no schema,
-- então a validação exige exatamente uma correspondência por código.
DROP TEMPORARY TABLE IF EXISTS tmp_habilidades_resolvidas;
CREATE TEMPORARY TABLE tmp_habilidades_resolvidas (
    pagina_origem tinyint unsigned NOT NULL,
    serie_origem varchar(32) NOT NULL,
    componente_origem varchar(128) NOT NULL,
    serie_codigo varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    componente_codigo varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    ordem_origem decimal(5,1) NOT NULL,
    numero_origem smallint unsigned NULL,
    texto text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    serie_id bigint unsigned NULL,
    serie_matches bigint unsigned NOT NULL,
    componente_curricular_id bigint unsigned NULL,
    componente_matches bigint unsigned NOT NULL,
    INDEX idx_tmp_hab_resolvida (serie_id, componente_curricular_id)
) ENGINE=InnoDB;

INSERT INTO tmp_habilidades_resolvidas
SELECT
    h.*,
    (SELECT MIN(s.id)
       FROM series s
      WHERE BINARY s.codigo = BINARY h.serie_codigo) AS serie_id,
    (SELECT COUNT(*)
       FROM series s
      WHERE BINARY s.codigo = BINARY h.serie_codigo) AS serie_matches,
    (SELECT MIN(c.id)
       FROM componentes_curriculares c
      WHERE BINARY c.codigo = BINARY h.componente_codigo) AS componente_curricular_id,
    (SELECT COUNT(*)
       FROM componentes_curriculares c
      WHERE BINARY c.codigo = BINARY h.componente_codigo) AS componente_matches
FROM tmp_habilidades_2tri_2026 h;

DROP TEMPORARY TABLE IF EXISTS tmp_pares_habilidades;
CREATE TEMPORARY TABLE tmp_pares_habilidades (
    serie_id bigint unsigned NOT NULL,
    componente_curricular_id bigint unsigned NOT NULL,
    PRIMARY KEY (serie_id, componente_curricular_id)
) ENGINE=InnoDB;

INSERT INTO tmp_pares_habilidades
SELECT DISTINCT serie_id, componente_curricular_id
FROM tmp_habilidades_resolvidas
WHERE serie_matches = 1 AND componente_matches = 1;

SET @tipo_parecer_matches := (
    SELECT COUNT(*)
    FROM tipos_avaliacao
    WHERE BINARY nome = BINARY 'Parecer' AND status = 1
);

SET @tipo_parecer_id := (
    SELECT MIN(id)
    FROM tipos_avaliacao
    WHERE BINARY nome = BINARY 'Parecer' AND status = 1
);

SET @alternativas_ativas := (
    SELECT COUNT(*)
    FROM alternativas
    WHERE tipo_avaliacao_id = @tipo_parecer_id AND status = 1
);

SET @textos_vazios := (
    SELECT COUNT(*)
    FROM tmp_habilidades_2tri_2026
    WHERE CHAR_LENGTH(TRIM(texto)) = 0
);

SET @linhas_mapa := (SELECT COUNT(*) FROM tmp_habilidades_2tri_2026);
SET @pares_mapa := (SELECT COUNT(*) FROM tmp_pares_habilidades);

SET @duplicatas_mapa := (
    SELECT COUNT(*)
    FROM (
        SELECT serie_codigo, componente_codigo, texto
        FROM tmp_habilidades_2tri_2026
        GROUP BY serie_codigo, componente_codigo, texto
        HAVING COUNT(*) > 1
    ) duplicatas
);

SET @referencias_invalidas := (
    SELECT COUNT(*)
    FROM tmp_habilidades_resolvidas
    WHERE serie_matches <> 1 OR componente_matches <> 1
);

SET @pautas_correspondentes_duplicadas := (
    SELECT COUNT(*)
    FROM (
        SELECT p.tipo_avaliacao_id, p.serie_id, p.componente_curricular_id, p.texto
        FROM pautas p
        JOIN tmp_habilidades_resolvidas h
          ON h.serie_id = p.serie_id
         AND h.componente_curricular_id = p.componente_curricular_id
         AND BINARY h.texto = BINARY p.texto
        WHERE p.tipo_avaliacao_id = @tipo_parecer_id
          AND p.status = 1
        GROUP BY p.tipo_avaliacao_id, p.serie_id, p.componente_curricular_id, p.texto
        HAVING COUNT(*) > 1
    ) duplicatas
);

SET @avaliacao_alvo_invalida := IF(
    @avaliacao_alvo_id IS NULL,
    0,
    IF(EXISTS (
        SELECT 1
        FROM avaliacoes a
        WHERE a.id = @avaliacao_alvo_id
          AND a.tipo_avaliacao_id = @tipo_parecer_id
          AND a.status = 'ativa'
    ), 0, 1)
);

DROP TEMPORARY TABLE IF EXISTS tmp_avaliacoes_alvo;
CREATE TEMPORARY TABLE tmp_avaliacoes_alvo (
    avaliacao_id bigint unsigned NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

INSERT INTO tmp_avaliacoes_alvo
SELECT DISTINCT a.id AS avaliacao_id
FROM avaliacoes a
JOIN avaliacao_serie avs
  ON avs.avaliacao_id = a.id
JOIN avaliacao_componente avc
  ON avc.avaliacao_id = a.id
JOIN tmp_pares_habilidades par
  ON par.serie_id = avs.serie_id
 AND par.componente_curricular_id = avc.componente_curricular_id
WHERE a.tipo_avaliacao_id = @tipo_parecer_id
  AND a.status = 'ativa'
  AND @avaliacao_alvo_id IS NOT NULL
  AND a.id = @avaliacao_alvo_id;

-- Uma pauta já utilizada não pode ter seu conjunto efetivo de alternativas alterado
-- por SQL cru. Pautas novas ou ainda sem uso serão normalizadas mais abaixo.
SET @alternativas_divergentes_em_pautas_usadas := (
    SELECT COUNT(DISTINCT p.id)
    FROM pautas p
    JOIN tmp_habilidades_resolvidas h
      ON h.serie_id = p.serie_id
     AND h.componente_curricular_id = p.componente_curricular_id
     AND BINARY h.texto = BINARY p.texto
    WHERE p.tipo_avaliacao_id = @tipo_parecer_id
      AND p.status = 1
      AND (
          (SELECT COUNT(*)
             FROM alternativa_pauta ap
             JOIN alternativas a ON a.id = ap.alternativa_id
            WHERE ap.pauta_id = p.id
              AND a.status = 1
              AND a.tipo_avaliacao_id = @tipo_parecer_id) <> @alternativas_ativas
          OR EXISTS (
              SELECT 1
              FROM alternativa_pauta ap
              JOIN alternativas a ON a.id = ap.alternativa_id
              WHERE ap.pauta_id = p.id
                AND a.status = 1
                AND (a.tipo_avaliacao_id IS NULL OR a.tipo_avaliacao_id <> @tipo_parecer_id)
          )
      )
      AND (
          EXISTS (
              SELECT 1
              FROM avaliacao_pauta ap
              WHERE ap.pauta_id = p.id
                AND (@avaliacao_alvo_id IS NULL OR ap.avaliacao_id <> @avaliacao_alvo_id)
          )
          OR EXISTS (
              SELECT 1
              FROM avaliacao_pauta_alternativa apa
              WHERE apa.pauta_id = p.id
                AND (@avaliacao_alvo_id IS NULL OR apa.avaliacao_id <> @avaliacao_alvo_id)
          )
          OR EXISTS (
              SELECT 1 FROM avaliacao_aluno_documentos d
              WHERE JSON_CONTAINS_PATH(d.payload, 'one', CONCAT('$.pautas."', p.id, '"'))
          )
          OR EXISTS (
              SELECT 1 FROM avaliacao_aluno_documentos_historico hst
              WHERE JSON_CONTAINS_PATH(hst.payload, 'one', CONCAT('$.pautas."', p.id, '"'))
          )
          OR EXISTS (SELECT 1 FROM avaliacao_dashboard_fatos f WHERE f.pauta_id = p.id)
      )
);

-- Pautas ativas do mesmo par que não pertencem à orientação nova.
DROP TEMPORARY TABLE IF EXISTS tmp_pautas_obsoletas;
CREATE TEMPORARY TABLE tmp_pautas_obsoletas (
    pauta_id bigint unsigned NOT NULL PRIMARY KEY,
    serie_id bigint unsigned NOT NULL,
    componente_curricular_id bigint unsigned NOT NULL,
    texto text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB;

INSERT INTO tmp_pautas_obsoletas
SELECT DISTINCT p.id AS pauta_id, p.serie_id, p.componente_curricular_id, p.texto
FROM pautas p
JOIN tmp_pares_habilidades par
  ON par.serie_id = p.serie_id
 AND par.componente_curricular_id = p.componente_curricular_id
WHERE p.tipo_avaliacao_id = @tipo_parecer_id
  AND p.status = 1
  AND NOT EXISTS (
      SELECT 1
      FROM tmp_habilidades_resolvidas h
      WHERE h.serie_id = p.serie_id
        AND h.componente_curricular_id = p.componente_curricular_id
        AND BINARY h.texto = BINARY p.texto
  );

-- Uma referência fora das avaliações-alvo impede a inativação global da pauta.
SET @obsoletas_vinculadas_fora_alvo := (
    SELECT COUNT(DISTINCT o.pauta_id)
    FROM tmp_pautas_obsoletas o
    JOIN avaliacao_pauta ap ON ap.pauta_id = o.pauta_id
    LEFT JOIN tmp_avaliacoes_alvo aa ON aa.avaliacao_id = ap.avaliacao_id
    WHERE aa.avaliacao_id IS NULL
);

SET @obsoletas_em_documentos := (
    SELECT COUNT(DISTINCT o.pauta_id)
    FROM tmp_pautas_obsoletas o
    WHERE EXISTS (
        SELECT 1
        FROM avaliacao_aluno_documentos d
        WHERE JSON_CONTAINS_PATH(
            d.payload,
            'one',
            CONCAT('$.pautas."', o.pauta_id, '"')
        )
    )
);

SET @obsoletas_em_historicos := (
    SELECT COUNT(DISTINCT o.pauta_id)
    FROM tmp_pautas_obsoletas o
    WHERE EXISTS (
        SELECT 1
        FROM avaliacao_aluno_documentos_historico h
        WHERE JSON_CONTAINS_PATH(
            h.payload,
            'one',
            CONCAT('$.pautas."', o.pauta_id, '"')
        )
    )
);

SET @obsoletas_em_fatos := (
    SELECT COUNT(DISTINCT o.pauta_id)
    FROM tmp_pautas_obsoletas o
    JOIN avaliacao_dashboard_fatos f ON f.pauta_id = o.pauta_id
);

SET @obsoletas_em_overrides := (
    SELECT COUNT(DISTINCT o.pauta_id)
    FROM tmp_pautas_obsoletas o
    JOIN avaliacao_pauta_alternativa apa ON apa.pauta_id = o.pauta_id
);

-- Detecta avaliações ativas cujo conjunto precisaria ser alterado.
DROP TEMPORARY TABLE IF EXISTS tmp_avaliacoes_com_mudanca;
CREATE TEMPORARY TABLE tmp_avaliacoes_com_mudanca (
    avaliacao_id bigint unsigned NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

INSERT IGNORE INTO tmp_avaliacoes_com_mudanca (avaliacao_id)
SELECT DISTINCT aa.avaliacao_id
FROM tmp_avaliacoes_alvo aa
JOIN avaliacao_serie avs ON avs.avaliacao_id = aa.avaliacao_id
JOIN avaliacao_componente avc ON avc.avaliacao_id = aa.avaliacao_id
JOIN tmp_habilidades_resolvidas h
  ON h.serie_id = avs.serie_id
 AND h.componente_curricular_id = avc.componente_curricular_id
WHERE NOT EXISTS (
    SELECT 1
    FROM avaliacao_pauta ap
    JOIN pautas p ON p.id = ap.pauta_id
    WHERE ap.avaliacao_id = aa.avaliacao_id
      AND p.tipo_avaliacao_id = @tipo_parecer_id
      AND p.serie_id = h.serie_id
      AND p.componente_curricular_id = h.componente_curricular_id
      AND p.status = 1
      AND BINARY p.texto = BINARY h.texto
);

-- Inserção separada: antecipa exatamente as pautas que serão removidas do pivô,
-- inclusive pautas inativas, divergentes ou fora do escopo da avaliação.
INSERT IGNORE INTO tmp_avaliacoes_com_mudanca (avaliacao_id)
SELECT DISTINCT aa.avaliacao_id
FROM tmp_avaliacoes_alvo aa
JOIN avaliacao_pauta ap ON ap.avaliacao_id = aa.avaliacao_id
JOIN pautas p ON p.id = ap.pauta_id
JOIN tmp_pares_habilidades par
  ON par.serie_id = p.serie_id
 AND par.componente_curricular_id = p.componente_curricular_id
LEFT JOIN avaliacao_serie avs
  ON avs.avaliacao_id = aa.avaliacao_id
 AND avs.serie_id = p.serie_id
LEFT JOIN avaliacao_componente avc
  ON avc.avaliacao_id = aa.avaliacao_id
 AND avc.componente_curricular_id = p.componente_curricular_id
LEFT JOIN tmp_habilidades_resolvidas h
  ON h.serie_id = p.serie_id
 AND h.componente_curricular_id = p.componente_curricular_id
 AND BINARY h.texto = BINARY p.texto
WHERE p.tipo_avaliacao_id = @tipo_parecer_id
  AND (
      p.status <> 1
      OR avs.serie_id IS NULL
      OR avc.componente_curricular_id IS NULL
      OR h.serie_id IS NULL
  );

-- SQL cru não reconstrói documentos/fatos de forma confiável. Se houver dados em uma
-- avaliação que precisaria mudar, o script aborta antes de qualquer mutação.
SET @avaliacoes_com_mudanca_e_dados := (
    SELECT COUNT(DISTINCT m.avaliacao_id)
    FROM tmp_avaliacoes_com_mudanca m
    WHERE EXISTS (
        SELECT 1 FROM avaliacao_aluno_documentos d
        WHERE d.avaliacao_id = m.avaliacao_id
    ) OR EXISTS (
        SELECT 1 FROM avaliacao_aluno_documentos_historico h
        WHERE h.avaliacao_id = m.avaliacao_id
    ) OR EXISTS (
        SELECT 1 FROM avaliacao_dashboard_fatos f
        WHERE f.avaliacao_id = m.avaliacao_id
    )
);

SET @tem_erros :=
      IF(@tipo_parecer_matches = 1, 0, 1)
    + IF(@alternativas_ativas = 8, 0, 1)
    + IF(@linhas_mapa = 257, 0, 1)
    + IF(@pares_mapa = 42, 0, 1)
    + @textos_vazios
    + @duplicatas_mapa
    + @referencias_invalidas
    + @pautas_correspondentes_duplicadas
    + @avaliacao_alvo_invalida
    + @obsoletas_vinculadas_fora_alvo
    + @obsoletas_em_documentos
    + @obsoletas_em_historicos
    + @obsoletas_em_fatos
    + @obsoletas_em_overrides
    + @alternativas_divergentes_em_pautas_usadas
    + @avaliacoes_com_mudanca_e_dados;

-- Diagnóstico prévio. Se total_erros > 0, todos os comandos de escrita abaixo são no-op.
SELECT 'tipo_parecer_matches' AS validacao, @tipo_parecer_matches AS valor
UNION ALL SELECT 'alternativas_ativas', @alternativas_ativas
UNION ALL SELECT 'linhas_unicas_mapa', @linhas_mapa
UNION ALL SELECT 'pares_serie_componente', @pares_mapa
UNION ALL SELECT 'textos_vazios', @textos_vazios
UNION ALL SELECT 'duplicatas_mapa', @duplicatas_mapa
UNION ALL SELECT 'referencias_invalidas', @referencias_invalidas
UNION ALL SELECT 'pautas_correspondentes_duplicadas', @pautas_correspondentes_duplicadas
UNION ALL SELECT 'avaliacao_alvo_invalida', @avaliacao_alvo_invalida
UNION ALL SELECT 'avaliacoes_ativas_alvo', (SELECT COUNT(*) FROM tmp_avaliacoes_alvo)
UNION ALL SELECT 'pautas_obsoletas', (SELECT COUNT(*) FROM tmp_pautas_obsoletas)
UNION ALL SELECT 'obsoletas_vinculadas_fora_alvo', @obsoletas_vinculadas_fora_alvo
UNION ALL SELECT 'obsoletas_em_documentos', @obsoletas_em_documentos
UNION ALL SELECT 'obsoletas_em_historicos', @obsoletas_em_historicos
UNION ALL SELECT 'obsoletas_em_fatos', @obsoletas_em_fatos
UNION ALL SELECT 'obsoletas_em_overrides', @obsoletas_em_overrides
UNION ALL SELECT 'alternativas_divergentes_em_pautas_usadas', @alternativas_divergentes_em_pautas_usadas
UNION ALL SELECT 'avaliacoes_com_mudanca_e_dados', @avaliacoes_com_mudanca_e_dados
UNION ALL SELECT 'total_erros', @tem_erros;

SELECT
    pagina_origem,
    serie_origem,
    componente_origem,
    serie_codigo,
    componente_codigo,
    serie_matches,
    componente_matches
FROM tmp_habilidades_resolvidas
WHERE serie_matches <> 1 OR componente_matches <> 1
ORDER BY pagina_origem, serie_origem, componente_origem;

SET @pautas_ativas_antes := (
    SELECT COUNT(*)
    FROM pautas p
    JOIN tmp_pares_habilidades par
      ON par.serie_id = p.serie_id
     AND par.componente_curricular_id = p.componente_curricular_id
    WHERE p.tipo_avaliacao_id = @tipo_parecer_id AND p.status = 1
);

-- Mantém o catálogo série×componente coerente; não cria vínculos de professor/turma.
INSERT INTO serie_componente_curricular (serie_id, componente_curricular_id)
SELECT par.serie_id, par.componente_curricular_id
FROM tmp_pares_habilidades par
WHERE @tem_erros = 0
  AND NOT EXISTS (
      SELECT 1
      FROM serie_componente_curricular scc
      WHERE scc.serie_id = par.serie_id
        AND scc.componente_curricular_id = par.componente_curricular_id
  );

-- Cria somente as pautas sem correspondência ativa. Uma correspondência inativa não
-- é reativada: ela pode pertencer a uma avaliação histórica e deve manter seu estado.
INSERT INTO pautas
    (tipo_avaliacao_id, texto, componente_curricular_id, serie_id,
     status, created_at, updated_at)
SELECT
    @tipo_parecer_id,
    h.texto,
    h.componente_curricular_id,
    h.serie_id,
    1,
    NOW(),
    NOW()
FROM tmp_habilidades_resolvidas h
WHERE @tem_erros = 0
  AND NOT EXISTS (
      SELECT 1
      FROM pautas p
      WHERE p.tipo_avaliacao_id = @tipo_parecer_id
        AND p.serie_id = h.serie_id
        AND p.componente_curricular_id = h.componente_curricular_id
        AND p.status = 1
        AND BINARY p.texto = BINARY h.texto
  );

-- Resolve os IDs definitivos depois do upsert.
DROP TEMPORARY TABLE IF EXISTS tmp_pautas_desejadas;
CREATE TEMPORARY TABLE tmp_pautas_desejadas (
    serie_id bigint unsigned NOT NULL,
    componente_curricular_id bigint unsigned NOT NULL,
    ordem_origem decimal(5,1) NOT NULL,
    texto text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    pauta_id bigint unsigned NOT NULL,
    PRIMARY KEY (pauta_id),
    INDEX idx_tmp_pauta_desejada_par (serie_id, componente_curricular_id)
) ENGINE=InnoDB;

INSERT INTO tmp_pautas_desejadas
SELECT
    h.serie_id,
    h.componente_curricular_id,
    h.ordem_origem,
    h.texto,
    p.id AS pauta_id
FROM tmp_habilidades_resolvidas h
JOIN pautas p
  ON p.tipo_avaliacao_id = @tipo_parecer_id
 AND p.serie_id = h.serie_id
 AND p.componente_curricular_id = h.componente_curricular_id
 AND BINARY p.texto = BINARY h.texto
WHERE @tem_erros = 0
  AND p.status = 1;

-- Remove alternativas ativas de outro tipo. O guard anterior impede essa alteração
-- quando a pauta divergente já foi utilizada por qualquer avaliação.
DELETE ap
FROM alternativa_pauta ap
JOIN tmp_pautas_desejadas d ON d.pauta_id = ap.pauta_id
JOIN alternativas a ON a.id = ap.alternativa_id
WHERE @tem_erros = 0
  AND a.status = 1
  AND (a.tipo_avaliacao_id IS NULL OR a.tipo_avaliacao_id <> @tipo_parecer_id);

-- Garante as oito alternativas Parecer ativas em cada pauta nova/correta.
INSERT INTO alternativa_pauta (pauta_id, alternativa_id, created_at, updated_at)
SELECT d.pauta_id, a.id, NOW(), NOW()
FROM tmp_pautas_desejadas d
JOIN alternativas a
  ON a.tipo_avaliacao_id = @tipo_parecer_id
 AND a.status = 1
WHERE @tem_erros = 0
  AND NOT EXISTS (
      SELECT 1
      FROM alternativa_pauta ap
      WHERE ap.pauta_id = d.pauta_id
        AND ap.alternativa_id = a.id
  );

-- Conjunto desejado por avaliação, limitado à interseção do escopo já cadastrado.
DROP TEMPORARY TABLE IF EXISTS tmp_avaliacao_pauta_desejada;
CREATE TEMPORARY TABLE tmp_avaliacao_pauta_desejada (
    avaliacao_id bigint unsigned NOT NULL,
    pauta_id bigint unsigned NOT NULL,
    PRIMARY KEY (avaliacao_id, pauta_id)
) ENGINE=InnoDB;

INSERT INTO tmp_avaliacao_pauta_desejada
SELECT DISTINCT
    aa.avaliacao_id,
    d.pauta_id
FROM tmp_avaliacoes_alvo aa
JOIN avaliacao_serie avs ON avs.avaliacao_id = aa.avaliacao_id
JOIN avaliacao_componente avc ON avc.avaliacao_id = aa.avaliacao_id
JOIN tmp_pautas_desejadas d
  ON d.serie_id = avs.serie_id
 AND d.componente_curricular_id = avc.componente_curricular_id
WHERE @tem_erros = 0;

DROP TEMPORARY TABLE IF EXISTS tmp_avaliacao_pauta_remover;
CREATE TEMPORARY TABLE tmp_avaliacao_pauta_remover (
    avaliacao_id bigint unsigned NOT NULL,
    pauta_id bigint unsigned NOT NULL,
    PRIMARY KEY (avaliacao_id, pauta_id)
) ENGINE=InnoDB;

INSERT INTO tmp_avaliacao_pauta_remover
SELECT DISTINCT ap.avaliacao_id, ap.pauta_id
FROM avaliacao_pauta ap
JOIN tmp_avaliacoes_alvo aa ON aa.avaliacao_id = ap.avaliacao_id
JOIN pautas p ON p.id = ap.pauta_id
JOIN tmp_pares_habilidades par
  ON par.serie_id = p.serie_id
 AND par.componente_curricular_id = p.componente_curricular_id
LEFT JOIN tmp_avaliacao_pauta_desejada d
  ON d.avaliacao_id = ap.avaliacao_id
 AND d.pauta_id = ap.pauta_id
WHERE @tem_erros = 0
  AND p.tipo_avaliacao_id = @tipo_parecer_id
  AND d.pauta_id IS NULL;

-- Overrides só fazem sentido enquanto a pauta pertence à avaliação.
DELETE apa
FROM avaliacao_pauta_alternativa apa
JOIN tmp_avaliacao_pauta_remover r
  ON r.avaliacao_id = apa.avaliacao_id
 AND r.pauta_id = apa.pauta_id
WHERE @tem_erros = 0;

DELETE ap
FROM avaliacao_pauta ap
JOIN tmp_avaliacao_pauta_remover r
  ON r.avaliacao_id = ap.avaliacao_id
 AND r.pauta_id = ap.pauta_id
WHERE @tem_erros = 0;

INSERT INTO avaliacao_pauta (avaliacao_id, pauta_id, created_at, updated_at)
SELECT d.avaliacao_id, d.pauta_id, NOW(), NOW()
FROM tmp_avaliacao_pauta_desejada d
WHERE @tem_erros = 0
  AND NOT EXISTS (
      SELECT 1
      FROM avaliacao_pauta ap
      WHERE ap.avaliacao_id = d.avaliacao_id
        AND ap.pauta_id = d.pauta_id
  );

-- Somente agora inativa o catálogo substituído. As validações anteriores garantem
-- que essas pautas não possuem vínculo fora do conjunto seguro nem dados históricos.
UPDATE pautas p
JOIN tmp_pautas_obsoletas o ON o.pauta_id = p.id
SET p.status = 0,
    p.updated_at = NOW()
WHERE @tem_erros = 0
  AND p.status <> 0;

SET @pautas_ativas_depois := (
    SELECT COUNT(*)
    FROM pautas p
    JOIN tmp_pares_habilidades par
      ON par.serie_id = p.serie_id
     AND par.componente_curricular_id = p.componente_curricular_id
    WHERE p.tipo_avaliacao_id = @tipo_parecer_id AND p.status = 1
);

-- Auditoria final. Com @avaliacao_alvo_id = NULL, avaliações_ativas_alvo é 0 por projeto.
-- No Hub de 10/08/2026, a única avaliação ativa é exclusiva da Educação Infantil.
SELECT
    IF(@tem_erros = 0,
       'OK - catálogo corrigido; avaliação explícita sincronizada quando informada',
       'ERRO - nenhuma mutação executada; revise as validações') AS resultado,
    @tem_erros AS total_erros,
    @pautas_ativas_antes AS pautas_ativas_antes,
    @pautas_ativas_depois AS pautas_ativas_depois,
    (SELECT COUNT(*) FROM tmp_pautas_desejadas) AS pautas_corretas,
    IF(@tem_erros = 0, (SELECT COUNT(*) FROM tmp_pautas_obsoletas), 0) AS pautas_inativadas,
    (SELECT COUNT(*) FROM tmp_avaliacoes_alvo) AS avaliacoes_ativas_alvo,
    (SELECT COUNT(*) FROM tmp_avaliacao_pauta_desejada) AS vinculos_avaliacao_desejados,
    (SELECT COUNT(*) FROM tmp_avaliacao_pauta_remover) AS vinculos_avaliacao_removidos;

SELECT
    s.codigo AS serie_codigo,
    c.codigo AS componente_codigo,
    COUNT(*) AS pautas_ativas
FROM pautas p
JOIN series s ON s.id = p.serie_id
JOIN componentes_curriculares c ON c.id = p.componente_curricular_id
JOIN tmp_pares_habilidades par
  ON par.serie_id = p.serie_id
 AND par.componente_curricular_id = p.componente_curricular_id
WHERE p.tipo_avaliacao_id = @tipo_parecer_id
  AND p.status = 1
GROUP BY s.codigo, c.codigo
ORDER BY s.codigo, c.codigo;

SELECT
    m.avaliacao_id,
    CONCAT('php artisan avaliacoes:rebuild-dashboard-facts ', m.avaliacao_id)
        AS comando_pos_commit_se_houver_mudanca
FROM tmp_avaliacoes_com_mudanca m
WHERE @tem_erros = 0
ORDER BY m.avaliacao_id;

COMMIT;

DROP TEMPORARY TABLE IF EXISTS tmp_avaliacao_pauta_remover;
DROP TEMPORARY TABLE IF EXISTS tmp_avaliacao_pauta_desejada;
DROP TEMPORARY TABLE IF EXISTS tmp_pautas_desejadas;
DROP TEMPORARY TABLE IF EXISTS tmp_avaliacoes_com_mudanca;
DROP TEMPORARY TABLE IF EXISTS tmp_pautas_obsoletas;
DROP TEMPORARY TABLE IF EXISTS tmp_avaliacoes_alvo;
DROP TEMPORARY TABLE IF EXISTS tmp_pares_habilidades;
DROP TEMPORARY TABLE IF EXISTS tmp_habilidades_resolvidas;
DROP TEMPORARY TABLE IF EXISTS tmp_habilidades_2tri_2026;
