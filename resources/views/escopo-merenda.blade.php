<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sistema de Estoque Escolar — Arquitetura</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,600;0,9..144,700;1,9..144,300&display=swap');

:root {
  --bg: #f7f6f2;
  --surface: #ffffff;
  --surface2: #f0ede6;
  --border: #ddd9ce;
  --accent: #c84b11;
  --accent2: #e8692a;
  --text: #1c1812;
  --text-dim: #7a6f5e;
  --text-light: #b0a48e;
  --green: #2d6a4f;
  --green-bg: #e8f5ee;
  --blue: #1a4a8a;
  --blue-bg: #e8f0fa;
  --purple: #5b2d8a;
  --purple-bg: #f0e8fa;
  --yellow: #7a5c00;
  --yellow-bg: #fdf5d8;
  --orange-bg: #fdf0e8;
}

* { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }

body {
  background: var(--bg);
  color: var(--text);
  font-family: 'DM Mono', monospace;
  line-height: 1.6;
}

.nav {
  position: sticky; top: 0; z-index: 100;
  background: var(--text); color: var(--bg);
  padding: 12px 40px;
  display: flex; align-items: center; justify-content: space-between;
  font-size: 11px; letter-spacing: 0.08em;
}
.nav-brand { font-family: 'Fraunces', serif; font-size: 15px; font-weight: 600; color: var(--accent2); }
.nav-links { display: flex; gap: 24px; }
.nav-links a { color: rgba(247,246,242,0.6); text-decoration: none; transition: color 0.2s; font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; }
.nav-links a:hover { color: var(--bg); }

.hero { padding: 80px 40px 60px; max-width: 1100px; margin: 0 auto; border-bottom: 1px solid var(--border); }
.hero-tag { font-size: 10px; text-transform: uppercase; letter-spacing: 0.15em; color: var(--accent); margin-bottom: 16px; }
.hero-title { font-family: 'Fraunces', serif; font-size: 52px; font-weight: 700; line-height: 1.1; margin-bottom: 16px; }
.hero-title em { font-style: italic; color: var(--accent); }
.hero-sub { font-size: 13px; color: var(--text-dim); max-width: 560px; line-height: 1.7; }
.hero-meta { display: flex; gap: 32px; margin-top: 40px; flex-wrap: wrap; }
.hero-meta-item { display: flex; flex-direction: column; gap: 4px; }
.hero-meta-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.12em; color: var(--text-light); }
.hero-meta-value { font-size: 13px; font-weight: 500; }

.section { max-width: 1100px; margin: 0 auto; padding: 60px 40px; border-bottom: 1px solid var(--border); }
.section-header { display: flex; align-items: baseline; gap: 16px; margin-bottom: 40px; }
.section-num { font-size: 11px; color: var(--text-light); letter-spacing: 0.1em; }
.section-title { font-family: 'Fraunces', serif; font-size: 28px; font-weight: 600; }

/* ACTORS */
.actors-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.actor-card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 24px; position: relative; overflow: hidden; transition: box-shadow 0.2s; }
.actor-card:hover { box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
.actor-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
.actor-card.escola::before { background: var(--blue); }
.actor-card.gestor::before { background: var(--green); }
.actor-card.admin::before  { background: var(--accent); }
.actor-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 18px; margin-bottom: 14px; }
.actor-icon.escola  { background: var(--blue-bg); }
.actor-icon.gestor  { background: var(--green-bg); }
.actor-icon.admin   { background: var(--orange-bg); }
.actor-name { font-family: 'Fraunces', serif; font-size: 18px; font-weight: 600; margin-bottom: 4px; }
.actor-role { font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-light); margin-bottom: 14px; }
.actor-actions { display: flex; flex-direction: column; gap: 6px; }
.actor-action { font-size: 11px; color: var(--text-dim); display: flex; align-items: flex-start; gap: 8px; }
.actor-action::before { content: '→'; color: var(--text-light); flex-shrink: 0; }

