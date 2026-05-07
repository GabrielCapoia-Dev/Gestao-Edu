-- Generated SQL for importing pautas from Mapa de Pautas.xlsx
-- Source workbook: C:/Users/gabriel.capoia/Downloads/Mapa de Pautas.xlsx
-- Reference dump/schema: C:/Users/gabriel.capoia/Downloads/gestao-edu (1).sql
-- Generated on: 2026-05-07
-- Workbook rows read: 425
-- Unique pautas after deduplication: 341
-- Mapping notes:
-- - tipo_avaliacao: Parecer
-- - alternativas: all active alternatives linked to tipo Parecer
-- - Base Diversificada rows for 1o/2o Ano are mapped to SER016/SER017 (integral series)
-- - SRM rows are mapped to series srm_serie and component srm

SET NAMES utf8mb4;
START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_mapa_pautas;
CREATE TEMPORARY TABLE tmp_mapa_pautas (
  origem varchar(32) NOT NULL,
  linha int NOT NULL,
  turma_planilha varchar(255) NOT NULL,
  componente_planilha varchar(255) NOT NULL,
  serie_codigo varchar(255) NOT NULL,
  componente_codigo varchar(255) NOT NULL,
  texto text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tmp_mapa_pautas
  (origem, linha, turma_planilha, componente_planilha, serie_codigo, componente_codigo, texto)
VALUES
('Pautas', 2, 'Berçário', 'O Eu, O Outro e o Nós', 'SER013', 'eu_outro_nos', '1. Nos momentos de interação e conversa com crianças e adultos, como o bebê reage? Dirige o olhar para quem está falando, sorri, dá gargalhadas, balbucia, estende os braços, entre outras?'),
('Pautas', 3, 'Berçário', 'O Eu, O Outro e o Nós', 'SER013', 'eu_outro_nos', '2. Durante a rotina diária o bebê manifesta incômodos (xixi, cocô, dor), necessidades (sede, fome, sono), sentimentos e emoções (sorriso, choro)?'),
('Pautas', 4, 'Berçário', 'Escuta, fala, pensamento e imaginação', 'SER013', 'escuta_fala_pensamento', '1. Para comunicar desejos, necessidades e sentimentos, o bebê utiliza balbucios, choro, sorriso, gestos, movimentos ou outras formas de expressão?'),
('Pautas', 5, 'Berçário', 'Espaços, tempos, quantidades, relações e transformações', 'SER013', 'espaco_tempo_quantidade', '1. Ao manipular e explorar materiais, objetos e brinquedos o bebê realiza ações de apertar, jogar, bater, empilhar, rolar, abrir/fechar e retirar/colocar em recipientes?'),
('Pautas', 6, 'Berçário', 'Corpo, Gesto e Movimento', 'SER013', 'corpo_gesto_movimento', '1. O bebê explora os espaços da instituição utilizando diversas habilidades corporais como: rastejar, engatinhar, rolar, sentar, subir, descer, ficar em pé, deitar, andar, entre outras?'),
('Pautas', 7, 'Berçário', 'Corpo, Gesto e Movimento', 'SER013', 'corpo_gesto_movimento', '2. Ao observar-se no espelho o bebê interage com sua própria imagem refletida?'),
('Pautas', 8, 'Berçário', 'Traços, sons, cores e formas', 'SER013', 'tracos_sons_cores', '1. O bebê percebe e reage a estímulos sonoros na manipulação de objetos, brinquedos e no ambiente?'),
('Pautas', 9, 'Berçário', 'Traços, sons, cores e formas', 'SER013', 'tracos_sons_cores', '2. O bebê percebe e reage a estímulos visuais na manipulação de objetos, brinquedos e no ambiente?'),
('Pautas', 10, 'Infantil 1', 'O Eu, O Outro e o Nós', 'SER010', 'eu_outro_nos', '1. Nos momentos de interação e conversa com crianças e adultos, como a criança reage? Dirige o olhar para quem está falando, sorri, balbucia, estende os braços, entre outras?'),
('Pautas', 11, 'Infantil 1', 'O Eu, O Outro e o Nós', 'SER010', 'eu_outro_nos', '2. Durante a rotina diária a criança manifesta incômodos (xixi, cocô, dor), necessidades (sede, fome, sono), sentimentos e emoções (sorriso, choro)?'),
('Pautas', 12, 'Infantil 1', 'Escuta, fala, pensamento e imaginação', 'SER010', 'escuta_fala_pensamento', '1. Para comunicar-se, a criança utiliza a linguagem oral, pronunciando sons onomatopeicos ou algumas palavras ou utiliza a linguagem corporal e gestual?'),
('Pautas', 13, 'Infantil 1', 'Escuta, fala, pensamento e imaginação', 'SER010', 'escuta_fala_pensamento', '2. A criança entende comandos simples nas diversas situações da rotina diária como nas brincadeiras, na alimentação, na organização da sala e na higienização?'),
('Pautas', 14, 'Infantil 1', 'Espaços, tempos, quantidades, relações e transformações', 'SER010', 'espaco_tempo_quantidade', '1. Ao manipular e explorar materiais, objetos e brinquedos a criança realiza ações de abrir/fechar, rosquear, jogar, bater, empilhar, rolar, encaixar, lançar e retirar/colocar em recipientes?'),
('Pautas', 15, 'Infantil 1', 'Espaços, tempos, quantidades, relações e transformações', 'SER010', 'espaco_tempo_quantidade', '2. A criança experimenta e explora o espaço através de experiências de deslocamentos de si e dos objetos?'),
('Pautas', 16, 'Infantil 1', 'Corpo, Gesto e Movimento', 'SER010', 'corpo_gesto_movimento', '1. A criança explora os espaços da instituição realizando ações como: equilibrar-se, arrastar, engatinhar, levantar, subir, descer, passar por dentro, por baixo, saltar, rolar, procurar, pegar, entre outras?'),
('Pautas', 17, 'Infantil 1', 'Corpo, Gesto e Movimento', 'SER010', 'corpo_gesto_movimento', '2. Nos momentos de brincadeiras e no dia a dia a criança compreende e realiza comandos como: levantar, sentar, abaixar, subir, descer, dançar, comer, beber, entre outros?'),
('Pautas', 18, 'Infantil 1', 'Corpo, Gesto e Movimento', 'SER010', 'corpo_gesto_movimento', '3. A criança demonstra interesse em experimentar os alimentos que são oferecidos nas refeições?'),
('Pautas', 19, 'Infantil 1', 'Traços, sons, cores e formas', 'SER010', 'tracos_sons_cores', '1. A criança percebe e reage a estímulos sonoros na manipulação de objetos, brinquedos e no ambiente?'),
('Pautas', 20, 'Infantil 1', 'Traços, sons, cores e formas', 'SER010', 'tracos_sons_cores', '2. A criança produz sons com o próprio corpo?'),
('Pautas', 21, 'Infantil 1', 'Traços, sons, cores e formas', 'SER010', 'tracos_sons_cores', '3. A criança percebe e reage a estímulos visuais na manipulação de objetos, brinquedos e no ambiente?'),
('Pautas', 22, 'Infantil 2', 'O Eu, O Outro e o Nós', 'SER011', 'eu_outro_nos', '1. A criança atende ao ser chamada pelo seu nome?'),
('Pautas', 23, 'Infantil 2', 'O Eu, O Outro e o Nós', 'SER011', 'eu_outro_nos', '2. Durante a rotina diária a criança expressa necessidades, emoções e sentimentos que vivencia sinalizando situações positivas ou negativas que experimenta?'),
('Pautas', 24, 'Infantil 2', 'O Eu, O Outro e o Nós', 'SER011', 'eu_outro_nos', '3. Nos momentos da rotina diária a criança interage com adultos e crianças com as quais convive estabelecendo vínculos afetivos?'),
('Pautas', 25, 'Infantil 2', 'Escuta, fala, pensamento e imaginação', 'SER011', 'escuta_fala_pensamento', '1. A criança expressa suas ideias, sentimentos e emoções utilizando a linguagem oral, pronunciando sons onomatopeicos ou algumas palavras ou utiliza a linguagem corporal e gestual?'),
('Pautas', 26, 'Infantil 2', 'Escuta, fala, pensamento e imaginação', 'SER011', 'escuta_fala_pensamento', '2. A criança entende comandos simples nas diversas situações da rotina diária como nas brincadeiras, na alimentação, na organização da sala e na higienização?'),
('Pautas', 27, 'Infantil 2', 'Espaços, tempos, quantidades, relações e transformações', 'SER011', 'espaco_tempo_quantidade', '1. A criança ao observar e manipular objetos e brinquedos para exploração de suas possibilidades, realiza ações de apertar, empilhar, encher, esvaziar, transvasar, rolar, encaixar, fazer afundar, flutuar, soprar, montar, construir, lançar e jogar?'),
('Pautas', 28, 'Infantil 2', 'Espaços, tempos, quantidades, relações e transformações', 'SER011', 'espaco_tempo_quantidade', '2. A criança experimenta e explora o espaço através de experiências de deslocamentos de si e dos objetos?'),
('Pautas', 29, 'Infantil 2', 'Corpo, Gesto e Movimento', 'SER011', 'corpo_gesto_movimento', '1. A criança explora o espaço ao seu redor realizando movimentos como: correr, lançar, galopar, pular, saltar, rolar, arremessar, engatinhar e dançar?'),
('Pautas', 30, 'Infantil 2', 'Corpo, Gesto e Movimento', 'SER011', 'corpo_gesto_movimento', '2. A criança conhece o próprio corpo e algumas de suas partes através do uso e da exploração de suas habilidades?'),
('Pautas', 31, 'Infantil 2', 'Corpo, Gesto e Movimento', 'SER011', 'corpo_gesto_movimento', '3. A criança realiza pequenas ações cotidianas relacionadas à alimentação e higiene para adquirir crescente autonomia?'),
('Pautas', 32, 'Infantil 2', 'Traços, sons, cores e formas', 'SER011', 'tracos_sons_cores', '1. A criança percebe e reage a estímulos sonoros na manipulação de objetos, brinquedos e no ambiente?'),
('Pautas', 33, 'Infantil 2', 'Traços, sons, cores e formas', 'SER011', 'tracos_sons_cores', '2. A criança produz sons com o próprio corpo?'),
('Pautas', 34, 'Infantil 2', 'Traços, sons, cores e formas', 'SER011', 'tracos_sons_cores', '3. A criança percebe e reage a estímulos visuais na manipulação de objetos, brinquedos e no ambiente?'),
('Pautas', 35, 'Infantil 3', 'O Eu, O Outro e o Nós', 'SER012', 'eu_outro_nos', '1. A criança atende ao ser chamada pelo seu nome?'),
('Pautas', 36, 'Infantil 3', 'O Eu, O Outro e o Nós', 'SER012', 'eu_outro_nos', '2. Durante a rotina diária a criança expressa necessidades, emoções e sentimentos que vivencia sinalizando situações positivas ou negativas que experimenta?'),
('Pautas', 37, 'Infantil 3', 'O Eu, O Outro e o Nós', 'SER012', 'eu_outro_nos', '3. Nos momentos da rotina diária a criança interage com adultos e crianças com as quais convive estabelecendo vínculos afetivos?'),
('Pautas', 38, 'Infantil 3', 'Escuta, fala, pensamento e imaginação', 'SER012', 'escuta_fala_pensamento', '1. A criança expressa suas ideias, sentimentos e emoções utilizando a linguagem oral, pronunciando sons onomatopeicos ou algumas palavras, frases ou utiliza a linguagem corporal e gestual?'),
('Pautas', 39, 'Infantil 3', 'Escuta, fala, pensamento e imaginação', 'SER012', 'escuta_fala_pensamento', '2. Nas diversas situações da rotina diária, como nas brincadeiras, na alimentação, na organização da sala, na higienização, a criança entende comandos simples?'),
('Pautas', 40, 'Infantil 3', 'Escuta, fala, pensamento e imaginação', 'SER012', 'escuta_fala_pensamento', '3. A criança registra suas vivências desenhando como uma forma de comunicação gráfica?'),
('Pautas', 41, 'Infantil 3', 'Escuta, fala, pensamento e imaginação', 'SER012', 'escuta_fala_pensamento', '4. A criança observa e manuseia diferentes portadores textuais?'),
('Pautas', 42, 'Infantil 3', 'Espaços, tempos, quantidades, relações e transformações', 'SER012', 'espaco_tempo_quantidade', '1. A criança ao observar e manipular objetos e brinquedos para exploração de suas possibilidades, realiza ações de apertar, empilhar, encher, esvaziar, transvasar, rolar, encaixar, fazer afundar, flutuar, soprar, montar, construir, lançar e jogar?'),
('Pautas', 43, 'Infantil 3', 'Espaços, tempos, quantidades, relações e transformações', 'SER012', 'espaco_tempo_quantidade', '2. A criança desloca-se no espaço por meio da exploração de relações específicas como: dentro e fora, em cima e embaixo, entre e do lado, à frente e atrás?'),
('Pautas', 44, 'Infantil 3', 'Corpo, Gesto e Movimento', 'SER012', 'corpo_gesto_movimento', '1. A criança explora o espaço ao seu redor realizando movimentos como: correr, lançar, galopar, pular, saltar, rolar, arremessar, engatinhar e dançar?'),
('Pautas', 45, 'Infantil 3', 'Corpo, Gesto e Movimento', 'SER012', 'corpo_gesto_movimento', '2. A criança conhece o próprio corpo e identifica algumas de suas partes através do uso e da exploração de suas habilidades?'),
('Pautas', 46, 'Infantil 3', 'Corpo, Gesto e Movimento', 'SER012', 'corpo_gesto_movimento', '3. A criança realiza pequenas ações cotidianas relacionadas à alimentação e higiene para adquirir crescente autonomia?'),
('Pautas', 47, 'Infantil 3', 'Traços, sons, cores e formas', 'SER012', 'tracos_sons_cores', '1. A criança percebe e reage a estímulos sonoros na manipulação de objetos, brinquedos e no ambiente?'),
('Pautas', 48, 'Infantil 3', 'Traços, sons, cores e formas', 'SER012', 'tracos_sons_cores', '2. A criança produz sons com o próprio corpo?'),
('Pautas', 49, 'Infantil 3', 'Traços, sons, cores e formas', 'SER012', 'tracos_sons_cores', '3. A criança percebe e reage a estímulos visuais na manipulação de objetos, brinquedos e no ambiente?'),
('Pautas', 50, 'Infantil 3', 'Traços, sons, cores e formas', 'SER012', 'tracos_sons_cores', '4. A criança expressa-se por meio de desenho, pintura e modelagem?'),
('Pautas', 51, 'Infantil 4', 'Corpo, Gesto e Movimento', 'SER003', 'corpo_gesto_movimento', '1. Conhece as partes do corpo: olhos, orelha, nariz, mão, pé, cabeça, pescoço, boca, costas e barriga.'),
('Pautas', 52, 'Infantil 4', 'Corpo, Gesto e Movimento', 'SER003', 'corpo_gesto_movimento', '2. Emprega meios para recuperar o equilíbrio após movimentos dos segmentos corporais.'),
('Pautas', 53, 'Infantil 4', 'Corpo, Gesto e Movimento', 'SER003', 'corpo_gesto_movimento', '3. Amplia suas possibilidades de movimento nos brinquedos cantados.'),
('Pautas', 54, 'Infantil 4', 'Corpo, Gesto e Movimento', 'SER003', 'corpo_gesto_movimento', '4. Demonstra controle do uso de seu corpo nas atividades que envolvem as grandes massas musculares.'),
('Pautas', 55, 'Infantil 4', 'Corpo, Gesto e Movimento', 'SER003', 'corpo_gesto_movimento', '5. Orienta o seu corpo no espaço.'),
('Pautas', 56, 'Infantil 4', 'Corpo, Gesto e Movimento', 'SER003', 'corpo_gesto_movimento', '6. Comunica-se através da linguagem corporal (gestos, posturas, imitações, expressões faciais).'),
('Pautas', 57, 'Infantil 4', 'Corpo, Gesto e Movimento', 'SER003', 'corpo_gesto_movimento', '7. Emprega meios para coordenar o sentido da visão com os movimentos dos membros inferiores.'),
('Pautas', 58, 'Infantil 4', 'Corpo, Gesto e Movimento', 'SER003', 'corpo_gesto_movimento', '8. Emprega meios para coordenar o sentido da visão com os movimentos dos membros superiores.'),
('Pautas', 59, 'Infantil 4', 'Corpo, Gesto e Movimento', 'SER003', 'corpo_gesto_movimento', '9. Realiza movimentos específicos com as mãos, aprimorando a coordenação motora fina.'),
('Pautas', 60, 'Infantil 4', 'Corpo, Gesto e Movimento', 'SER003', 'corpo_gesto_movimento', '10. Monta quebra-cabeças com 4 (quatro) peças.'),
('Pautas', 61, 'Infantil 4', 'Escuta, fala, pensamento e imaginação', 'SER003', 'escuta_fala_pensamento', '1. Expressa, por meio da linguagem oral, suas vivências, necessidades e sentimentos.'),
('Pautas', 62, 'Infantil 4', 'Escuta, fala, pensamento e imaginação', 'SER003', 'escuta_fala_pensamento', '2. Ouve histórias com atenção.'),
('Pautas', 63, 'Infantil 4', 'Escuta, fala, pensamento e imaginação', 'SER003', 'escuta_fala_pensamento', '3. Expressa ideias por meio do desenho.'),
('Pautas', 64, 'Infantil 4', 'Escuta, fala, pensamento e imaginação', 'SER003', 'escuta_fala_pensamento', '4. Escreve o próprio nome sem modelo.'),
('Pautas', 65, 'Infantil 4', 'Espaços, tempos, quantidades, relações e transformações', 'SER003', 'espaco_tempo_quantidade', '1. Faz contagem oral sequenciando de 1 a 10.'),
('Pautas', 66, 'Infantil 4', 'Espaços, tempos, quantidades, relações e transformações', 'SER003', 'espaco_tempo_quantidade', '2. Relaciona numeral entre 0 e 5 a sua respectiva quantidade.'),
('Pautas', 67, 'Infantil 4', 'Espaços, tempos, quantidades, relações e transformações', 'SER003', 'espaco_tempo_quantidade', '3. Diferencia números de letras.'),
('Pautas', 68, 'Infantil 4', 'Espaços, tempos, quantidades, relações e transformações', 'SER003', 'espaco_tempo_quantidade', '4. Identifica o que vem antes e depois em uma sequência de objetos.'),
('Pautas', 69, 'Infantil 4', 'Espaços, tempos, quantidades, relações e transformações', 'SER003', 'espaco_tempo_quantidade', '5. Nomeia algumas partes do corpo.'),
('Pautas', 70, 'Infantil 4', 'Espaços, tempos, quantidades, relações e transformações', 'SER003', 'espaco_tempo_quantidade', '6. Reconhece diferentes sensações táteis.'),
('Pautas', 71, 'Infantil 4', 'Espaços, tempos, quantidades, relações e transformações', 'SER003', 'espaco_tempo_quantidade', '7. Reconhece alguns estímulos auditivos.'),
('Pautas', 72, 'Infantil 4', 'O Eu, O Outro e o Nós', 'SER003', 'eu_outro_nos', '1. Sabe dizer o seu nome completo.'),
('Pautas', 73, 'Infantil 4', 'O Eu, O Outro e o Nós', 'SER003', 'eu_outro_nos', '2. Faz escolhas exercitando sua autonomia.'),
('Pautas', 74, 'Infantil 4', 'O Eu, O Outro e o Nós', 'SER003', 'eu_outro_nos', '3. Sabe dizer o nome das pessoas que fazem parte de sua família.'),
('Pautas', 75, 'Infantil 4', 'Traços, sons, cores e formas', 'SER003', 'tracos_sons_cores', '1. Discrimina diversos sons.'),
('Pautas', 76, 'Infantil 4', 'Traços, sons, cores e formas', 'SER003', 'tracos_sons_cores', '2. Expressa-se através do desenho e da pintura.'),
('Pautas', 77, 'Infantil 4', 'Traços, sons, cores e formas', 'SER003', 'tracos_sons_cores', '3. Produz composições artísticas através da técnica de rasgadura e colagem.'),
('Pautas', 78, 'Infantil 4', 'Traços, sons, cores e formas', 'SER003', 'tracos_sons_cores', '4. Identifica obras de Aldemir Martins.'),
('Pautas', 79, 'Infantil 4', 'Traços, sons, cores e formas', 'SER003', 'tracos_sons_cores', '5. Reconhece e nomeia diversas cores.'),
('Pautas', 80, 'Infantil 5', 'Corpo, Gesto e Movimento', 'SER004', 'corpo_gesto_movimento', '1. Conhece as partes do corpo: testa, ombro, joelho, cotovelo, perna, braço e queixo.'),
('Pautas', 81, 'Infantil 5', 'Corpo, Gesto e Movimento', 'SER004', 'corpo_gesto_movimento', '2. Recupera o equilíbrio após movimentos dos segmentos corporais.'),
('Pautas', 82, 'Infantil 5', 'Corpo, Gesto e Movimento', 'SER004', 'corpo_gesto_movimento', '3. Emprega meios para acompanhar os movimentos respeitando o ritmo da música.'),
('Pautas', 83, 'Infantil 5', 'Corpo, Gesto e Movimento', 'SER004', 'corpo_gesto_movimento', '4. Demonstra controle do uso de seu corpo nas atividades que envolvem as grandes massas musculares.'),
('Pautas', 84, 'Infantil 5', 'Corpo, Gesto e Movimento', 'SER004', 'corpo_gesto_movimento', '5. Emprega meios para deslocar o corpo ou um segmento do corpo, numa unidade de tempo cada vez menor.'),
('Pautas', 85, 'Infantil 5', 'Corpo, Gesto e Movimento', 'SER004', 'corpo_gesto_movimento', '6. Demonstra movimento corporal espontâneo, natural e expressivo.'),
('Pautas', 86, 'Infantil 5', 'Corpo, Gesto e Movimento', 'SER004', 'corpo_gesto_movimento', '7. Coordena o sentido da visão com os movimentos dos membros inferiores.'),
('Pautas', 87, 'Infantil 5', 'Corpo, Gesto e Movimento', 'SER004', 'corpo_gesto_movimento', '8. Coordena o sentido da visão com os movimentos dos membros superiores.'),
('Pautas', 88, 'Infantil 5', 'Corpo, Gesto e Movimento', 'SER004', 'corpo_gesto_movimento', '9. Demonstra coordenação motora fina adequada.'),
('Pautas', 89, 'Infantil 5', 'Corpo, Gesto e Movimento', 'SER004', 'corpo_gesto_movimento', '10. Exercita a dedução, observação e a atenção para encontrar os pares no jogo da memória com 10 cartas.'),
('Pautas', 90, 'Infantil 5', 'Escuta, fala, pensamento e imaginação', 'SER004', 'escuta_fala_pensamento', '1. Expressa, por meio da linguagem oral, suas vivências, necessidades e sentimentos.'),
('Pautas', 91, 'Infantil 5', 'Escuta, fala, pensamento e imaginação', 'SER004', 'escuta_fala_pensamento', '2. Ouve histórias com atenção.'),
('Pautas', 92, 'Infantil 5', 'Escuta, fala, pensamento e imaginação', 'SER004', 'escuta_fala_pensamento', '3. Expressa ideias por meio do desenho.'),
('Pautas', 93, 'Infantil 5', 'Escuta, fala, pensamento e imaginação', 'SER004', 'escuta_fala_pensamento', '4. Escreve o próprio nome sem modelo.'),
('Pautas', 94, 'Infantil 5', 'Espaços, tempos, quantidades, relações e transformações', 'SER004', 'espaco_tempo_quantidade', '1. Faz contagem oral sequenciando de 1 a 10.'),
('Pautas', 95, 'Infantil 5', 'Espaços, tempos, quantidades, relações e transformações', 'SER004', 'espaco_tempo_quantidade', '2. Relaciona numeral entre 0 e 5 a sua respectiva quantidade.'),
('Pautas', 96, 'Infantil 5', 'Espaços, tempos, quantidades, relações e transformações', 'SER004', 'espaco_tempo_quantidade', '3. Diferencia números de letras.'),
('Pautas', 97, 'Infantil 5', 'Espaços, tempos, quantidades, relações e transformações', 'SER004', 'espaco_tempo_quantidade', '4. Traça números entre 0 e 5.'),
('Pautas', 98, 'Infantil 5', 'Espaços, tempos, quantidades, relações e transformações', 'SER004', 'espaco_tempo_quantidade', '5. Identifica o que vem antes e depois em uma sequência de objetos.'),
('Pautas', 99, 'Infantil 5', 'Espaços, tempos, quantidades, relações e transformações', 'SER004', 'espaco_tempo_quantidade', '6. Reconhece os órgãos do sentido relacionando-os às suas funções'),
('Pautas', 100, 'Infantil 5', 'Espaços, tempos, quantidades, relações e transformações', 'SER004', 'espaco_tempo_quantidade', '7. Reconhece algumas características dos animais.'),
('Pautas', 101, 'Infantil 5', 'O Eu, O Outro e o Nós', 'SER004', 'eu_outro_nos', '1. Sabe dizer o seu nome completo.'),
('Pautas', 102, 'Infantil 5', 'O Eu, O Outro e o Nós', 'SER004', 'eu_outro_nos', '2. Sabe dizer a sua idade.'),
('Pautas', 103, 'Infantil 5', 'O Eu, O Outro e o Nós', 'SER004', 'eu_outro_nos', '3. Faz escolhas exercitando sua autonomia.'),
('Pautas', 104, 'Infantil 5', 'O Eu, O Outro e o Nós', 'SER004', 'eu_outro_nos', '4. Sabe dizer o nome das pessoas que fazem parte de sua família.'),
('Pautas', 105, 'Infantil 5', 'Traços, sons, cores e formas', 'SER004', 'tracos_sons_cores', '1. Discrimina diversos sons.'),
('Pautas', 106, 'Infantil 5', 'Traços, sons, cores e formas', 'SER004', 'tracos_sons_cores', '2. Canta pequenas canções folclóricas do início ao fim.'),
('Pautas', 107, 'Infantil 5', 'Traços, sons, cores e formas', 'SER004', 'tracos_sons_cores', '3. Demonstra expressividade ao participar de brincadeiras de roda.'),
('Pautas', 108, 'Infantil 5', 'Traços, sons, cores e formas', 'SER004', 'tracos_sons_cores', '4. Expressa-se por meio de desenho e pintura.'),
('Pautas', 109, 'Infantil 5', 'Traços, sons, cores e formas', 'SER004', 'tracos_sons_cores', '5. Produz trabalhos artísticos através da técnica de recorte e colagem.'),
('Pautas', 110, 'Infantil 5', 'Traços, sons, cores e formas', 'SER004', 'tracos_sons_cores', '6. Reconhece e nomeia diversas cores.'),
('Pautas', 111, 'Infantil 5', 'Traços, sons, cores e formas', 'SER004', 'tracos_sons_cores', '7. Identifica obras de Tarsila do Amaral.'),
('Pautas', 112, '1º Ano', 'Inglês', 'SER005', 'ingles', '1. Participa de interações orais simples em língua inglesa, utilizando cumprimentos e apresentações pessoais em atividades mediadas pelo professor'),
('Pautas', 113, '1º Ano', 'Inglês', 'SER005', 'ingles', '2. Reproduz palavras e expressões simples em inglês com pronúncia adequada'),
('Pautas', 114, '1º Ano', 'Inglês', 'SER005', 'ingles', '3. Identifica e pronuncia os nomes das cores em inglês.'),
('Pautas', 115, '1º Ano', 'Inglês', 'SER005', 'ingles', '4. Reconhece e utiliza vocabulário em inglês relacionado a objetos e pessoas da sala de aula (teacher, student, board, desk, chair)'),
('Pautas', 116, '1º Ano', 'Matemática', 'SER005', 'matematica', '1. Escrever números convencionalmente, compreendendo o princípio de valor posicional dos números no Sistema de Numeração Decimal (SND).'),
('Pautas', 117, '1º Ano', 'Matemática', 'SER005', 'matematica', '2. Identifica o conjunto que tem mais elementos.'),
('Pautas', 118, '1º Ano', 'Matemática', 'SER005', 'matematica', '3. Identifica o conjunto que tem menos elementos.'),
('Pautas', 119, '1º Ano', 'Matemática', 'SER005', 'matematica', '4. Identifica os conjuntos que têm a mesma quantidade de elementos.'),
('Pautas', 120, '1º Ano', 'Matemática', 'SER005', 'matematica', '5. Compara comprimentos, capacidades ou massas, utilizando termos como mais alto, mais baixo, mais pesado, mais leve.'),
('Pautas', 121, '1º Ano', 'Matemática', 'SER005', 'matematica', '6. Identifica a localização de pessoas ou objetos no espaço a partir de pontos de referência, utilizando os termos direita e esquerda, entre, em cima, embaixo, dentro e fora, à frente de, atrás de, depois de.'),
('Pautas', 122, '1º Ano', 'Matemática', 'SER005', 'matematica', '7. Associa a denominação do número a sua respectiva representação simbólica. (em torno de 30 elementos).'),
('Pautas', 123, '1º Ano', 'Matemática', 'SER005', 'matematica', '8. Classifica objetos e figuras de acordo com diferentes critérios.'),
('Pautas', 124, '1º Ano', 'Matemática', 'SER005', 'matematica', '9. Relaciona figuras geométricas espaciais (cone, cilindro, esfera, pirâmide e bloco retangular) a objetos familiares do mundo físico.'),
('Pautas', 125, '1º Ano', 'Língua Portuguesa', 'SER005', 'portugues', '1. Relaciona o nome da letra à letra/grafema que a representa.'),
('Pautas', 126, '1º Ano', 'Língua Portuguesa', 'SER005', 'portugues', '2. Identifica as letras do seu nome.'),
('Pautas', 127, '1º Ano', 'Língua Portuguesa', 'SER005', 'portugues', '3. Escreve o primeiro nome sem modelo.'),
('Pautas', 128, '1º Ano', 'Língua Portuguesa', 'SER005', 'portugues', '4. Identifica informações explícitas em texto ouvido.'),
('Pautas', 129, '1º Ano', 'Língua Portuguesa', 'SER005', 'portugues', '5. Reconhece o gênero textual.'),
('Pautas', 130, '1º Ano', 'Língua Portuguesa', 'SER005', 'portugues', '6. Escreve palavras obedecendo aos princípios da ortografia.'),
('Pautas', 131, '1º Ano', 'Educação Física', 'SER005', 'educacao_fisica', '1. Executa exercícios de coordenação motora global com movimentos harmoniosos.'),
('Pautas', 132, '1º Ano', 'Educação Física', 'SER005', 'educacao_fisica', '2. Aprimora as ações psicomotoras de locomoção e equilíbrio na ginástica geral.'),
('Pautas', 133, '1º Ano', 'Educação Física', 'SER005', 'educacao_fisica', '3. Segue movimentos ritmados citados nos brinquedos cantados.'),
('Pautas', 134, '1º Ano', 'Educação Física', 'SER005', 'educacao_fisica', '4. Demonstra concentração e a percepção nos jogos de atenção.'),
('Pautas', 135, '1º Ano', 'Educação Física', 'SER005', 'educacao_fisica', '5. Percebe que o sucesso do grupo é mais importante que o individual nos jogos cooperativos.'),
('Pautas', 136, '1º Ano', 'Ciências', 'SER005', 'ciencias', '1. Reconhece as características observáveis de diferentes materiais.'),
('Pautas', 137, '1º Ano', 'Ciências', 'SER005', 'ciencias', '2. Identifica formas adequadas de descarte de diferentes objetos.'),
('Pautas', 138, '1º Ano', 'Geografia', 'SER005', 'geografia', '1. Reconhece os diferentes espaços de moradia e suas funções.'),
('Pautas', 139, '1º Ano', 'Geografia', 'SER005', 'geografia', '2. Reconhece diferentes tipos de moradia, considerando os materiais utilizados em sua produção.'),
('Pautas', 140, '1º Ano', 'Geografia', 'SER005', 'geografia', '3. Representa trajetos do dia a dia por meio de desenho e mapa mental.'),
('Pautas', 141, '1º Ano', 'Arte', 'SER005', 'arte', '1. Identificar obras de Joan Miró.'),
('Pautas', 142, '1º Ano', 'Arte', 'SER005', 'arte', '2. Expressar-se por meio de desenho.'),
('Pautas', 143, '1º Ano', 'Arte', 'SER005', 'arte', '3. Identificar diversos sons (timbre.)'),
('Pautas', 144, '1º Ano', 'Arte', 'SER005', 'arte', '4. Representa personagens manipulando máscaras em brincadeiras de faz de conta.'),
('Pautas', 145, '1º Ano', 'História', 'SER005', 'historia', '1. Identifica aspectos do seu crescimento por meio de suas características individuais.'),
('Pautas', 146, '1º Ano', 'História', 'SER005', 'historia', '2. Identifica o seu nome e sobrenome.'),
('Pautas', 147, '1º Ano', 'História', 'SER005', 'historia', '3. Identifica objetos e/ou imagens relacionadas ao presente e passado, reconhecendo a passagem do tempo.'),
('Pautas', 148, '1º Ano', 'História', 'SER005', 'historia', '4. Identifica e compara características de diferentes fases da vida do ser humano.'),
('Pautas', 149, '2º Ano', 'Inglês', 'SER006', 'ingles', '1. Participa de interações orais simples em língua inglesa, utilizando cumprimentos e apresentações pessoais em atividades mediadas pelo professor'),
('Pautas', 150, '2º Ano', 'Inglês', 'SER006', 'ingles', '2. Reproduz palavras e expressões simples em inglês com pronúncia adequada.'),
('Pautas', 151, '2º Ano', 'Inglês', 'SER006', 'ingles', '3. Identificar os membros da família em inglês.'),
('Pautas', 152, '2º Ano', 'Inglês', 'SER006', 'ingles', '4. Identifica brinquedos em inglês'),
('Pautas', 153, '2º Ano', 'Matemática', 'SER006', 'matematica', '1. Escrever números convencionalmente, compreendendo o princípio de valor posicional dos números no Sistema de Numeração Decimal (SND).'),
('Pautas', 154, '2º Ano', 'Matemática', 'SER006', 'matematica', '2. Escreve números entre 0 e 100.'),
('Pautas', 155, '2º Ano', 'Matemática', 'SER006', 'matematica', '3. Relaciona 10 unidades a uma dezena.'),
('Pautas', 156, '2º Ano', 'Matemática', 'SER006', 'matematica', '4. Relaciona 10 dezenas a uma centena.'),
('Pautas', 157, '2º Ano', 'Matemática', 'SER006', 'matematica', '5. Calcula fatos básicos da adição com registro por meio de algoritmo.'),
('Pautas', 158, '2º Ano', 'Matemática', 'SER006', 'matematica', '6. Calcula fatos básicos da subtração com registro por meio de algoritmo.'),
('Pautas', 159, '2º Ano', 'Matemática', 'SER006', 'matematica', '7. Calcula adição até a dezena, sem e com agrupamento, por meio do algoritmo.'),
('Pautas', 160, '2º Ano', 'Matemática', 'SER006', 'matematica', '8. Calcula subtração até a dezena, sem e com desagrupamento por meio do algoritmo.'),
('Pautas', 161, '2º Ano', 'Matemática', 'SER006', 'matematica', '9. Relaciona as figuras geométricas planas, círculo, quadrado, retângulo e triângulo a objetos familiares do mundo físico.'),
('Pautas', 162, '2º Ano', 'Língua Portuguesa', 'SER006', 'portugues', '1. Lê palavra formada por sílaba canônica.'),
('Pautas', 163, '2º Ano', 'Língua Portuguesa', 'SER006', 'portugues', '2. Lê palavra formada por sílaba não canônica.'),
('Pautas', 164, '2º Ano', 'Língua Portuguesa', 'SER006', 'portugues', '3. Reconhece o assunto do texto.'),
('Pautas', 165, '2º Ano', 'Língua Portuguesa', 'SER006', 'portugues', '4. Reconhece o gênero textual.'),
('Pautas', 166, '2º Ano', 'Língua Portuguesa', 'SER006', 'portugues', '5. Identifica a finalidade do texto.'),
('Pautas', 167, '2º Ano', 'Língua Portuguesa', 'SER006', 'portugues', '6. Identifica informações explícitas em texto.'),
('Pautas', 168, '2º Ano', 'Língua Portuguesa', 'SER006', 'portugues', '7. Escreve o nome completo.'),
('Pautas', 169, '2º Ano', 'Língua Portuguesa', 'SER006', 'portugues', '8. Escreve palavras obedecendo aos princípios da ortografia.'),
('Pautas', 170, '2º Ano', 'Educação Física', 'SER006', 'educacao_fisica', '1. Aprimora as ações psicomotoras de locomoção e equilíbrio na ginástica geral'),
('Pautas', 171, '2º Ano', 'Educação Física', 'SER006', 'educacao_fisica', '2. Coordena o sentido da visão com os movimentos dos membros inferiores.'),
('Pautas', 172, '2º Ano', 'Educação Física', 'SER006', 'educacao_fisica', '3. Coordena o sentido da visão com os movimentos dos membros superiores.'),
('Pautas', 173, '2º Ano', 'Educação Física', 'SER006', 'educacao_fisica', '4. Movimenta o corpo no tempo e espaço ao ritmo da música nos brinquedos cantados.'),
('Pautas', 174, '2º Ano', 'Educação Física', 'SER006', 'educacao_fisica', '5. Considera o outro como um parceiro e não como um adversário.'),
('Pautas', 175, '2º Ano', 'Educação Física', 'SER006', 'educacao_fisica', '6. Expressa-se corporalmente nos jogos de dramatização.'),
('Pautas', 176, '2º Ano', 'Educação Física', 'SER006', 'educacao_fisica', '7. Expressa-se oralmente nos jogos de dramatização.'),
('Pautas', 177, '2º Ano', 'Ciências', 'SER006', 'ciencias', '1. Identifica a matéria-prima de diferentes objetos.'),
('Pautas', 178, '2º Ano', 'Ciências', 'SER006', 'ciencias', '2. Relaciona as propriedades (flexibilidade, dureza, permeabilidade e transparência) dos diferentes materiais a sua utilização em objetos do cotidiano.'),
('Pautas', 179, '2º Ano', 'Ciências', 'SER006', 'ciencias', '3. Reconhece atitudes de segurança em relação às situações de risco no ambiente doméstico.'),
('Pautas', 180, '2º Ano', 'Geografia', 'SER006', 'geografia', '1. Representa através de mapa mental o trajeto casa/escola, identificando pontos de referência.'),
('Pautas', 181, '2º Ano', 'Geografia', 'SER006', 'geografia', '2. Identifica mudanças e permanências, na paisagem de um mesmo lugar em diferentes tempos.'),
('Pautas', 182, '2º Ano', 'Arte', 'SER006', 'arte', '1. Identifica elementos constitutivos das artes visuais (ponto e linha).'),
('Pautas', 183, '2º Ano', 'Arte', 'SER006', 'arte', '2. Descreve os sons que compõem diferentes paisagens sonoras.'),
('Pautas', 184, '2º Ano', 'Arte', 'SER006', 'arte', '3. Identifica os elementos que fazem parte da linguagem teatral (cenário, figurino, iluminação e som, texto teatral).'),
('Pautas', 185, '2º Ano', 'Arte', 'SER006', 'arte', '4. Explora fontes sonoras diversas por meio da voz, da percussão corporal e de objetos.'),
('Pautas', 186, '2º Ano', 'História', 'SER006', 'historia', '1. Compreende acontecimentos da vida cotidiana utilizando noções relacionadas ao tempo.'),
('Pautas', 187, '2º Ano', 'História', 'SER006', 'historia', '2. Identifica acontecimentos da vida cotidiana e sua temporalidade.'),
('Pautas', 188, '2º Ano', 'História', 'SER006', 'historia', '3. Reconhece o relógio como marcador do tempo.'),
('Pautas', 189, '2º Ano', 'História', 'SER006', 'historia', '4. Identifica fontes históricas como resultado da ação humana.'),
('Pautas', 190, '2º Ano', 'História', 'SER006', 'historia', '5. Identifica o papel da História e do historiador dentro da historiografia'),
('Pautas', 194, 'SRM', 'Coordenação Motora Fina', 'srm_serie', 'srm', '1. Recorta linhas retas, curvas ou quebradas.'),
('Pautas', 195, 'SRM', 'Coordenação Motora Fina', 'srm_serie', 'srm', '2. Amarra o cordão do tênis (ou sapato).'),
('Pautas', 196, 'SRM', 'Coordenação Motora Fina', 'srm_serie', 'srm', '3. Abotoa os botões com precisão.'),
('Pautas', 197, 'SRM', 'Coordenação Motora Global e Equilíbrio', 'srm_serie', 'srm', '1. Anda em linha curva.'),
('Pautas', 198, 'SRM', 'Coordenação Motora Global e Equilíbrio', 'srm_serie', 'srm', '2. Anda pé ante pé.'),
('Pautas', 199, 'SRM', 'Coordenação Motora Global e Equilíbrio', 'srm_serie', 'srm', '3. Pula num pé só (direito/esquerdo).'),
('Pautas', 200, 'SRM', 'Coordenação Motora Global e Equilíbrio', 'srm_serie', 'srm', '4. Pula corda coordenadamente.'),
('Pautas', 201, 'SRM', 'Coordenação Motora Global e Equilíbrio', 'srm_serie', 'srm', '5. Senta com postura adequada.'),
('Pautas', 202, 'SRM', 'Coordenação Motora Global e Equilíbrio', 'srm_serie', 'srm', '6. Arremessa bola com as duas mãos.'),
('Pautas', 203, 'SRM', 'Coordenação Motora Global e Equilíbrio', 'srm_serie', 'srm', '7. Pega a bola com as duas mãos.'),
('Pautas', 204, 'SRM', 'Coordenação Motora Global e Equilíbrio', 'srm_serie', 'srm', '8. Eleva-se sobre a ponta dos pés.'),
('Pautas', 205, 'SRM', 'Coordenação Motora Global e Equilíbrio', 'srm_serie', 'srm', '9. Mantém-se sobre um pé só (direito/esquerdo)'),
('Pautas', 206, 'SRM', 'Coordenação visomotora', 'srm_serie', 'srm', '1. Coordena o sentido da visão com os movimentos dos membros inferiores.'),
('Pautas', 207, 'SRM', 'Coordenação visomotora', 'srm_serie', 'srm', '2. Coordena o sentido da visão com os movimentos dos membros superiores.'),
('Pautas', 208, 'SRM', 'Esquema Corporal', 'srm_serie', 'srm', '1. Conhece e nomeia as partes do corpo, cabeça, olhos, nariz, boca, orelhas, braço, mãos, barriga, pernas e pés.'),
('Pautas', 209, 'SRM', 'Esquema Corporal', 'srm_serie', 'srm', '2. Reproduz a figura humana.'),
('Pautas', 210, 'SRM', 'Lateralidade', 'srm_serie', 'srm', '1. Reconhece o lado direito e o lado esquerdo no seu corpo.'),
('Pautas', 211, 'SRM', 'Lateralidade', 'srm_serie', 'srm', '2. Reconhece o seu lado dominante.'),
('Pautas', 212, 'SRM', 'Integração Sensório-Motora', 'srm_serie', 'srm', '1. Identifica objetos pelo tato.'),
('Pautas', 213, 'SRM', 'Integração Sensório-Motora', 'srm_serie', 'srm', '2. Distingue odores diversos.'),
('Pautas', 214, 'SRM', 'Integração Sensório-Motora', 'srm_serie', 'srm', '3. Reconhece sons diversificados.'),
('Pautas', 215, 'SRM', 'Integração Sensório-Motora', 'srm_serie', 'srm', '4. Distingue sabores diferentes.'),
('Pautas', 216, 'SRM', 'Cores', 'srm_serie', 'srm', '1. Conhece as cores vermelho, amarelo, azul, verde, laranja e roxo.'),
('Pautas', 217, 'SRM', 'Expressões artísticas: desenho, recorte e colagem', 'srm_serie', 'srm', '1. Apresenta habilidades visomotoras para desenhar.'),
('Pautas', 218, 'SRM', 'Expressões artísticas: desenho, recorte e colagem', 'srm_serie', 'srm', '2. Expressa verbalmente o conteúdo do seu desenho.'),
('Pautas', 219, 'SRM', 'Expressões artísticas: desenho, recorte e colagem', 'srm_serie', 'srm', '3. Demonstra criatividade para desenhar.'),
('Pautas', 220, 'SRM', 'Expressões artísticas: desenho, recorte e colagem', 'srm_serie', 'srm', '4. Apresenta habilidades visomotoras para recortar e colar.'),
('Pautas', 221, 'SRM', 'Brincadeiras cantadas', 'srm_serie', 'srm', '1. Acompanha música cantando e gesticulando.'),
('Pautas', 222, 'SRM', 'Oralidade', 'srm_serie', 'srm', '1. Apresenta vocabulário receptivo de acordo com a idade, oportunidades e situações vivenciadas.'),
('Pautas', 223, 'SRM', 'Oralidade', 'srm_serie', 'srm', '2. Compreende sons ou palavras faladas.'),
('Pautas', 224, 'SRM', 'Oralidade', 'srm_serie', 'srm', '3. Comunica-se oralmente fazendo-se entender.'),
('Pautas', 225, 'SRM', 'Oralidade', 'srm_serie', 'srm', '4. Pronuncia palavras corretamente.'),
('Pautas', 226, 'SRM', 'Oralidade', 'srm_serie', 'srm', '5. Responde verbalmente, com significado, a estímulos auditivos.'),
('Pautas', 227, 'SRM', 'Oralidade', 'srm_serie', 'srm', '6. Retém e lembra de informações dadas oralmente.'),
('Pautas', 228, 'SRM', 'Oralidade', 'srm_serie', 'srm', '7. Reconta uma história ouvida.'),
('Pautas', 229, 'SRM', 'Leitura', 'srm_serie', 'srm', '1. Reconhece seu nome entre outros.'),
('Pautas', 230, 'SRM', 'Leitura', 'srm_serie', 'srm', '2. Relaciona o nome da letra à letra/grafema que a representa.'),
('Pautas', 231, 'SRM', 'Leitura', 'srm_serie', 'srm', '3. Lê palavra formada por sílaba canônica.'),
('Pautas', 232, 'SRM', 'Leitura', 'srm_serie', 'srm', '4. Lê palavra formada por sílaba não canônica.'),
('Pautas', 233, 'SRM', 'Escrita', 'srm_serie', 'srm', '1. Escreve o nome completo.'),
('Pautas', 234, 'SRM', 'Escrita', 'srm_serie', 'srm', '2. Escreve palavras obedecendo aos princípios da ortografia.'),
('Pautas', 235, 'SRM', 'Escrita', 'srm_serie', 'srm', '3. Produz frases pela observância de imagens.'),
('Pautas', 236, 'SRM', 'Correspondência', 'srm_serie', 'srm', '1. Faz correspondência um a um.'),
('Pautas', 237, 'SRM', 'Comparação', 'srm_serie', 'srm', '1. Agrupa objetos quanto a cor, a forma, tamanho e espessura.'),
('Pautas', 238, 'SRM', 'Classificação', 'srm_serie', 'srm', '1. Classifica objetos de acordo com características comuns.'),
('Pautas', 239, 'SRM', 'Seriação', 'srm_serie', 'srm', '1. Ordena uma sequência segundo um critério.'),
('Pautas', 240, 'SRM', 'Sequenciação', 'srm_serie', 'srm', '1. Ordena um elemento ao outro, sem considerar a posição entre eles.'),
('Pautas', 241, 'SRM', 'Inclusão', 'srm_serie', 'srm', '1. Inclui um conjunto de objetos variados, ao seu conjunto correspondente.'),
('Pautas', 242, 'SRM', 'Conservação', 'srm_serie', 'srm', '1. Percebe que a quantidade de algo não depende da arrumação, forma ou posição.'),
('Pautas', 243, 'SRM', 'Localização de pessoas ou objetos no espaço', 'srm_serie', 'srm', '1. Localiza pessoas e objetos no espaço utilizando os pontos de referência, como termos em cima de, embaixo de, ao lado de, a frente de e atrás de.'),
('Pautas', 244, 'SRM', 'Quantidade e Tamanho', 'srm_serie', 'srm', '1. Reconhece o menor e o maior, o curto e o comprido, o pequeno e o grande, o baixo e o alto.'),
('Pautas', 245, 'SRM', 'Quantidade e Tamanho', 'srm_serie', 'srm', '2. Reconhece o que é fino e o que é grosso.'),
('Pautas', 246, 'SRM', 'Contagem', 'srm_serie', 'srm', '1. Faz contagem oral até 100.'),
('Pautas', 247, 'SRM', 'Sistema de numeração decimal', 'srm_serie', 'srm', '1. Lê números entre 0 e 100.'),
('Pautas', 248, 'SRM', 'Sistema de numeração decimal', 'srm_serie', 'srm', '2. Escreve números entre 0 e 100.'),
('Pautas', 249, 'SRM', 'Sistema de numeração decimal', 'srm_serie', 'srm', '3. Traça os números de forma correta.'),
('Pautas', 250, 'SRM', 'Sistema de numeração decimal', 'srm_serie', 'srm', '4. Associa o número à sua respectiva quantidade.'),
('Pautas', 251, 'SRM', 'Adição e Subtração', 'srm_serie', 'srm', '1. Calcula fatos básicos da adição com unidades.'),
('Pautas', 252, 'SRM', 'Adição e Subtração', 'srm_serie', 'srm', '2. Calcula fatos básicos da subtração com unidades.'),
('Pautas', 253, 'SRM', 'Adição e Subtração', 'srm_serie', 'srm', '3. Realiza adições com números de até duas ordens, sem agrupamento na dezena.'),
('Pautas', 254, 'SRM', 'Adição e Subtração', 'srm_serie', 'srm', '4. Realiza subtrações com números de até duas ordens, sem desagrupamento na dezena.'),
('Pautas', 255, 'SRM', 'Adição e Subtração', 'srm_serie', 'srm', '5.  Resolve situações-problema que envolvam os fatos básicos da adição que demandam a ideia de juntar, com registro através de desenho ou número.'),
('Pautas', 256, 'SRM', 'Adição e Subtração', 'srm_serie', 'srm', '6.  Resolve situações-problema que envolvam os fatos básicos da subtração que demandam a ideia de retirar, com registro através de desenho ou número.'),
('Pautas', 257, 'SRM', 'Medidas de tempo', 'srm_serie', 'srm', '1. Relaciona os períodos de tempo manhã, tarde, noite, ontem, hoje, agora, amanhã, antes e depois a partir de ações do cotidiano.'),
('Pautas', 258, 'SRM', 'Medidas de tempo', 'srm_serie', 'srm', '2. Nomeia os dias da semana.'),
('Pautas', 259, 'SRM', 'Medidas de tempo', 'srm_serie', 'srm', '3. Identifica dia/mês/ano.'),
('Pautas', 260, 'SRM', 'Medidas de tempo', 'srm_serie', 'srm', '4. Nomeia os meses do ano.'),
('Pautas', 261, 'SRM', 'Geometria', 'srm_serie', 'srm', '1. Conhece as formas geométricas, círculo, quadrado, retângulo e triângulo.'),
('Pautas', 262, '1º Ano', 'Atividades Esportivas (Lutas)', 'SER016', 'atividades_esportivas', 'Empurra mantendo controle sem desequilibrar agressivamente.'),
('Pautas', 263, '1º Ano', 'Atividades Esportivas (Lutas)', 'SER016', 'atividades_esportivas', 'Participa respeitando regras e limites.'),
('Pautas', 264, '1º Ano', 'Atividades Esportivas (Lutas)', 'SER016', 'atividades_esportivas', 'Mantém equilíbrio durante desafio motor.'),
('Pautas', 265, '1º Ano', 'Atividades Esportivas (Lutas)', 'SER016', 'atividades_esportivas', 'Respeita sinais de parada e limites do colega.'),
('Pautas', 266, '1º Ano', 'Atividades Esportivas (Capoeira)', 'SER016', 'atividades_esportivas', 'Executa ginga e ao menos um movimento de esquiva com equilíbrio e controle básico.'),
('Pautas', 267, '1º Ano', 'Atividades Esportivas (Capoeira)', 'SER016', 'atividades_esportivas', 'Desloca-se sem colidir e responde adequadamente a comandos de lateralidade.'),
('Pautas', 268, '1º Ano', 'Atividades Esportivas (Capoeira)', 'SER016', 'atividades_esportivas', 'Respeita combinados e mantém postura adequada durante o jogo.'),
('Pautas', 269, '1º Ano', 'Atividades Esportivas (Capoeira)', 'SER016', 'atividades_esportivas', 'Mantém sequência rítmica simples com regularidade.'),
('Pautas', 270, '1º Ano', 'Atividades Esportivas (Esportes)', 'SER016', 'atividades_esportivas', 'Percorre trajeto mantendo direção.'),
('Pautas', 271, '1º Ano', 'Atividades Esportivas (Esportes)', 'SER016', 'atividades_esportivas', 'Realiza salto ultrapassando marca inicial.'),
('Pautas', 272, '1º Ano', 'Atividades Esportivas (Esportes)', 'SER016', 'atividades_esportivas', 'Arremessa objeto alcançando alvo geral.'),
('Pautas', 273, '1º Ano', 'Atividades Esportivas (Esportes)', 'SER016', 'atividades_esportivas', 'Completa circuito proposto.'),
('Pautas', 274, '1º Ano', 'Recreação e Jogos', 'SER016', 'recreacao_jogos', 'Respeita turno e regras básicas.'),
('Pautas', 275, '1º Ano', 'Recreação e Jogos', 'SER016', 'recreacao_jogos', 'Identifica possibilidade de vitória.'),
('Pautas', 276, '1º Ano', 'Recreação e Jogos', 'SER016', 'recreacao_jogos', 'Distribui peças corretamente.'),
('Pautas', 277, '1º Ano', 'Recreação e Jogos', 'SER016', 'recreacao_jogos', 'Compreende objetivo do percurso.'),
('Pautas', 278, '1º Ano', '(Dança) Laboratório de Teatro, Dança e Música', 'SER016', 'danca', 'Executa sequência mantendo ritmo.'),
('Pautas', 279, '1º Ano', '(Dança) Laboratório de Teatro, Dança e Música', 'SER016', 'danca', 'Mantém alinhamento no grupo.'),
('Pautas', 280, '1º Ano', '(Dança) Laboratório de Teatro, Dança e Música', 'SER016', 'danca', 'Mantém posição combinada.'),
('Pautas', 281, '1º Ano', '(Dança) Laboratório de Teatro, Dança e Música', 'SER016', 'danca', 'Executa sequência completa.'),
('Pautas', 282, '1º Ano', '(Teatro) Laboratório de Teatro, Dança e Música', 'SER016', 'teatro', 'Apresenta variedade de movimentos.'),
('Pautas', 283, '1º Ano', '(Teatro) Laboratório de Teatro, Dança e Música', 'SER016', 'teatro', 'Organiza ações simples.'),
('Pautas', 284, '1º Ano', '(Teatro) Laboratório de Teatro, Dança e Música', 'SER016', 'teatro', 'Adapta gestos e personagens.'),
('Pautas', 285, '1º Ano', '(Teatro) Laboratório de Teatro, Dança e Música', 'SER016', 'teatro', 'Participa respeitando regras.'),
('Pautas', 286, '1º Ano', '(Música) Laboratório de Teatro, Dança e Música', 'SER016', 'musica', 'Utiliza diferentes fontes sonoras.'),
('Pautas', 287, '1º Ano', '(Música) Laboratório de Teatro, Dança e Música', 'SER016', 'musica', 'Canta com participação ativa.'),
('Pautas', 288, '1º Ano', '(Música) Laboratório de Teatro, Dança e Música', 'SER016', 'musica', 'Diferencia grave/agudo e curto/longo.'),
('Pautas', 289, '1º Ano', '(Música) Laboratório de Teatro, Dança e Música', 'SER016', 'musica', 'Mantém ritmo simples.'),
('Pautas', 290, '1º Ano', 'Artes Visuais', 'SER016', 'arte', 'Reconhece cores, formas e personagens.'),
('Pautas', 291, '1º Ano', 'Artes Visuais', 'SER016', 'arte', 'Produz desenhos com maior detalhamento.'),
('Pautas', 292, '1º Ano', 'Artes Visuais', 'SER016', 'arte', 'Utiliza cores adequadas à proposta.'),
('Pautas', 293, '1º Ano', 'Artes Visuais', 'SER016', 'arte', 'Organiza elementos formando composição.'),
('Pautas', 294, '1º Ano', 'Recomposição - Matemática', 'SER016', 'recomposicao_matematica', 'Escreve números com algarismos e por extenso.'),
('Pautas', 295, '1º Ano', 'Recomposição - Matemática', 'SER016', 'recomposicao_matematica', 'Lê e representa números (principalmente até dezenas, iniciando centenas).'),
('Pautas', 296, '1º Ano', 'Recomposição - Matemática', 'SER016', 'recomposicao_matematica', 'Resolve operações de adição com apoio de recursos manipuláveis e/ou digitais, registros pictóricos e algorítmicos (com e sem agrupamento na dezena).'),
('Pautas', 297, '1º Ano', 'Recomposição - Matemática', 'SER016', 'recomposicao_matematica', 'Resolver operações de subtração com apoio de recursos manipuláveis e/ou digitais, registros pictóricos e algorítmicos (com e sem desagrupamento na dezena).'),
('Pautas', 298, '1º Ano', 'Recomposição - Matemática', 'SER016', 'recomposicao_matematica', 'Identifica as características das figuras geométricas espaciais observando semelhanças e diferenças (cones, cilindros, esferas, pirâmides e blocos retangulares).'),
('Pautas', 299, '1º Ano', 'Recomposição - Matemática', 'SER016', 'recomposicao_matematica', 'Conhece diferentes tipos de relógio (digital e analógico).'),
('Pautas', 300, '1º Ano', 'Recomposição - Matemática', 'SER016', 'recomposicao_matematica', 'Lê horas em relógios digitais e analógicos (hora exata).'),
('Pautas', 301, '1º Ano', 'Recomposição - Língua Portuguesa', 'SER016', 'recomposicao_lingua_portuguesa', 'Reconta oralmente histórias com apoio de imagens.'),
('Pautas', 302, '1º Ano', 'Recomposição - Língua Portuguesa', 'SER016', 'recomposicao_lingua_portuguesa', 'Escrever palavras canônicas.'),
('Pautas', 303, '1º Ano', 'Recomposição - Língua Portuguesa', 'SER016', 'recomposicao_lingua_portuguesa', 'Escreve palavras não canônicas.'),
('Pautas', 304, '2º Ano', 'Atividades Esportivas (Lutas)', 'SER017', 'atividades_esportivas', 'Mantém postura adequada e segurança.'),
('Pautas', 305, '2º Ano', 'Atividades Esportivas (Lutas)', 'SER017', 'atividades_esportivas', 'Mantém base estável sob oposição leve.'),
('Pautas', 306, '2º Ano', 'Atividades Esportivas (Lutas)', 'SER017', 'atividades_esportivas', 'Executa movimento sem perder equilíbrio.'),
('Pautas', 307, '2º Ano', 'Atividades Esportivas (Lutas)', 'SER017', 'atividades_esportivas', 'Mantém estabilidade durante deslocamento.'),
('Pautas', 308, '2º Ano', 'Atividades Esportivas (Capoeira)', 'SER017', 'atividades_esportivas', 'Mantém ginga contínua com postura adequada.'),
('Pautas', 309, '2º Ano', 'Atividades Esportivas (Capoeira)', 'SER017', 'atividades_esportivas', 'Seleciona esquiva adequada ao comando.'),
('Pautas', 310, '2º Ano', 'Atividades Esportivas (Capoeira)', 'SER017', 'atividades_esportivas', 'Executa armada adaptada com equilíbrio.'),
('Pautas', 311, '2º Ano', 'Atividades Esportivas (Capoeira)', 'SER017', 'atividades_esportivas', 'Ajusta velocidade do jogo ao ritmo indicado.'),
('Pautas', 312, '2º Ano', 'Atividades Esportivas (Esportes)', 'SER017', 'atividades_esportivas', 'Completa percurso sem derrubar obstáculos.'),
('Pautas', 313, '2º Ano', 'Atividades Esportivas (Esportes)', 'SER017', 'atividades_esportivas', 'Realiza sequência de saltos.'),
('Pautas', 314, '2º Ano', 'Atividades Esportivas (Esportes)', 'SER017', 'atividades_esportivas', 'Arremessa alcançando distância proposta.'),
('Pautas', 315, '2º Ano', 'Atividades Esportivas (Esportes)', 'SER017', 'atividades_esportivas', 'Completa circuito motor.'),
('Pautas', 316, '2º Ano', 'Recreação e Jogos', 'SER017', 'recreacao_jogos', 'Realiza captura correta.'),
('Pautas', 317, '2º Ano', 'Recreação e Jogos', 'SER017', 'recreacao_jogos', 'Aplica bloqueio simples.'),
('Pautas', 318, '2º Ano', 'Recreação e Jogos', 'SER017', 'recreacao_jogos', 'Executa captura simples.'),
('Pautas', 319, '2º Ano', 'Recreação e Jogos', 'SER017', 'recreacao_jogos', 'Apresenta regras aplicáveis.'),
('Pautas', 320, '2º Ano', '(Dança) Laboratório de Teatro, Dança e Música', 'SER017', 'danca', 'Executa sequência mantendo ritmo.'),
('Pautas', 321, '2º Ano', '(Dança) Laboratório de Teatro, Dança e Música', 'SER017', 'danca', 'Mantém alinhamento no grupo.'),
('Pautas', 322, '2º Ano', '(Dança) Laboratório de Teatro, Dança e Música', 'SER017', 'danca', 'Mantém posição combinada.'),
('Pautas', 323, '2º Ano', '(Dança) Laboratório de Teatro, Dança e Música', 'SER017', 'danca', 'Executa sequência completa.'),
('Pautas', 324, '2º Ano', '(Teatro) Laboratório de Teatro, Dança e Música', 'SER017', 'teatro', 'Utiliza o corpo para expressar ideias simples.'),
('Pautas', 325, '2º Ano', '(Teatro) Laboratório de Teatro, Dança e Música', 'SER017', 'teatro', 'Participa de jogos simbólicos com envolvimento.'),
('Pautas', 326, '2º Ano', '(Teatro) Laboratório de Teatro, Dança e Música', 'SER017', 'teatro', 'Imita personagens com intencionalidade.'),
('Pautas', 327, '2º Ano', '(Teatro) Laboratório de Teatro, Dança e Música', 'SER017', 'teatro', 'Participa respeitando os combinados.'),
('Pautas', 328, '2º Ano', '(Música) Laboratório de Teatro, Dança e Música', 'SER017', 'musica', 'Produz sons utilizando corpo e objetos.'),
('Pautas', 329, '2º Ano', '(Música) Laboratório de Teatro, Dança e Música', 'SER017', 'musica', 'Participa cantando músicas conhecidas.'),
('Pautas', 330, '2º Ano', '(Música) Laboratório de Teatro, Dança e Música', 'SER017', 'musica', 'Diferencia sons graves/agudos e fortes/fracos.'),
('Pautas', 331, '2º Ano', '(Música) Laboratório de Teatro, Dança e Música', 'SER017', 'musica', 'Repete padrões com regularidade.'),
('Pautas', 332, '2º Ano', 'Artes Visuais', 'SER017', 'arte', 'Identifica elementos visuais básicos em imagens apresentadas.'),
('Pautas', 333, '2º Ano', 'Artes Visuais', 'SER017', 'arte', 'Produz desenho com intenção expressiva.'),
('Pautas', 334, '2º Ano', 'Artes Visuais', 'SER017', 'arte', 'Utiliza cores variadas em suas produções.'),
('Pautas', 335, '2º Ano', 'Artes Visuais', 'SER017', 'arte', 'Constrói formas simples em três dimensões.'),
('Pautas', 336, '2º Ano', 'Recomposição - Matemática', 'SER017', 'recomposicao_matematica', 'Escreve números com algarismos e por extenso.'),
('Pautas', 337, '2º Ano', 'Recomposição - Matemática', 'SER017', 'recomposicao_matematica', 'Lê e representa números (principalmente até dezenas, iniciando centenas).'),
('Pautas', 338, '2º Ano', 'Recomposição - Matemática', 'SER017', 'recomposicao_matematica', 'Resolve operações de adição com apoio de recursos manipuláveis e/ou digitais, registros pictóricos e algorítmicos (com e sem agrupamento na dezena).'),
('Pautas', 339, '2º Ano', 'Recomposição - Matemática', 'SER017', 'recomposicao_matematica', 'Resolver operações de subtração com apoio de recursos manipuláveis e/ou digitais, registros pictóricos e algorítmicos (com e sem desagrupamento na dezena).'),
('Pautas', 340, '2º Ano', 'Recomposição - Matemática', 'SER017', 'recomposicao_matematica', 'Identifica as características das figuras geométricas espaciais observando semelhanças e diferenças (cones, cilindros, esferas, pirâmides e blocos retangulares).'),
('Pautas', 341, '2º Ano', 'Recomposição - Matemática', 'SER017', 'recomposicao_matematica', 'Conhece diferentes tipos de relógio (digital e analógico).'),
('Pautas', 342, '2º Ano', 'Recomposição - Matemática', 'SER017', 'recomposicao_matematica', 'Lê horas em relógios digitais e analógicos (hora exata).'),
('Pautas', 343, '2º Ano', 'Recomposição - Língua Portuguesa', 'SER017', 'recomposicao_lingua_portuguesa', 'Reconta oralmente histórias com apoio de imagens.'),
('Pautas', 344, '2º Ano', 'Recomposição - Língua Portuguesa', 'SER017', 'recomposicao_lingua_portuguesa', 'Escrever palavras canônicas.'),
('Pautas', 345, '2º Ano', 'Recomposição - Língua Portuguesa', 'SER017', 'recomposicao_lingua_portuguesa', 'Escreve palavras não canônicas.');

SET @tipo_parecer_id := (
  SELECT id FROM tipos_avaliacao WHERE nome = 'Parecer' AND status = 1 ORDER BY id LIMIT 1
);

DROP TEMPORARY TABLE IF EXISTS tmp_mapa_pautas_resolvidas;
CREATE TEMPORARY TABLE tmp_mapa_pautas_resolvidas AS
SELECT
  mp.*,
  s.id AS serie_id,
  c.id AS componente_curricular_id
FROM tmp_mapa_pautas mp
LEFT JOIN series s ON s.codigo = mp.serie_codigo
LEFT JOIN componentes_curriculares c ON c.codigo = mp.componente_codigo;

SET @pautas_sem_resolucao := (
  SELECT COUNT(*)
  FROM tmp_mapa_pautas_resolvidas
  WHERE serie_id IS NULL OR componente_curricular_id IS NULL
);
SET @alternativas_ativas_parecer := (
  SELECT COUNT(*) FROM alternativas
  WHERE tipo_avaliacao_id = @tipo_parecer_id AND status = 1
);
SET @tem_erros :=
  IF(@tipo_parecer_id IS NULL, 1, 0)
  + IF(@alternativas_ativas_parecer = 0, 1, 0)
  + @pautas_sem_resolucao;

SELECT 'tipo_parecer_id' AS validacao, @tipo_parecer_id AS valor;
SELECT 'alternativas_ativas_parecer' AS validacao, @alternativas_ativas_parecer AS valor;
SELECT 'pautas_sem_resolucao' AS validacao, @pautas_sem_resolucao AS valor;
SELECT
  origem, linha, turma_planilha, componente_planilha, serie_codigo, componente_codigo, serie_id, componente_curricular_id
FROM tmp_mapa_pautas_resolvidas
WHERE serie_id IS NULL OR componente_curricular_id IS NULL
ORDER BY origem, linha;

-- Keep the grade/component catalogue aligned with the imported map.
INSERT IGNORE INTO serie_componente_curricular (serie_id, componente_curricular_id)
SELECT DISTINCT serie_id, componente_curricular_id
FROM tmp_mapa_pautas_resolvidas
WHERE @tem_erros = 0;

-- Keep class/component links available for evaluation scoping when a pair is missing.
INSERT IGNORE INTO turma_componente_professor
  (turma_id, componente_curricular_id, professor_id, tem_professor, created_at, updated_at)
SELECT DISTINCT t.id, r.componente_curricular_id, NULL, 0, NOW(), NOW()
FROM tmp_mapa_pautas_resolvidas r
JOIN turmas t ON t.id_serie = r.serie_id
WHERE @tem_erros = 0;

-- Reactivate matching pautas if they already exist.
UPDATE pautas p
JOIN tmp_mapa_pautas_resolvidas r
  ON p.tipo_avaliacao_id = @tipo_parecer_id
 AND p.serie_id = r.serie_id
 AND p.componente_curricular_id = r.componente_curricular_id
 AND p.texto = r.texto
SET p.status = 1, p.updated_at = NOW()
WHERE @tem_erros = 0;

-- Insert pautas that are still missing.
INSERT INTO pautas
  (tipo_avaliacao_id, texto, componente_curricular_id, serie_id, status, created_at, updated_at)
SELECT
  @tipo_parecer_id, r.texto, r.componente_curricular_id, r.serie_id, 1, NOW(), NOW()
FROM tmp_mapa_pautas_resolvidas r
WHERE @tem_erros = 0
  AND NOT EXISTS (
    SELECT 1
    FROM pautas p
    WHERE p.tipo_avaliacao_id = @tipo_parecer_id
      AND p.serie_id = r.serie_id
      AND p.componente_curricular_id = r.componente_curricular_id
      AND p.texto = r.texto
  );

-- Link every imported pauta to all active Parecer alternatives in the current database.
INSERT IGNORE INTO alternativa_pauta (pauta_id, alternativa_id, created_at, updated_at)
SELECT DISTINCT p.id, a.id, NOW(), NOW()
FROM tmp_mapa_pautas_resolvidas r
JOIN pautas p
  ON p.tipo_avaliacao_id = @tipo_parecer_id
 AND p.serie_id = r.serie_id
 AND p.componente_curricular_id = r.componente_curricular_id
 AND p.texto = r.texto
JOIN alternativas a
  ON a.tipo_avaliacao_id = @tipo_parecer_id
 AND a.status = 1
WHERE @tem_erros = 0;

SELECT
  IF(@tem_erros = 0, 'OK - importacao concluida', 'ERRO - nenhuma insercao foi executada; veja as validacoes acima') AS resultado,
  @tem_erros AS total_erros;

SELECT
  COUNT(DISTINCT p.id) AS pautas_importadas_ou_reativadas,
  COUNT(ap.id) AS vinculos_alternativas
FROM tmp_mapa_pautas_resolvidas r
JOIN pautas p
  ON p.tipo_avaliacao_id = @tipo_parecer_id
 AND p.serie_id = r.serie_id
 AND p.componente_curricular_id = r.componente_curricular_id
 AND p.texto = r.texto
LEFT JOIN alternativa_pauta ap ON ap.pauta_id = p.id
WHERE @tem_erros = 0;

COMMIT;

DROP TEMPORARY TABLE IF EXISTS tmp_mapa_pautas_resolvidas;
DROP TEMPORARY TABLE IF EXISTS tmp_mapa_pautas;

-- Distribution by serie code:
-- SER003: 29
-- SER004: 32
-- SER005: 37
-- SER006: 42
-- SER010: 12
-- SER011: 13
-- SER012: 16
-- SER013: 8
-- SER016: 42
-- SER017: 42
-- srm_serie: 68
-- Distribution by component code:
-- arte: 16
-- atividades_esportivas: 24
-- ciencias: 5
-- corpo_gesto_movimento: 31
-- danca: 8
-- educacao_fisica: 12
-- escuta_fala_pensamento: 17
-- espaco_tempo_quantidade: 21
-- eu_outro_nos: 17
-- geografia: 5
-- historia: 9
-- ingles: 8
-- matematica: 18
-- musica: 8
-- portugues: 14
-- recomposicao_lingua_portuguesa: 6
-- recomposicao_matematica: 14
-- recreacao_jogos: 8
-- srm: 68
-- teatro: 8
-- tracos_sons_cores: 24