/* NODES */
.node { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 16px; transition: box-shadow 0.2s; }
.node:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.07); }
.node-layer { font-size: 9px; text-transform: uppercase; letter-spacing: 0.12em; margin-bottom: 6px; }
.node-title { font-family: 'Fraunces', serif; font-size: 18px; font-weight: 600; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid var(--border); }
.node-fields { display: flex; flex-direction: column; gap: 4px; }
.field { font-size: 10px; color: var(--text-dim); display: flex; align-items: center; gap: 8px; padding: 2px 0; }
.field-dot { width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; background: var(--border); }
.field.pk .field-dot { background: var(--yellow); }
.field.fk .field-dot { background: var(--blue); }
.field.note .field-dot { background: var(--green); }
.field.pk { color: var(--yellow); }
.field.fk { color: var(--blue); }
.field.note { color: var(--green); font-style: italic; }
.node.cat    { border-top: 3px solid var(--purple); }
.node.item   { border-top: 3px solid var(--purple); }
.node.escola { border-top: 3px solid var(--blue); }
.node.estoque{ border-top: 3px solid var(--blue); }
.node.inv    { border-top: 3px solid var(--accent); }
.node.dep    { border-top: 3px solid var(--green); }
.node.pedido { border-top: 3px solid var(--yellow); }
.node.mov    { border-top: 3px solid var(--yellow); }
.node.rom    { border-top: 3px solid var(--accent2); }
.node-layer.purple { color: var(--purple); }
.node-layer.blue   { color: var(--blue); }
.node-layer.orange { color: var(--accent); }
.node-layer.green  { color: var(--green); }
.node-layer.yellow { color: var(--yellow); }

/* FLOW */
.flow-container { display: flex; flex-direction: column; gap: 0; }
.flow-step-row { display: grid; grid-template-columns: 160px 1fr; gap: 0; position: relative; }
.flow-step-row:not(:last-child)::after { content: ''; position: absolute; left: 79px; top: 56px; bottom: -24px; width: 2px; background: var(--border); z-index: 0; }
.flow-actor-col { display: flex; flex-direction: column; align-items: center; padding-top: 16px; position: relative; z-index: 1; }
.flow-actor-badge { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; border: 2px solid var(--border); background: var(--surface); margin-bottom: 6px; }
.flow-actor-name { font-size: 9px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-light); text-align: center; }
.flow-content { padding: 16px 0 32px 24px; }
.flow-status-row { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
.flow-badge { padding: 4px 12px; border-radius: 4px; font-size: 10px; font-weight: 500; letter-spacing: 0.06em; border: 1px solid; }
.flow-badge.pending  { background: var(--yellow-bg); border-color: var(--yellow); color: var(--yellow); }
.flow-badge.approved { background: var(--blue-bg); border-color: var(--blue); color: var(--blue); }
.flow-badge.romaneio { background: var(--orange-bg); border-color: var(--accent); color: var(--accent); }
.flow-badge.sent     { background: var(--purple-bg); border-color: var(--purple); color: var(--purple); }
.flow-badge.received { background: var(--green-bg); border-color: var(--green); color: var(--green); }
.flow-title-text { font-family: 'Fraunces', serif; font-size: 16px; font-weight: 600; }
.flow-desc-text { font-size: 11px; color: var(--text-dim); margin-bottom: 12px; max-width: 600px; line-height: 1.8; }
.flow-effects { display: flex; gap: 8px; flex-wrap: wrap; }
.effect-tag { font-size: 10px; padding: 3px 10px; border-radius: 3px; border: 1px solid var(--border); color: var(--text-dim); background: var(--surface2); }
.effect-tag.db   { border-color: var(--blue); color: var(--blue); background: var(--blue-bg); }
.effect-tag.warn { border-color: var(--yellow); color: var(--yellow); background: var(--yellow-bg); }
.effect-tag.ok   { border-color: var(--green); color: var(--green); background: var(--green-bg); }

/* ROMANEIO */
.romaneio-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.romaneio-card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
.romaneio-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.romaneio-title { font-family: 'Fraunces', serif; font-size: 16px; font-weight: 600; }
.romaneio-badge { font-size: 9px; padding: 3px 8px; border-radius: 3px; text-transform: uppercase; letter-spacing: 0.1em; }
.romaneio-badge.geral  { background: var(--green-bg); color: var(--green); }
.romaneio-badge.escola { background: var(--blue-bg); color: var(--blue); }
.romaneio-body { padding: 20px; }
.romaneio-recipient { font-size: 10px; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 12px; }
.mock-table { width: 100%; font-size: 10px; border-collapse: collapse; }
.mock-table th { text-align: left; padding: 6px 8px; background: var(--surface2); color: var(--text-light); font-weight: 500; font-size: 9px; text-transform: uppercase; letter-spacing: 0.08em; }
.mock-table td { padding: 6px 8px; border-top: 1px solid var(--border); color: var(--text-dim); }
.mock-table tr:hover td { background: var(--surface2); }
.sign-line { margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--border); font-size: 10px; color: var(--text-light); display: flex; gap: 24px; flex-wrap: wrap; }

/* SALDO */
.saldo-explainer { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 24px; }
.saldo-card { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 20px; text-align: center; }
.saldo-value { font-family: 'Fraunces', serif; font-size: 40px; font-weight: 700; line-height: 1; margin-bottom: 6px; }
.saldo-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-light); margin-bottom: 8px; }
.saldo-desc { font-size: 10px; color: var(--text-dim); line-height: 1.6; }
.saldo-formula { background: var(--text); color: var(--bg); border-radius: 8px; padding: 20px 24px; font-size: 12px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.saldo-formula span { color: rgba(247,246,242,0.4); }
.saldo-formula strong { color: #fb923c; font-weight: 500; }
.saldo-formula em { color: #6ee7b7; font-style: normal; }

.footer { max-width: 1100px; margin: 0 auto; padding: 40px; display: flex; justify-content: space-between; align-items: center; font-size: 10px; color: var(--text-light); }

.layer-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.12em; margin-bottom: 10px; margin-top: 28px; }
</style>
</head>
<body>

<nav class="nav">
  <div class="nav-brand">EstoqueEscolar</div>
  <div class="nav-links">
    <a href="#atores">Atores</a>
    <a href="#modelo">Modelo</a>
    <a href="#fluxo">Fluxo</a>
    <a href="#romaneio">Romaneio</a>
    <a href="#saldo">Saldo</a>
    <a class="nav-brand" style="color: var(--accent2)" href="/exemplo">Exemplo</a>
  </div>
</nav>

<div class="hero">
  <div class="hero-tag">// Documento de Arquitetura — v1.0</div>
  <h1 class="hero-title">Sistema de <em>Estoque</em><br>Escolar Municipal</h1>
  <p class="hero-sub">Gestão centralizada de estoque para rede municipal de ensino. Um depósito central distribui itens para os inventários de cada escola através de um fluxo de pedidos rastreável e auditável.</p>
  <div class="hero-meta">
    <div class="hero-meta-item">
      <span class="hero-meta-label">Entidades</span>
      <span class="hero-meta-value">9 tabelas</span>
    </div>
    <div class="hero-meta-item">
      <span class="hero-meta-label">Atores</span>
      <span class="hero-meta-value">3 perfis</span>
    </div>
    <div class="hero-meta-item">
      <span class="hero-meta-label">Fluxo</span>
      <span class="hero-meta-value">5 etapas</span>
    </div>
    <div class="hero-meta-item">
      <span class="hero-meta-label">Stack</span>
      <span class="hero-meta-value">Laravel + Filament</span>
    </div>
  </div>
</div>

<!-- ATORES -->
<section class="section" id="atores">
  <div class="section-header">
    <span class="section-num">01</span>
    <h2 class="section-title">Atores do Sistema</h2>
  </div>
  <div class="actors-grid">
    <div class="actor-card escola">
      <div class="actor-icon escola">🏫</div>
      <div class="actor-name">Escola</div>
      <div class="actor-role">Solicitante de recursos</div>
      <div class="actor-actions">
        <div class="actor-action">Cria pedidos de itens para a semana</div>
        <div class="actor-action">Visualiza status do pedido em tempo real</div>
        <div class="actor-action">Confirma recebimento da carga</div>
        <div class="actor-action">Consulta inventário local por estoque</div>
        <div class="actor-action">Registra baixas de consumo interno</div>
        <div class="actor-action">Solicita devoluções ao depósito</div>
      </div>
    </div>
    <div class="actor-card gestor">
      <div class="actor-icon gestor">🗂️</div>
      <div class="actor-name">Gestor do Depósito</div>
      <div class="actor-role">Operador central</div>
      <div class="actor-actions">
        <div class="actor-action">Analisa e aprova pedidos das escolas</div>
        <div class="actor-action">Ajusta quantidades aprovadas se necessário</div>
        <div class="actor-action">Gera romaneios de remessa</div>
        <div class="actor-action">Registra entradas de fornecedores</div>
        <div class="actor-action">Controla saldo disponível e reservado</div>
        <div class="actor-action">Emite relatórios de distribuição</div>
      </div>
    </div>
    <div class="actor-card admin">
      <div class="actor-icon admin">⚙️</div>
      <div class="actor-name">Administrador</div>
      <div class="actor-role">Configuração e supervisão</div>
      <div class="actor-actions">
        <div class="actor-action">Cadastra escolas, estoques e categorias</div>
        <div class="actor-action">Gerencia catálogo de itens</div>
        <div class="actor-action">Define permissões por perfil de usuário</div>
        <div class="actor-action">Audita movimentações históricas</div>
        <div class="actor-action">Acessa relatórios consolidados</div>
        <div class="actor-action">Configura alertas de estoque mínimo</div>
      </div>
    </div>
  </div>
</section>

<!-- MODELO -->
<section class="section" id="modelo">
  <div class="section-header">
    <span class="section-num">02</span>
    <h2 class="section-title">Modelo de Dados</h2>
  </div>

  <p class="layer-label" style="color:var(--purple); margin-top:0;">Camada de Catálogo</p>
  <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:4px;">
    <div class="node cat">
      <div class="node-layer purple">catálogo</div>
      <div class="node-title">Categoria</div>
      <div class="node-fields">
        <div class="field pk"><div class="field-dot"></div>id</div>
        <div class="field"><div class="field-dot"></div>nome</div>
        <div class="field"><div class="field-dot"></div>descrição</div>
        <div class="field note"><div class="field-dot"></div>ex: Alimentos, Limpeza, Material Escolar</div>
      </div>
    </div>
    <div class="node item">
      <div class="node-layer purple">catálogo</div>
      <div class="node-title">Item</div>
      <div class="node-fields">
        <div class="field pk"><div class="field-dot"></div>id</div>
        <div class="field fk"><div class="field-dot"></div>categoria_id</div>
        <div class="field"><div class="field-dot"></div>nome</div>
        <div class="field"><div class="field-dot"></div>unidade_medida</div>
        <div class="field"><div class="field-dot"></div>descrição</div>
        <div class="field note"><div class="field-dot"></div>catálogo único — compartilhado por todo sistema</div>
      </div>
    </div>
  </div>

  <p class="layer-label" style="color:var(--blue);">Camada de Estrutura</p>
  <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:4px;">
    <div class="node escola">
      <div class="node-layer blue">estrutura</div>
      <div class="node-title">Escola</div>
      <div class="node-fields">
        <div class="field pk"><div class="field-dot"></div>id</div>
        <div class="field"><div class="field-dot"></div>nome</div>
        <div class="field"><div class="field-dot"></div>endereco</div>
        <div class="field"><div class="field-dot"></div>responsável</div>
        <div class="field"><div class="field-dot"></div>telefone</div>
      </div>
    </div>
    <div class="node estoque">
      <div class="node-layer blue">estrutura</div>
      <div class="node-title">Estoque</div>
      <div class="node-fields">
        <div class="field pk"><div class="field-dot"></div>id</div>
        <div class="field fk"><div class="field-dot"></div>escola_id</div>
        <div class="field"><div class="field-dot"></div>nome</div>
        <div class="field note"><div class="field-dot"></div>ex: Cozinha, Almoxarifado, Depósito Seco</div>
        <div class="field note"><div class="field-dot"></div>cada escola pode ter N estoques</div>
      </div>
    </div>
  </div>

  <p class="layer-label" style="color:var(--accent);">Camada de Inventário</p>
  <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:4px;">
    <div class="node inv">
      <div class="node-layer orange">inventário — escola</div>
      <div class="node-title">Inventário</div>
      <div class="node-fields">
        <div class="field pk"><div class="field-dot"></div>id</div>
        <div class="field fk"><div class="field-dot"></div>item_id</div>
        <div class="field fk"><div class="field-dot"></div>estoque_id</div>
        <div class="field fk"><div class="field-dot"></div>escola_id</div>
        <div class="field"><div class="field-dot"></div>quantidade</div>
        <div class="field"><div class="field-dot"></div>qtd_reservada</div>
        <div class="field"><div class="field-dot"></div>validade</div>
        <div class="field"><div class="field-dot"></div>lote</div>
        <div class="field note"><div class="field-dot"></div>saldo creditado ao confirmar recebimento</div>
      </div>
    </div>
    <div class="node dep">
      <div class="node-layer green">inventário — depósito central</div>
      <div class="node-title">Depósito</div>
      <div class="node-fields">
        <div class="field pk"><div class="field-dot"></div>id</div>
        <div class="field fk"><div class="field-dot"></div>item_id</div>
        <div class="field"><div class="field-dot"></div>quantidade</div>
        <div class="field"><div class="field-dot"></div>qtd_reservada</div>
        <div class="field"><div class="field-dot"></div>validade</div>
        <div class="field"><div class="field-dot"></div>lote</div>
        <div class="field note"><div class="field-dot"></div>matriz de distribuição — origem de tudo</div>
        <div class="field note"><div class="field-dot"></div>recebe entradas de fornecedores</div>
      </div>
    </div>
  </div>

  <p class="layer-label" style="color:var(--yellow);">Camada de Operação</p>
  <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
    <div class="node pedido">
      <div class="node-layer yellow">operação</div>
      <div class="node-title">Pedido</div>
      <div class="node-fields">
        <div class="field pk"><div class="field-dot"></div>id</div>
        <div class="field fk"><div class="field-dot"></div>escola_id</div>
        <div class="field fk"><div class="field-dot"></div>romaneio_id</div>
        <div class="field"><div class="field-dot"></div>status</div>
        <div class="field"><div class="field-dot"></div>observação</div>
        <div class="field"><div class="field-dot"></div>created_at</div>
        <div style="border-top:1px solid var(--border);margin:8px 0;"></div>
        <div class="field pk"><div class="field-dot"></div>item_pedido.id</div>
        <div class="field fk"><div class="field-dot"></div>item_pedido.item_id</div>
        <div class="field"><div class="field-dot"></div>qtd_solicitada</div>
        <div class="field"><div class="field-dot"></div>qtd_aprovada</div>
      </div>
    </div>
    <div class="node mov">
      <div class="node-layer yellow">auditoria</div>
      <div class="node-title">Movimentação</div>
      <div class="node-fields">
        <div class="field pk"><div class="field-dot"></div>id</div>
        <div class="field fk"><div class="field-dot"></div>pedido_id</div>
        <div class="field fk"><div class="field-dot"></div>item_id</div>
        <div class="field fk"><div class="field-dot"></div>origem_deposito_id</div>
        <div class="field fk"><div class="field-dot"></div>destino_inventario_id</div>
        <div class="field"><div class="field-dot"></div>tipo</div>
        <div class="field note"><div class="field-dot"></div>ENTRADA | TRANSFERÊNCIA</div>
        <div class="field note"><div class="field-dot"></div>BAIXA | DEVOLUÇÃO</div>
        <div class="field"><div class="field-dot"></div>quantidade</div>
        <div class="field"><div class="field-dot"></div>data</div>
        <div class="field"><div class="field-dot"></div>responsável</div>
      </div>
    </div>
    <div class="node rom">
      <div class="node-layer orange">distribuição</div>
      <div class="node-title">Romaneio</div>
      <div class="node-fields">
        <div class="field pk"><div class="field-dot"></div>id</div>
        <div class="field fk"><div class="field-dot"></div>gestor_id</div>
        <div class="field"><div class="field-dot"></div>data_remessa</div>
        <div class="field"><div class="field-dot"></div>status</div>
        <div class="field note"><div class="field-dot"></div>GERADO → ENVIADO → CONCLUÍDO</div>
        <div style="border-top:1px solid var(--border);margin:8px 0;"></div>
        <div class="field note"><div class="field-dot"></div>gera PDF geral (depósito)</div>
        <div class="field note"><div class="field-dot"></div>gera PDF individual por escola</div>
      </div>
    </div>
  </div>
</section>

<!-- FLUXO -->
<section class="section" id="fluxo">
  <div class="section-header">
    <span class="section-num">03</span>
    <h2 class="section-title">Fluxo Operacional Completo</h2>
  </div>
  <div class="flow-container">

    <div class="flow-step-row">
      <div class="flow-actor-col">
        <div class="flow-actor-badge">🏫</div>
        <div class="flow-actor-name">Escola</div>
      </div>
      <div class="flow-content">
        <div class="flow-status-row">
          <span class="flow-badge pending">PENDENTE</span>
          <span class="flow-title-text">Criação do Pedido</span>
        </div>
        <p class="flow-desc-text">A escola acessa "Novo Pedido", seleciona os itens desejados com as quantidades para a semana e submete. O pedido é criado com status PENDENTE e a notificação é enviada automaticamente para o gestor do depósito.</p>
        <div class="flow-effects">
          <span class="effect-tag db">INSERT pedido</span>
          <span class="effect-tag db">INSERT item_pedido[]</span>
          <span class="effect-tag">notifica gestor</span>
        </div>
      </div>
    </div>

    <div class="flow-step-row">
      <div class="flow-actor-col">
        <div class="flow-actor-badge">🗂️</div>
        <div class="flow-actor-name">Gestor</div>
      </div>
      <div class="flow-content">
        <div class="flow-status-row">
          <span class="flow-badge approved">APROVADO</span>
          <span class="flow-title-text">Análise e Aprovação</span>
        </div>
        <p class="flow-desc-text">O gestor analisa o pedido verificando saldo disponível no depósito. Pode aprovar integralmente ou ajustar quantidades (qtd_aprovada pode ser menor que qtd_solicitada). Ao aprovar, o sistema bloqueia a quantidade como reservada — impedindo conflito com outros pedidos simultâneos de outras escolas.</p>
        <div class="flow-effects">
          <span class="effect-tag db">pedido.status → APROVADO</span>
          <span class="effect-tag warn">deposito.qtd_reservada += qtd_aprovada</span>
          <span class="effect-tag">notifica escola</span>
          <span class="effect-tag">valida saldo disponível</span>
        </div>
      </div>
    </div>

    <div class="flow-step-row">
      <div class="flow-actor-col">
        <div class="flow-actor-badge">🗂️</div>
        <div class="flow-actor-name">Gestor</div>
      </div>
      <div class="flow-content">
        <div class="flow-status-row">
          <span class="flow-badge romaneio">ROMANEIO GERADO</span>
          <span class="flow-title-text">Geração da Remessa</span>
        </div>
        <p class="flow-desc-text">O gestor agrupa pedidos aprovados em uma remessa e aciona a geração do romaneio. O sistema produz dois documentos: (1) Romaneio Geral — visão consolidada de tudo que sai do depósito para o gestor separar a carga; (2) Romaneio por Escola — entregue ao transportador para conferência e assinatura na entrega.</p>
        <div class="flow-effects">
          <span class="effect-tag db">INSERT romaneio</span>
          <span class="effect-tag db">pedidos vinculados ao romaneio</span>
          <span class="effect-tag">PDF geral gerado</span>
          <span class="effect-tag">PDFs por escola gerados</span>
        </div>
      </div>
    </div>

    <div class="flow-step-row">
      <div class="flow-actor-col">
        <div class="flow-actor-badge">🚚</div>
        <div class="flow-actor-name">Logística</div>
      </div>
      <div class="flow-content">
        <div class="flow-status-row">
          <span class="flow-badge sent">ENVIADO</span>
          <span class="flow-title-text">Saída do Depósito</span>
        </div>
        <p class="flow-desc-text">A carga é separada fisicamente no depósito usando o romaneio geral como guia e despachada. O gestor marca o romaneio como ENVIADO. Neste ponto os itens ainda estão contabilizados no depósito (como reservados), pois o recebimento ainda não foi confirmado pela escola.</p>
        <div class="flow-effects">
          <span class="effect-tag db">romaneio.status → ENVIADO</span>
          <span class="effect-tag db">pedido.status → ENVIADO</span>
          <span class="effect-tag">notifica escolas da remessa</span>
        </div>
      </div>
    </div>

    <div class="flow-step-row">
      <div class="flow-actor-col">
        <div class="flow-actor-badge">🏫</div>
        <div class="flow-actor-name">Escola</div>
      </div>
      <div class="flow-content">
        <div class="flow-status-row">
          <span class="flow-badge received">RECEBIDO</span>
          <span class="flow-title-text">Confirmação de Recebimento</span>
        </div>
        <p class="flow-desc-text">A escola confere os itens físicos com o romaneio em mãos e confirma no sistema. Este é o gatilho da movimentação real: o depósito é debitado, a reserva é liberada, e o inventário da escola é creditado. Um registro de Movimentação do tipo TRANSFERÊNCIA é criado para rastreabilidade completa.</p>
        <div class="flow-effects">
          <span class="effect-tag ok">deposito.quantidade -= qtd_aprovada</span>
          <span class="effect-tag ok">deposito.qtd_reservada -= qtd_aprovada</span>
          <span class="effect-tag ok">inventário.quantidade += qtd_aprovada</span>
          <span class="effect-tag db">INSERT movimentacao (TRANSFERÊNCIA)</span>
          <span class="effect-tag db">pedido.status → RECEBIDO</span>
        </div>
      </div>
    </div>

  </div>
</section>

<!-- ROMANEIO -->
<section class="section" id="romaneio">
  <div class="section-header">
    <span class="section-num">04</span>
    <h2 class="section-title">Romaneios de Remessa</h2>
  </div>
  <div class="romaneio-grid">
    <div class="romaneio-card">
      <div class="romaneio-header">
        <div class="romaneio-title">Romaneio Geral</div>
        <span class="romaneio-badge geral">Gestor do Depósito</span>
      </div>
      <div class="romaneio-body">
        <div class="romaneio-recipient">Remessa #042 — 10/03/2026 — Todas as Escolas</div>
        <table class="mock-table">
          <thead><tr><th>Item</th><th>Qtd Total</th><th>Destinos</th></tr></thead>
          <tbody>
            <tr><td>Feijão Carioca 1kg</td><td>80 un</td><td>E.A (30), E.B (20), E.C (30)</td></tr>
            <tr><td>Arroz Branco 5kg</td><td>40 un</td><td>E.A (15), E.B (25)</td></tr>
            <tr><td>Óleo de Soja 900ml</td><td>60 un</td><td>E.A (20), E.B (20), E.C (20)</td></tr>
            <tr><td>Sal Refinado 1kg</td><td>30 un</td><td>E.B (10), E.C (20)</td></tr>
            <tr><td>Açúcar Cristal 1kg</td><td>50 un</td><td>E.A (25), E.C (25)</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="romaneio-card">
      <div class="romaneio-header">
        <div class="romaneio-title">Romaneio por Escola</div>
        <span class="romaneio-badge escola">Transportador / Escola</span>
      </div>
      <div class="romaneio-body">
        <div class="romaneio-recipient">E.M. João da Silva — Remessa #042 — 10/03/2026</div>
        <table class="mock-table">
          <thead><tr><th>Item</th><th>Qtd</th><th>Recebido</th><th>Ass.</th></tr></thead>
          <tbody>
            <tr><td>Feijão Carioca 1kg</td><td>30 un</td><td>☐</td><td>___</td></tr>
            <tr><td>Arroz Branco 5kg</td><td>15 un</td><td>☐</td><td>___</td></tr>
            <tr><td>Óleo de Soja 900ml</td><td>20 un</td><td>☐</td><td>___</td></tr>
            <tr><td>Açúcar Cristal 1kg</td><td>25 un</td><td>☐</td><td>___</td></tr>
          </tbody>
        </table>
        <div class="sign-line">
          <span>Entregue por: ___________________</span>
          <span>Recebido por: ___________________</span>
          <span>Data: ___/___/___</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- SALDO -->
<section class="section" id="saldo">
  <div class="section-header">
    <span class="section-num">05</span>
    <h2 class="section-title">Controle de Saldo e Reserva</h2>
  </div>
  <div class="saldo-explainer">
    <div class="saldo-card">
      <div class="saldo-value" style="color:var(--text)">100</div>
      <div class="saldo-label">Quantidade Total</div>
      <div class="saldo-desc">Total físico em estoque. Só muda quando um recebimento é confirmado por uma escola ou uma entrada de fornecedor é registrada.</div>
    </div>
    <div class="saldo-card">
      <div class="saldo-value" style="color:var(--yellow)">35</div>
      <div class="saldo-label">Qtd. Reservada</div>
      <div class="saldo-desc">Bloqueado ao aprovar pedidos. Garante que o mesmo item não seja comprometido para duas escolas ao mesmo tempo.</div>
    </div>
    <div class="saldo-card">
      <div class="saldo-value" style="color:var(--green)">65</div>
      <div class="saldo-label">Saldo Disponível</div>
      <div class="saldo-desc">Único que pode ser comprometido em novos pedidos. Calculado em tempo real. Aprovação só é permitida se saldo ≥ qtd solicitada.</div>
    </div>
  </div>
  <div class="saldo-formula">
    <em>saldo_disponivel</em>
    <span>=</span>
    <strong>quantidade_total</strong>
    <span>−</span>
    <strong>qtd_reservada</strong>
    <span style="margin-left:24px; color:rgba(247,246,242,0.4);">regra:</span>
    <em>aprovação só permitida se saldo_disponivel ≥ qtd_aprovada</em>
  </div>

  <div style="margin-top:20px; display:grid; grid-template-columns:1fr 1fr; gap:16px;">
    <div style="background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:20px;">
      <p style="font-size:10px; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-light); margin-bottom:12px;">qtd_reservada aumenta quando</p>
      <div class="actor-actions">
        <div class="actor-action" style="font-size:11px; color:var(--text-dim);">Gestor aprova um pedido</div>
      </div>
    </div>
    <div style="background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:20px;">
      <p style="font-size:10px; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-light); margin-bottom:12px;">qtd_reservada diminui quando</p>
      <div class="actor-actions">
        <div class="actor-action" style="font-size:11px; color:var(--text-dim);">Escola confirma recebimento — reserva vira débito real</div>
        <div class="actor-action" style="font-size:11px; color:var(--text-dim);">Pedido cancelado pelo gestor ou pela escola</div>
      </div>
    </div>
  </div>
</section>

<div class="footer">
  <span>Sistema de Estoque Escolar Municipal — Arquitetura v1.0</span>
  <span>Documento interno — sujeito a alterações</span>
</div>

</body>
</html>