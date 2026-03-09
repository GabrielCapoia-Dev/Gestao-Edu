<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EstoqueEscolar — Protótipo v2</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Mono:ital,wght@0,300;0,400;0,500;1,300&family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,600;0,9..144,700;1,9..144,400&display=swap');
:root{
  --bg:#f7f6f2;--surface:#fff;--surface2:#f0ede6;--border:#ddd9ce;
  --accent:#c84b11;--accent2:#e8692a;
  --text:#1c1812;--dim:#7a6f5e;--light:#b0a48e;
  --green:#2d6a4f;--green-bg:#e8f5ee;
  --blue:#1a4a8a;--blue-bg:#e8f0fa;
  --purple:#5b2d8a;--purple-bg:#f0e8fa;
  --yellow:#7a5c00;--yellow-bg:#fdf5d8;
  --orange-bg:#fdf0e8;
  --red:#8a1a1a;--red-bg:#fae8e8;
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{background:var(--bg);color:var(--text);font-family:'DM Mono',monospace;line-height:1.5;font-size:13px}

/* SHELL */
.shell{display:flex;height:100vh;overflow:hidden}
.sidebar{width:224px;flex-shrink:0;background:var(--text);display:flex;flex-direction:column;overflow-y:auto}
.sidebar-brand{padding:20px 18px 16px;border-bottom:1px solid rgba(255,255,255,.07)}
.brand-name{font-family:'Fraunces',serif;font-size:16px;font-weight:700;color:var(--accent2)}
.brand-role{font-size:9px;text-transform:uppercase;letter-spacing:.1em;color:rgba(247,246,242,.35);margin-top:2px}
.sidebar-section{padding:14px 12px 4px;font-size:9px;text-transform:uppercase;letter-spacing:.12em;color:rgba(247,246,242,.25)}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 18px;font-size:11px;color:rgba(247,246,242,.55);cursor:pointer;transition:all .15s;border-left:2px solid transparent}
.nav-item:hover{color:rgba(247,246,242,.9);background:rgba(255,255,255,.04)}
.nav-item.active{color:#fff;border-left-color:var(--accent2);background:rgba(255,255,255,.06)}
.nav-icon{font-size:13px;width:16px;text-align:center}
.notif-dot{width:7px;height:7px;border-radius:50%;background:var(--accent);display:inline-block;margin-left:4px;vertical-align:middle}
.role-switcher{margin-top:auto;padding:12px;border-top:1px solid rgba(255,255,255,.07)}
.role-label{font-size:9px;text-transform:uppercase;letter-spacing:.1em;color:rgba(247,246,242,.3);margin-bottom:8px}
.role-btn{display:block;width:100%;padding:7px 10px;margin-bottom:4px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:5px;color:rgba(247,246,242,.6);font-family:'DM Mono',monospace;font-size:10px;cursor:pointer;text-align:left;transition:all .15s}
.role-btn:hover{background:rgba(255,255,255,.1);color:#fff}
.role-btn.active{border-color:var(--accent2);color:var(--accent2);background:rgba(232,105,42,.1)}

/* MAIN */
.main{flex:1;overflow-y:auto;display:flex;flex-direction:column}
.topbar{background:var(--surface);border-bottom:1px solid var(--border);padding:12px 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10}
.topbar-title{font-family:'Fraunces',serif;font-size:18px;font-weight:600}
.topbar-sub{font-size:10px;color:var(--light);margin-top:1px}
.topbar-actions{display:flex;gap:8px;align-items:center}
.screen{display:none;flex-direction:column;flex:1}
.screen.active{display:flex}
.content{padding:22px 28px;flex:1}

/* BUTTONS */
.btn{padding:7px 14px;border-radius:5px;font-family:'DM Mono',monospace;font-size:11px;cursor:pointer;border:1px solid;transition:all .15s;display:inline-flex;align-items:center;gap:6px;white-space:nowrap}
.btn-primary{background:var(--accent);border-color:var(--accent);color:#fff}
.btn-primary:hover{background:var(--accent2);border-color:var(--accent2)}
.btn-secondary{background:var(--surface);border-color:var(--border);color:var(--dim)}
.btn-secondary:hover{border-color:var(--dim);color:var(--text)}
.btn-ghost{background:transparent;border-color:transparent;color:var(--dim)}
.btn-ghost:hover{background:var(--surface2);color:var(--text)}
.btn-success{background:var(--green);border-color:var(--green);color:#fff}
.btn-success:hover{opacity:.88}
.btn-danger{background:var(--red);border-color:var(--red);color:#fff}
.btn-danger:hover{opacity:.88}
.btn-sm{padding:4px 10px;font-size:10px}
.btn-warn{background:var(--yellow-bg);border-color:var(--yellow);color:var(--yellow)}

/* BADGES */
.badge{padding:3px 8px;border-radius:3px;font-size:9px;text-transform:uppercase;letter-spacing:.08em;border:1px solid;white-space:nowrap}
.badge-pending{background:var(--yellow-bg);border-color:var(--yellow);color:var(--yellow)}
.badge-approved{background:var(--blue-bg);border-color:var(--blue);color:var(--blue)}
.badge-sent{background:var(--purple-bg);border-color:var(--purple);color:var(--purple)}
.badge-received{background:var(--green-bg);border-color:var(--green);color:var(--green)}
.badge-canceled{background:var(--red-bg);border-color:var(--red);color:var(--red)}
.badge-info{background:var(--surface2);border-color:var(--border);color:var(--dim)}
.badge-gerado{background:var(--orange-bg);border-color:var(--accent);color:var(--accent)}
.badge-concluido{background:var(--green-bg);border-color:var(--green);color:var(--green)}

/* CARDS */
.card{background:var(--surface);border:1px solid var(--border);border-radius:8px}
.card-header{padding:13px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
.card-title{font-family:'Fraunces',serif;font-size:15px;font-weight:600}
.card-body{padding:18px}

/* STATS */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:16px}
.stat-value{font-family:'Fraunces',serif;font-size:28px;font-weight:700;line-height:1;margin-bottom:4px}
.stat-label{font-size:10px;color:var(--light);text-transform:uppercase;letter-spacing:.08em}

/* TABLES */
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:11px}
thead th{padding:8px 12px;background:var(--surface2);color:var(--light);font-weight:500;font-size:9px;text-transform:uppercase;letter-spacing:.08em;text-align:left;border-bottom:1px solid var(--border)}
tbody td{padding:9px 12px;border-bottom:1px solid var(--border);color:var(--dim);vertical-align:middle}
tbody tr:hover td{background:var(--surface2)}
tbody tr:last-child td{border-bottom:none}

/* FORM */
.form-grid{display:grid;gap:14px}
.fg2{grid-template-columns:1fr 1fr}
.fg3{grid-template-columns:1fr 1fr 1fr}
.form-group{display:flex;flex-direction:column;gap:5px}
.form-label{font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--dim)}
.form-control{padding:7px 10px;border-radius:5px;border:1px solid var(--border);background:var(--surface);font-family:'DM Mono',monospace;font-size:11px;color:var(--text);outline:none;transition:border-color .15s;width:100%}
.form-control:focus{border-color:var(--accent)}
select.form-control{cursor:pointer}
textarea.form-control{resize:vertical;min-height:72px}

/* ALERT */
.alert{padding:11px 15px;border-radius:6px;border:1px solid;font-size:11px;display:flex;align-items:flex-start;gap:10px;margin-bottom:16px;line-height:1.5}
.alert-info{background:var(--blue-bg);border-color:var(--blue);color:var(--blue)}
.alert-success{background:var(--green-bg);border-color:var(--green);color:var(--green)}
.alert-warn{background:var(--yellow-bg);border-color:var(--yellow);color:var(--yellow)}
.alert-danger{background:var(--red-bg);border-color:var(--red);color:var(--red)}

/* PROGRESS */
.progress-bar{height:6px;background:var(--border);border-radius:3px;overflow:hidden;margin-top:4px}
.progress-fill{height:100%;border-radius:3px}

/* TIMELINE */
.tl-row{display:grid;grid-template-columns:32px 1fr;gap:12px;position:relative}
.tl-row:not(:last-child)::before{content:'';position:absolute;left:15px;top:32px;bottom:0;width:2px;background:var(--border)}
.tl-dot{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;border:2px solid;flex-shrink:0;z-index:1;background:var(--surface)}
.tl-dot.done{border-color:var(--green);background:var(--green-bg)}
.tl-dot.current{border-color:var(--accent);background:var(--orange-bg)}
.tl-dot.pending{border-color:var(--border)}
.tl-content{padding:4px 0 20px}
.tl-title{font-size:12px;font-weight:500}
.tl-desc{font-size:10px;color:var(--light);margin-top:2px}

/* SDIV */
.sdiv{font-size:9px;text-transform:uppercase;letter-spacing:.12em;color:var(--light);margin:18px 0 10px;padding-bottom:6px;border-bottom:1px solid var(--border)}

/* MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(28,24,18,.55);z-index:200;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:var(--surface);border-radius:10px;border:1px solid var(--border);width:540px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.modal.wide{width:720px}
.modal-header{padding:17px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center}
.modal-title{font-family:'Fraunces',serif;font-size:17px;font-weight:600}
.modal-close{font-size:18px;cursor:pointer;color:var(--light);background:none;border:none;padding:0;line-height:1}
.modal-body{padding:20px}
.modal-footer{padding:13px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px}

/* TOAST */
.toast{position:fixed;bottom:24px;right:24px;z-index:300;background:var(--text);color:var(--bg);padding:11px 18px;border-radius:6px;font-size:11px;display:none;box-shadow:0 8px 24px rgba(0,0,0,.2)}
.toast.show{display:block;animation:slideUp .25s ease}
@keyframes slideUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

/* ROMANEIO PRINT PREVIEW */
.rom-preview{background:#fff;border:1px solid var(--border);border-radius:6px;font-size:11px}
.rom-preview-header{padding:14px 18px;background:var(--surface2);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:flex-start}
.rom-preview-body{padding:16px 18px}
.rom-sign{margin-top:16px;padding-top:12px;border-top:1px solid var(--border);display:flex;gap:24px;flex-wrap:wrap;font-size:10px;color:var(--light)}

/* INV ITEM */
.inv-row{display:grid;align-items:center;padding:9px 12px;border-bottom:1px solid var(--border);font-size:11px;transition:background .1s}
.inv-row:hover{background:var(--surface2)}
.inv-row:last-child{border-bottom:none}
.inv-name{font-weight:500;color:var(--text)}
.inv-cat{font-size:9px;color:var(--light);text-transform:uppercase;letter-spacing:.06em;margin-top:1px}

/* ITEM LINE IN PEDIDO FORM */
.item-line{display:grid;grid-template-columns:1fr 100px 90px 32px;gap:8px;align-items:center;padding:8px 12px;border-bottom:1px solid var(--border)}
.item-line:last-child{border-bottom:none}

/* EMPTY STATE */
.empty{text-align:center;padding:48px 24px;color:var(--light)}
.empty-icon{font-size:32px;margin-bottom:12px}
.empty-title{font-family:'Fraunces',serif;font-size:16px;color:var(--dim);margin-bottom:4px}
</style>
</head>
<body>

<div class="shell">
<!-- ═══════════ SIDEBAR ═══════════ -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <a class="brand-name" href="/escopo">EstoqueEscolar</a>
    <div class="brand-role" id="sidebar-role-label">Escola — E.M. João da Silva</div>
  </div>

  <div id="nav-escola">
    <div class="sidebar-section">Principal</div>
    <div class="nav-item active" onclick="goto('dash-escola')"><span class="nav-icon">◼</span> Dashboard</div>
    <div class="nav-item" onclick="goto('inv-escola')"><span class="nav-icon">📦</span> Meu Inventário</div>
    <div class="sidebar-section">Pedidos</div>
    <div class="nav-item" onclick="goto('meus-pedidos')"><span class="nav-icon">📋</span> Meus Pedidos</div>
    <div class="nav-item" onclick="goto('novo-pedido')"><span class="nav-icon">＋</span> Novo Pedido</div>
  </div>

  <div id="nav-gestor" style="display:none">
    <div class="sidebar-section">Principal</div>
    <div class="nav-item" onclick="goto('dash-gestor')"><span class="nav-icon">◼</span> Dashboard</div>
    <div class="nav-item" onclick="goto('deposito')"><span class="nav-icon">🏭</span> Depósito</div>
    <div class="sidebar-section">Operação</div>
    <div class="nav-item" onclick="goto('pedidos-gestor')"><span class="nav-icon">📋</span> Pedidos <span id="notif-pedidos" class="notif-dot" style="display:none"></span></div>
    <div class="nav-item" onclick="goto('romaneios')"><span class="nav-icon">📄</span> Romaneios</div>
    <div class="nav-item" onclick="goto('entrada-fornecedor')"><span class="nav-icon">⬇</span> Entrada Fornecedor</div>
  </div>

  <div id="nav-admin" style="display:none">
    <div class="sidebar-section">Cadastros</div>
    <div class="nav-item" onclick="goto('cad-escola')"><span class="nav-icon">🏫</span> Escolas</div>
    <div class="nav-item" onclick="goto('cad-item')"><span class="nav-icon">🗂</span> Itens / Catálogo</div>
    <div class="nav-item" onclick="goto('cad-categoria')"><span class="nav-icon">🏷</span> Categorias</div>
    <div class="sidebar-section">Relatórios</div>
    <div class="nav-item" onclick="goto('movimentacoes')"><span class="nav-icon">📊</span> Movimentações</div>
  </div>

  <div class="role-switcher">
    <div class="role-label">Trocar perfil</div>
    <button class="role-btn active" id="btn-role-escola" onclick="setRole('escola')">🏫 Escola</button>
    <button class="role-btn" id="btn-role-gestor" onclick="setRole('gestor')">🗂️ Gestor</button>
    <button class="role-btn" id="btn-role-admin" onclick="setRole('admin')">⚙️ Admin</button>
  </div>
</aside>

<!-- ═══════════ MAIN ═══════════ -->
<main class="main">

<!-- ──────────────────────────────── -->
<!-- ESCOLA: DASHBOARD                -->
<!-- ──────────────────────────────── -->
<div class="screen active" id="screen-dash-escola">
  <div class="topbar">
    <div><div class="topbar-title">Dashboard</div><div class="topbar-sub" id="dash-escola-sub">Escola</div></div>
    <div class="topbar-actions"><button class="btn btn-primary" onclick="goto('novo-pedido')">＋ Novo Pedido</button></div>
  </div>
  <div class="content">
    <div class="stats-row" id="stats-escola"></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="card">
        <div class="card-header"><span class="card-title">Pedidos Recentes</span><button class="btn btn-ghost btn-sm" onclick="goto('meus-pedidos')">Ver todos</button></div>
        <div id="dash-pedidos-recentes" style="padding:0"></div>
      </div>
      <div class="card">
        <div class="card-header"><span class="card-title">Inventário — Destaques</span><button class="btn btn-ghost btn-sm" onclick="goto('inv-escola')">Ver completo</button></div>
        <div id="dash-inv-destaques" style="padding:0"></div>
      </div>
    </div>
  </div>
</div>

<!-- ──────────────────────────────── -->
<!-- ESCOLA: INVENTÁRIO               -->
<!-- ──────────────────────────────── -->
<div class="screen" id="screen-inv-escola">
  <div class="topbar">
    <div><div class="topbar-title">Meu Inventário</div><div class="topbar-sub">Itens disponíveis na escola</div></div>
    <div class="topbar-actions">
      <select class="form-control" id="filter-estoque-inv" style="font-size:10px;padding:5px 10px" onchange="renderInvEscola()">
        <option value="">Todos os Estoques</option>
      </select>
    </div>
  </div>
  <div class="content">
    <div class="card">
      <div class="card-header"><span class="card-title">Itens em Estoque</span><input class="form-control" id="busca-inv" style="width:200px;font-size:10px;padding:5px 10px" placeholder="Buscar item..." oninput="renderInvEscola()"></div>
      <div id="inv-escola-body"></div>
    </div>
  </div>
</div>

<!-- ──────────────────────────────── -->
<!-- ESCOLA: MEUS PEDIDOS             -->
<!-- ──────────────────────────────── -->
<div class="screen" id="screen-meus-pedidos">
  <div class="topbar">
    <div><div class="topbar-title">Meus Pedidos</div><div class="topbar-sub">Histórico de solicitações</div></div>
    <div class="topbar-actions"><button class="btn btn-primary" onclick="goto('novo-pedido')">＋ Novo Pedido</button></div>
  </div>
  <div class="content">
    <div class="card">
      <div class="card-header"><span class="card-title">Todos os Pedidos</span>
        <select class="form-control" id="filter-pedido-status" style="font-size:10px;padding:5px 8px" onchange="renderMeusPedidos()">
          <option value="">Todos os Status</option>
          <option>pendente</option><option>aprovado</option><option>enviado</option><option>recebido</option><option>cancelado</option>
        </select>
      </div>
      <div id="meus-pedidos-body"></div>
    </div>
  </div>
</div>

<!-- ──────────────────────────────── -->
<!-- ESCOLA: DETALHE PEDIDO           -->
<!-- ──────────────────────────────── -->
<div class="screen" id="screen-detalhe-pedido">
  <div class="topbar" id="detalhe-topbar"></div>
  <div class="content" id="detalhe-content"></div>
</div>

<!-- ──────────────────────────────── -->
<!-- ESCOLA: NOVO PEDIDO              -->
<!-- ──────────────────────────────── -->
<div class="screen" id="screen-novo-pedido">
  <div class="topbar">
    <div><div class="topbar-title">Novo Pedido</div><div class="topbar-sub">Selecione itens e quantidades</div></div>
    <div class="topbar-actions">
      <button class="btn btn-secondary" onclick="goto('meus-pedidos')">Cancelar</button>
      <button class="btn btn-primary" onclick="submeterPedido()">Enviar Pedido</button>
    </div>
  </div>
  <div class="content">
    <div class="alert alert-info">ℹ Os itens disponíveis são baseados no catálogo do depósito. A disponibilidade exibida é o saldo atual.</div>
    <div style="display:grid;grid-template-columns:1fr 300px;gap:16px">
      <div class="card">
        <div class="card-header"><span class="card-title">Itens do Pedido</span><button class="btn btn-secondary btn-sm" onclick="openModal('modal-add-item')">＋ Adicionar Item</button></div>
        <div id="novopedido-header" style="display:grid;grid-template-columns:1fr 100px 90px 32px;gap:8px;padding:7px 12px;font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:var(--light);border-bottom:1px solid var(--border)">
          <span>Item</span><span style="text-align:center">Estoque Dest.</span><span style="text-align:center">Qtd</span><span></span>
        </div>
        <div id="novopedido-itens"></div>
      </div>
      <div>
        <div class="card" style="margin-bottom:12px">
          <div class="card-header"><span class="card-title">Resumo</span></div>
          <div class="card-body" id="novopedido-resumo" style="display:flex;flex-direction:column;gap:8px;font-size:11px"></div>
        </div>
        <div class="card">
          <div class="card-header"><span class="card-title">Observações</span></div>
          <div class="card-body"><textarea class="form-control" id="novopedido-obs" placeholder="Alguma observação para o gestor..."></textarea></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ──────────────────────────────── -->
<!-- GESTOR: DASHBOARD                -->
<!-- ──────────────────────────────── -->
<div class="screen" id="screen-dash-gestor">
  <div class="topbar">
    <div><div class="topbar-title">Dashboard — Depósito</div><div class="topbar-sub">Visão geral de operações</div></div>
  </div>
  <div class="content">
    <div class="stats-row" id="stats-gestor"></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="card">
        <div class="card-header"><span class="card-title">Pedidos Pendentes</span><button class="btn btn-ghost btn-sm" onclick="goto('pedidos-gestor')">Ver todos</button></div>
        <div id="dash-gestor-pendentes"></div>
      </div>
      <div class="card">
        <div class="card-header"><span class="card-title">Saldo do Depósito</span></div>
        <div id="dash-gestor-saldo"></div>
      </div>
    </div>
  </div>
</div>

<!-- ──────────────────────────────── -->
<!-- GESTOR: PEDIDOS                  -->
<!-- ──────────────────────────────── -->
<div class="screen" id="screen-pedidos-gestor">
  <div class="topbar">
    <div><div class="topbar-title">Pedidos das Escolas</div><div class="topbar-sub">Análise e aprovação</div></div>
    <div class="topbar-actions">
      <select class="form-control" id="filter-gestor-status" style="font-size:10px;padding:5px 8px" onchange="renderPedidosGestor()">
        <option value="">Todos os Status</option>
        <option>pendente</option><option>aprovado</option><option>enviado</option><option>recebido</option><option>cancelado</option>
      </select>
    </div>
  </div>
  <div class="content" id="pedidos-gestor-body"></div>
</div>

<!-- ──────────────────────────────── -->
<!-- GESTOR: DEPÓSITO                 -->
<!-- ──────────────────────────────── -->
<div class="screen" id="screen-deposito">
  <div class="topbar">
    <div><div class="topbar-title">Depósito Central</div><div class="topbar-sub">Saldo e controle de reservas</div></div>
    <div class="topbar-actions"><button class="btn btn-primary" onclick="goto('entrada-fornecedor')">⬇ Entrada de Fornecedor</button></div>
  </div>
  <div class="content">
    <div class="stats-row" id="stats-deposito" style="grid-template-columns:repeat(3,1fr)"></div>
    <div class="card"><div id="deposito-body"></div></div>
  </div>
</div>

<!-- ──────────────────────────────── -->
<!-- GESTOR: ROMANEIOS                -->
<!-- ──────────────────────────────── -->
<div class="screen" id="screen-romaneios">
  <div class="topbar">
    <div><div class="topbar-title">Romaneios</div><div class="topbar-sub">Remessas geradas</div></div>
    <div class="topbar-actions"><button class="btn btn-primary" onclick="openModal('modal-novo-romaneio')">＋ Gerar Romaneio</button></div>
  </div>
  <div class="content" id="romaneios-body"></div>
</div>

<!-- ──────────────────────────────── -->
<!-- GESTOR: ENTRADA FORNECEDOR       -->
<!-- ──────────────────────────────── -->
<div class="screen" id="screen-entrada-fornecedor">
  <div class="topbar">
    <div><div class="topbar-title">Entrada de Fornecedor</div><div class="topbar-sub">Registro de recebimento no depósito</div></div>
  </div>
  <div class="content">
    <div class="alert alert-success">✓ Ao confirmar, os itens serão creditados no depósito e uma Movimentação ENTRADA será registrada.</div>
    <div style="display:grid;grid-template-columns:1fr 280px;gap:16px">
      <div class="card">
        <div class="card-header"><span class="card-title">Dados da Nota / Entrega</span></div>
        <div class="card-body">
          <div class="form-grid fg2" style="margin-bottom:16px">
            <div class="form-group"><label class="form-label">Fornecedor</label><input class="form-control" id="ef-fornecedor" value="Distribuidora ABC Ltda"></div>
            <div class="form-group"><label class="form-label">Nº Nota Fiscal</label><input class="form-control" id="ef-nf" value="NF-2026/4512"></div>
            <div class="form-group"><label class="form-label">Data</label><input type="date" class="form-control" id="ef-data" value="2026-03-09"></div>
            <div class="form-group"><label class="form-label">Responsável</label><input class="form-control" id="ef-resp" value="Carlos Silva"></div>
          </div>
          <div class="sdiv" style="margin-top:0">Itens Recebidos</div>
          <div style="display:grid;grid-template-columns:1fr 70px 90px 110px 32px;gap:8px;font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:var(--light);margin-bottom:8px">
            <span>Item</span><span>Qtd</span><span>Lote</span><span>Validade</span><span></span>
          </div>
          <div id="ef-itens"></div>
          <button class="btn btn-secondary btn-sm" style="margin-top:8px" onclick="addEfItem()">＋ Adicionar item</button>
        </div>
      </div>
      <div>
        <div class="card">
          <div class="card-header"><span class="card-title">Resumo da Entrada</span></div>
          <div class="card-body" id="ef-resumo" style="font-size:11px;display:flex;flex-direction:column;gap:10px"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ──────────────────────────────── -->
<!-- ADMIN: ESCOLAS                   -->
<!-- ──────────────────────────────── -->
<div class="screen" id="screen-cad-escola">
  <div class="topbar">
    <div><div class="topbar-title">Escolas</div><div class="topbar-sub">Gerenciar unidades da rede</div></div>
    <div class="topbar-actions"><button class="btn btn-primary" onclick="openModalEscola()">＋ Nova Escola</button></div>
  </div>
  <div class="content">
    <div class="card">
      <div class="card-header"><span class="card-title">Unidades Cadastradas</span><input class="form-control" id="busca-escola" style="width:200px;font-size:10px;padding:5px 10px" placeholder="Buscar..." oninput="renderEscolas()"></div>
      <div id="escolas-body"></div>
    </div>
  </div>
</div>

<!-- ──────────────────────────────── -->
<!-- ADMIN: CATÁLOGO DE ITENS         -->
<!-- ──────────────────────────────── -->
<div class="screen" id="screen-cad-item">
  <div class="topbar">
    <div><div class="topbar-title">Catálogo de Itens</div><div class="topbar-sub">Compartilhado por toda a rede</div></div>
    <div class="topbar-actions"><button class="btn btn-primary" onclick="openModalItem()">＋ Novo Item</button></div>
  </div>
  <div class="content">
    <div class="card">
      <div class="card-header"><span class="card-title">Itens Cadastrados</span>
        <div style="display:flex;gap:8px">
          <select class="form-control" id="filter-item-cat" style="font-size:10px;padding:5px 8px" onchange="renderItens()"><option value="">Todas as Categorias</option></select>
          <input class="form-control" id="busca-item" style="width:180px;font-size:10px;padding:5px 10px" placeholder="Buscar..." oninput="renderItens()">
        </div>
      </div>
      <div id="itens-body"></div>
    </div>
  </div>
</div>

<!-- ──────────────────────────────── -->
<!-- ADMIN: CATEGORIAS                -->
<!-- ──────────────────────────────── -->
<div class="screen" id="screen-cad-categoria">
  <div class="topbar">
    <div><div class="topbar-title">Categorias</div><div class="topbar-sub">Agrupamento de itens</div></div>
    <div class="topbar-actions"><button class="btn btn-primary" onclick="openModalCategoria()">＋ Nova Categoria</button></div>
  </div>
  <div class="content">
    <div class="card"><div id="categorias-body"></div></div>
  </div>
</div>

<!-- ──────────────────────────────── -->
<!-- ADMIN: MOVIMENTAÇÕES             -->
<!-- ──────────────────────────────── -->
<div class="screen" id="screen-movimentacoes">
  <div class="topbar">
    <div><div class="topbar-title">Movimentações</div><div class="topbar-sub">Auditoria completa</div></div>
  </div>
  <div class="content">
    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
      <select class="form-control" id="filter-mov-tipo" style="font-size:10px;padding:5px 8px" onchange="renderMovimentacoes()">
        <option value="">Todos os Tipos</option>
        <option>ENTRADA</option><option>TRANSFERÊNCIA</option><option>BAIXA</option><option>DEVOLUÇÃO</option>
      </select>
    </div>
    <div class="card"><div id="movimentacoes-body"></div></div>
  </div>
</div>

</main>
</div>

<!-- ═══════════════════ MODALS ═══════════════════ -->

<!-- Add Item ao Pedido -->
<div class="modal-overlay" id="modal-add-item">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Adicionar Item ao Pedido</span><button class="modal-close" onclick="closeModal('modal-add-item')">✕</button></div>
    <div class="modal-body">
      <div class="form-grid fg2">
        <div class="form-group"><label class="form-label">Categoria</label>
          <select class="form-control" id="add-item-cat" onchange="populateAddItemSelect()"><option value="">— selecione —</option></select>
        </div>
        <div class="form-group"><label class="form-label">Item</label>
          <select class="form-control" id="add-item-sel"><option value="">— selecione —</option></select>
        </div>
        <div class="form-group"><label class="form-label">Estoque Destino</label>
          <select class="form-control" id="add-item-estoque"><option value="">— selecione —</option></select>
        </div>
        <div class="form-group"><label class="form-label">Quantidade</label>
          <input type="number" class="form-control" id="add-item-qtd" value="10" min="1">
        </div>
      </div>
      <div id="add-item-disp" style="margin-top:10px;font-size:10px;color:var(--dim)"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modal-add-item')">Cancelar</button>
      <button class="btn btn-primary" onclick="addItemAoPedido()">Adicionar</button>
    </div>
  </div>
</div>

<!-- Novo Romaneio -->
<div class="modal-overlay" id="modal-novo-romaneio">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Gerar Novo Romaneio</span><button class="modal-close" onclick="closeModal('modal-novo-romaneio')">✕</button></div>
    <div class="modal-body">
      <div class="form-group" style="margin-bottom:14px">
        <label class="form-label">Data da Remessa</label>
        <input type="date" class="form-control" id="rom-data">
      </div>
      <div class="sdiv" style="margin-top:0">Pedidos aprovados disponíveis</div>
      <div id="rom-pedidos-lista"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modal-novo-romaneio')">Cancelar</button>
      <button class="btn btn-primary" onclick="gerarRomaneio()">Gerar Romaneio</button>
    </div>
  </div>
</div>

<!-- Ver Romaneio (preview) -->
<div class="modal-overlay" id="modal-ver-romaneio">
  <div class="modal wide">
    <div class="modal-header"><span class="modal-title" id="ver-rom-titulo">Romaneio</span><button class="modal-close" onclick="closeModal('modal-ver-romaneio')">✕</button></div>
    <div class="modal-body" id="ver-rom-body"></div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('modal-ver-romaneio')">Fechar</button></div>
  </div>
</div>

<!-- Editar/Novo Escola -->
<div class="modal-overlay" id="modal-escola">
  <div class="modal">
    <div class="modal-header"><span class="modal-title" id="modal-escola-titulo">Nova Escola</span><button class="modal-close" onclick="closeModal('modal-escola')">✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="escola-edit-id">
      <div class="form-grid" style="gap:12px">
        <div class="form-group"><label class="form-label">Nome</label><input class="form-control" id="escola-nome" placeholder="E.M. Nome da Escola"></div>
        <div class="form-group"><label class="form-label">Responsável</label><input class="form-control" id="escola-resp" placeholder="Nome do responsável"></div>
        <div class="form-group"><label class="form-label">Endereço</label><input class="form-control" id="escola-end" placeholder="Rua, número, bairro"></div>
        <div class="form-group"><label class="form-label">Telefone</label><input class="form-control" id="escola-tel" placeholder="(44) 9xxxx-xxxx"></div>
        <div class="form-group"><label class="form-label">Estoques (separados por vírgula)</label><input class="form-control" id="escola-estoques" value="Cozinha, Almoxarifado"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modal-escola')">Cancelar</button>
      <button class="btn btn-primary" onclick="salvarEscola()">Salvar</button>
    </div>
  </div>
</div>

<!-- Editar/Novo Item -->
<div class="modal-overlay" id="modal-item">
  <div class="modal">
    <div class="modal-header"><span class="modal-title" id="modal-item-titulo">Novo Item</span><button class="modal-close" onclick="closeModal('modal-item')">✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="item-edit-id">
      <div class="form-grid fg2" style="gap:12px">
        <div class="form-group" style="grid-column:1/-1"><label class="form-label">Nome</label><input class="form-control" id="item-nome" placeholder="Ex: Feijão Preto 1kg"></div>
        <div class="form-group"><label class="form-label">Categoria</label><select class="form-control" id="item-cat"></select></div>
        <div class="form-group"><label class="form-label">Unidade de Medida</label>
          <select class="form-control" id="item-unidade">
            <option>un</option><option>kg</option><option>l</option><option>pc</option><option>cx</option>
          </select>
        </div>
        <div class="form-group" style="grid-column:1/-1"><label class="form-label">Descrição</label><textarea class="form-control" id="item-desc" placeholder="Opcional..."></textarea></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modal-item')">Cancelar</button>
      <button class="btn btn-primary" onclick="salvarItem()">Salvar</button>
    </div>
  </div>
</div>

<!-- Editar/Nova Categoria -->
<div class="modal-overlay" id="modal-categoria">
  <div class="modal">
    <div class="modal-header"><span class="modal-title" id="modal-cat-titulo">Nova Categoria</span><button class="modal-close" onclick="closeModal('modal-categoria')">✕</button></div>
    <div class="modal-body">
      <input type="hidden" id="cat-edit-id">
      <div class="form-grid" style="gap:12px">
        <div class="form-group"><label class="form-label">Nome</label><input class="form-control" id="cat-nome" placeholder="Ex: Higiene Pessoal"></div>
        <div class="form-group"><label class="form-label">Descrição</label><textarea class="form-control" id="cat-desc" placeholder="Descrição da categoria..."></textarea></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modal-categoria')">Cancelar</button>
      <button class="btn btn-primary" onclick="salvarCategoria()">Salvar</button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<!-- ═══════════════════ JS ═══════════════════ -->
<script>
// ─── ESTADO GLOBAL ───────────────────────────────────────
let _id = 100;
const uid = () => ++_id;

const S = {
  role: 'escola',
  escolaAtual: 1,

  categorias: [
    {id:1, nome:'Alimentos', desc:'Gêneros alimentícios'},
    {id:2, nome:'Limpeza', desc:'Produtos de higiene e limpeza'},
    {id:3, nome:'Material Escolar', desc:'Papelaria e materiais pedagógicos'},
  ],

  itens: [
    {id:1, catId:1, nome:'Feijão Carioca 1kg', unidade:'un', desc:''},
    {id:2, catId:1, nome:'Arroz Branco 5kg', unidade:'un', desc:''},
    {id:3, catId:1, nome:'Óleo de Soja 900ml', unidade:'un', desc:''},
    {id:4, catId:1, nome:'Açúcar Cristal 1kg', unidade:'un', desc:''},
    {id:5, catId:1, nome:'Sal Refinado 1kg', unidade:'un', desc:''},
    {id:6, catId:2, nome:'Detergente 500ml', unidade:'un', desc:''},
    {id:7, catId:2, nome:'Sabão em Pó 1kg', unidade:'un', desc:''},
  ],

  escolas: [
    {id:1, nome:'E.M. João da Silva', resp:'Ana Paula Costa', end:'Rua das Flores, 120', tel:'(44) 99001-1234', estoques:['Cozinha','Almoxarifado']},
    {id:2, nome:'E.M. Maria Aparecida', resp:'Pedro Henrique', end:'Av. Brasil, 500', tel:'(44) 99002-5678', estoques:['Cozinha']},
    {id:3, nome:'E.M. Santos Dumont', resp:'Juliana Ferreira', end:'Rua 7 de Setembro, 80', tel:'(44) 99003-9012', estoques:['Cozinha','Despensa']},
    {id:4, nome:'E.M. Tiradentes', resp:'Roberto Alves', end:'Rua Tiradentes, 45', tel:'(44) 99004-3456', estoques:['Cozinha']},
  ],

  // deposito[itemId] = {qtd, reservado, validade, lote}
  deposito: {
    1:{qtd:120, reservado:30, validade:'15/06/2026', lote:'L2024A'},
    2:{qtd:50,  reservado:42, validade:'20/08/2026', lote:'L2024B'},
    3:{qtd:100, reservado:40, validade:'10/12/2026', lote:'L2024C'},
    4:{qtd:80,  reservado:25, validade:'30/09/2026', lote:'L2024E'},
    5:{qtd:60,  reservado:0,  validade:'—', lote:'L2024F'},
    6:{qtd:40,  reservado:0,  validade:'—', lote:'L2024D'},
    7:{qtd:30,  reservado:0,  validade:'—', lote:'L2024G'},
  },

  // inventario[escolaId][itemId] = {qtd, estoque, validade, lote}
  inventario: {
    1:{
      1:{qtd:42, estoque:'Cozinha', validade:'15/06/2026', lote:'L2024A'},
      2:{qtd:8,  estoque:'Cozinha', validade:'20/08/2026', lote:'L2024B'},
      3:{qtd:3,  estoque:'Cozinha', validade:'10/12/2026', lote:'L2024C'},
      4:{qtd:18, estoque:'Cozinha', validade:'30/09/2026', lote:'L2024E'},
      5:{qtd:15, estoque:'Cozinha', validade:'—', lote:'L2024F'},
      6:{qtd:24, estoque:'Almoxarifado', validade:'—', lote:'L2024D'},
    },
    2:{1:{qtd:20,estoque:'Cozinha',validade:'15/06/2026',lote:'L2024A'},3:{qtd:10,estoque:'Cozinha',validade:'10/12/2026',lote:'L2024C'}},
    3:{2:{qtd:12,estoque:'Cozinha',validade:'20/08/2026',lote:'L2024B'}},
    4:{4:{qtd:5,estoque:'Cozinha',validade:'30/09/2026',lote:'L2024E'}},
  },

  pedidos: [
    {id:35, escolaId:1, status:'recebido', obs:'', romaneioId:39, createdAt:'01/03/2026',
     itens:[{itemId:1,qtdSol:20,qtdAprov:20},{itemId:2,qtdSol:10,qtdAprov:10}]},
    {id:36, escolaId:2, status:'recebido', obs:'', romaneioId:40, createdAt:'01/03/2026',
     itens:[{itemId:3,qtdSol:15,qtdAprov:15}]},
    {id:38, escolaId:1, status:'enviado', obs:'Urgente arroz', romaneioId:42, createdAt:'08/03/2026',
     itens:[{itemId:1,qtdSol:30,qtdAprov:30},{itemId:2,qtdSol:20,qtdAprov:15},{itemId:3,qtdSol:20,qtdAprov:20},{itemId:4,qtdSol:25,qtdAprov:25},{itemId:5,qtdSol:10,qtdAprov:10}]},
    {id:39, escolaId:2, status:'pendente', obs:'', romaneioId:null, createdAt:'09/03/2026',
     itens:[{itemId:1,qtdSol:20,qtdAprov:null},{itemId:3,qtdSol:15,qtdAprov:null},{itemId:4,qtdSol:10,qtdAprov:null}]},
    {id:40, escolaId:3, status:'pendente', obs:'Precisa de limpeza também', romaneioId:null, createdAt:'09/03/2026',
     itens:[{itemId:2,qtdSol:8,qtdAprov:null},{itemId:6,qtdSol:10,qtdAprov:null}]},
  ],

  romaneios: [
    {id:42, gestorId:'Carlos Silva', data:'10/03/2026', status:'enviado', pedidoIds:[38]},
  ],

  movimentacoes: [
    {id:87, data:'08/03/2026', tipo:'ENTRADA', itemId:1, qtd:120, origem:'Fornecedor ABC', destino:'Depósito', resp:'Carlos Silva'},
    {id:86, data:'05/03/2026', tipo:'TRANSFERÊNCIA', itemId:2, qtd:25, origem:'Depósito', destino:'E.M. Maria Aparecida', resp:'Carlos Silva'},
    {id:85, data:'02/03/2026', tipo:'BAIXA', itemId:6, qtd:6, origem:'E.M. João da Silva', destino:'—', resp:'Ana Paula'},
  ],

  // pedido em construção para novo pedido
  pedidoRascunho: [],
};

// ─── UTILITÁRIOS ──────────────────────────────────────────
const nomItem = id => { const i=S.itens.find(x=>x.id==id); return i?i.nome:'-'; };
const nomEscola = id => { const e=S.escolas.find(x=>x.id==id); return e?e.nome:'-'; };
const catNome = id => { const c=S.categorias.find(x=>x.id==id); return c?c.nome:'-'; };
const dispDep = itemId => {
  const d=S.deposito[itemId];
  if(!d) return 0;
  return Math.max(0, d.qtd - d.reservado);
};

const statusBadge = s => {
  const map={pendente:'badge-pending',aprovado:'badge-approved',enviado:'badge-sent',recebido:'badge-received',cancelado:'badge-canceled',gerado:'badge-gerado',concluido:'badge-concluido'};
  return `<span class="badge ${map[s]||'badge-info'}">${s}</span>`;
};

function fmt(n){ return n.toLocaleString('pt-BR'); }

// ─── NAVEGAÇÃO ────────────────────────────────────────────
function goto(id){
  document.querySelectorAll('.screen').forEach(s=>s.classList.remove('active'));
  const t=document.getElementById('screen-'+id);
  if(t) t.classList.add('active');
  document.querySelectorAll('.nav-item').forEach(n=>{
    n.classList.remove('active');
    const oc=n.getAttribute('onclick');
    if(oc && oc.includes("'"+id+"'")) n.classList.add('active');
  });
  // render on navigate
  const renders={
    'dash-escola': renderDashEscola,
    'inv-escola': renderInvEscola,
    'meus-pedidos': renderMeusPedidos,
    'novo-pedido': renderNovoPedido,
    'dash-gestor': renderDashGestor,
    'pedidos-gestor': renderPedidosGestor,
    'deposito': renderDeposito,
    'romaneios': renderRomaneios,
    'entrada-fornecedor': renderEF,
    'cad-escola': renderEscolas,
    'cad-item': renderItens,
    'cad-categoria': renderCategorias,
    'movimentacoes': renderMovimentacoes,
  };
  if(renders[id]) renders[id]();
}

function setRole(r){
  S.role=r;
  ['escola','gestor','admin'].forEach(x=>{
    document.getElementById('nav-'+x).style.display=x===r?'block':'none';
    document.getElementById('btn-role-'+x).classList.toggle('active',x===r);
  });
  const lbl={escola:`Escola — ${nomEscola(S.escolaAtual)}`,gestor:'Gestor — Depósito Central',admin:'Administrador'};
  document.getElementById('sidebar-role-label').textContent=lbl[r];
  const def={escola:'dash-escola',gestor:'dash-gestor',admin:'cad-escola'};
  goto(def[r]);
}

// ─── TOAST / MODAL ────────────────────────────────────────
let toastTimer;
function toast(msg, type=''){
  const t=document.getElementById('toast');
  t.textContent=msg;
  t.style.background=type==='error'?'var(--red)':type==='ok'?'var(--green)':'var(--text)';
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer=setTimeout(()=>t.classList.remove('show'),3200);
}

function openModal(id){ document.getElementById(id).classList.add('open'); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o=>o.addEventListener('click',e=>{if(e.target===o)o.classList.remove('open')}));

// ─── RENDER: DASHBOARD ESCOLA ────────────────────────────
function renderDashEscola(){
  const escola=S.escolas.find(e=>e.id===S.escolaAtual);
  document.getElementById('dash-escola-sub').textContent=escola?escola.nome:'';

  const pedidosEscola=S.pedidos.filter(p=>p.escolaId===S.escolaAtual);
  const ativos=pedidosEscola.filter(p=>['pendente','aprovado','enviado'].includes(p.status)).length;
  const aguardando=pedidosEscola.filter(p=>p.status==='enviado').length;
  const inv=S.inventario[S.escolaAtual]||{};
  const numItens=Object.keys(inv).length;
  const baixos=Object.values(inv).filter(v=>v.qtd<10).length;

  document.getElementById('stats-escola').innerHTML=`
    <div class="stat-card"><div class="stat-value">${ativos}</div><div class="stat-label">Pedidos Ativos</div></div>
    <div class="stat-card"><div class="stat-value" style="color:var(--green)">${numItens}</div><div class="stat-label">Itens em Estoque</div></div>
    <div class="stat-card"><div class="stat-value" style="color:var(--yellow)">${aguardando}</div><div class="stat-label">Aguardando Recebimento</div></div>
    <div class="stat-card"><div class="stat-value" style="color:var(--accent)">${baixos}</div><div class="stat-label">Itens com Estoque Baixo</div></div>
  `;

  // pedidos recentes
  const recentes=pedidosEscola.slice(-5).reverse();
  document.getElementById('dash-pedidos-recentes').innerHTML=recentes.length?`
    <table>
      <thead><tr><th>#</th><th>Data</th><th>Itens</th><th>Status</th><th></th></tr></thead>
      <tbody>${recentes.map(p=>`<tr>
        <td>#${p.id}</td><td>${p.createdAt}</td><td>${p.itens.length} itens</td>
        <td>${statusBadge(p.status)}</td>
        <td><button class="btn btn-ghost btn-sm" onclick="verPedido(${p.id})">Ver</button></td>
      </tr>`).join('')}</tbody>
    </table>`:
    `<div class="empty"><div class="empty-icon">📋</div><div class="empty-title">Nenhum pedido</div></div>`;

  // inventário destaques
  const invEntries=Object.entries(inv).slice(0,4);
  const maxQtd=Math.max(...invEntries.map(([,v])=>v.qtd),1);
  document.getElementById('dash-inv-destaques').innerHTML=invEntries.length?
    invEntries.map(([itemId,v])=>{
      const pct=Math.round((v.qtd/Math.max(maxQtd,1))*100);
      const cor=v.qtd<5?'var(--accent)':v.qtd<15?'var(--yellow)':'var(--green)';
      return `<div style="padding:10px 12px;border-bottom:1px solid var(--border)">
        <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:4px">
          <span>${nomItem(itemId)}</span><span style="color:${cor}">${v.qtd} ${S.itens.find(i=>i.id==itemId)?.unidade||'un'}</span>
        </div>
        <div class="progress-bar"><div class="progress-fill" style="width:${pct}%;background:${cor}"></div></div>
      </div>`;
    }).join(''):`<div class="empty"><div>Sem itens</div></div>`;
}

// ─── RENDER: INVENTÁRIO ESCOLA ───────────────────────────
function renderInvEscola(){
  const inv=S.inventario[S.escolaAtual]||{};
  const busca=(document.getElementById('busca-inv')?.value||'').toLowerCase();
  const filtroEst=(document.getElementById('filter-estoque-inv')?.value||'');

  // preencher filtro de estoque
  const escola=S.escolas.find(e=>e.id===S.escolaAtual);
  const sel=document.getElementById('filter-estoque-inv');
  if(sel){
    const vals=Array.from(sel.options).map(o=>o.value);
    (escola?.estoques||[]).forEach(es=>{if(!vals.includes(es)){const o=document.createElement('option');o.value=es;o.textContent=es;sel.appendChild(o);}});
  }

  const rows=Object.entries(inv).filter(([itemId,v])=>{
    const nome=nomItem(itemId).toLowerCase();
    return (!busca||nome.includes(busca))&&(!filtroEst||v.estoque===filtroEst);
  });

  document.getElementById('inv-escola-body').innerHTML=`
    <div style="display:grid;grid-template-columns:1fr 70px 80px 70px 90px;gap:12px;padding:7px 12px;font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:var(--light);border-bottom:1px solid var(--border)">
      <span>Item</span><span style="text-align:center">Qtd</span><span style="text-align:center">Validade</span><span style="text-align:center">Lote</span><span style="text-align:center">Estoque</span>
    </div>
    ${rows.length?rows.map(([itemId,v])=>{
      const cor=v.qtd<5?'var(--accent)':v.qtd<10?'var(--yellow)':'var(--green)';
      const item=S.itens.find(i=>i.id==itemId);
      return `<div class="inv-row" style="grid-template-columns:1fr 70px 80px 70px 90px">
        <div><div class="inv-name">${nomItem(itemId)}</div><div class="inv-cat">${catNome(item?.catId)}</div></div>
        <div style="text-align:center;color:${cor};font-weight:500">${v.qtd} ${item?.unidade||'un'}</div>
        <div style="text-align:center;font-size:10px">${v.validade}</div>
        <div style="text-align:center;font-size:10px">${v.lote}</div>
        <div style="text-align:center"><span class="badge badge-info">${v.estoque}</span></div>
      </div>`;
    }).join(''):`<div class="empty"><div class="empty-icon">📦</div><div class="empty-title">Nenhum item</div></div>`}
  `;
}

// ─── RENDER: MEUS PEDIDOS ────────────────────────────────
function renderMeusPedidos(){
  const filtro=document.getElementById('filter-pedido-status')?.value||'';
  const pedidos=S.pedidos.filter(p=>p.escolaId===S.escolaAtual&&(!filtro||p.status===filtro))
    .sort((a,b)=>b.id-a.id);

  document.getElementById('meus-pedidos-body').innerHTML=pedidos.length?`
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>Data</th><th>Itens</th><th>Status</th><th>Romaneio</th><th>Ações</th></tr></thead>
      <tbody>${pedidos.map(p=>`<tr>
        <td>#${p.id}</td><td>${p.createdAt}</td><td>${p.itens.length} itens</td>
        <td>${statusBadge(p.status)}</td>
        <td>${p.romaneioId?`#${p.romaneioId}`:'—'}</td>
        <td style="display:flex;gap:4px;flex-wrap:wrap">
          ${p.status==='enviado'?`<button class="btn btn-success btn-sm" onclick="confirmarRecebimento(${p.id})">✓ Receber</button>`:''}
          <button class="btn btn-ghost btn-sm" onclick="verPedido(${p.id})">Ver</button>
          ${p.status==='pendente'?`<button class="btn btn-danger btn-sm" onclick="cancelarPedido(${p.id})">Cancelar</button>`:''}
        </td>
      </tr>`).join('')}</tbody>
    </table></div>`
    :`<div class="empty"><div class="empty-icon">📋</div><div class="empty-title">Nenhum pedido encontrado</div></div>`;
}

// ─── RENDER: DETALHE PEDIDO ──────────────────────────────
function verPedido(pedidoId){
  const p=S.pedidos.find(x=>x.id===pedidoId);
  if(!p) return;

  // timeline
  const tl=[
    {label:'Pedido criado', desc:p.createdAt+' às 09:14', done:true},
    {label:'Aprovado pelo gestor', desc:'', done:['aprovado','enviado','recebido'].includes(p.status)},
    {label:'Romaneio gerado', desc:p.romaneioId?`Romaneio #${p.romaneioId}`:'', done:['enviado','recebido'].includes(p.status)},
    {label:'Enviado / Em trânsito', desc:'', done:['enviado','recebido'].includes(p.status), current:p.status==='enviado'},
    {label:'Recebido pela escola', desc:'', done:p.status==='recebido'},
  ];

  document.getElementById('detalhe-topbar').innerHTML=`
    <div>
      <div class="topbar-title">Pedido #${p.id} ${statusBadge(p.status)}</div>
      <div class="topbar-sub">Criado em ${p.createdAt} · ${nomEscola(p.escolaId)}</div>
    </div>
    <div class="topbar-actions">
      ${p.status==='enviado'?`<button class="btn btn-success" onclick="confirmarRecebimento(${p.id})">✓ Confirmar Recebimento</button>`:''}
      <button class="btn btn-secondary" onclick="history.back(); goto('meus-pedidos')">← Voltar</button>
    </div>
  `;

  const ajustados=p.itens.filter(i=>i.qtdAprov!==null&&i.qtdAprov<i.qtdSol);

  document.getElementById('detalhe-content').innerHTML=`
    <div style="display:grid;grid-template-columns:1fr 280px;gap:16px">
      <div>
        <div class="card" style="margin-bottom:16px">
          <div class="card-header"><span class="card-title">Itens Solicitados</span></div>
          <div style="display:grid;grid-template-columns:1fr 100px 100px;gap:8px;padding:7px 12px;font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:var(--light);border-bottom:1px solid var(--border)">
            <span>Item</span><span style="text-align:center">Solicitado</span><span style="text-align:center">Aprovado</span>
          </div>
          ${p.itens.map(i=>{
            const item=S.itens.find(x=>x.id===i.itemId);
            const reduzido=i.qtdAprov!==null&&i.qtdAprov<i.qtdSol;
            const corAprov=i.qtdAprov===null?'var(--light)':reduzido?'var(--yellow)':'var(--green)';
            return `<div style="display:grid;grid-template-columns:1fr 100px 100px;gap:8px;align-items:center;padding:9px 12px;border-bottom:1px solid var(--border)">
              <div><div style="font-size:11px;font-weight:500">${nomItem(i.itemId)}</div><div style="font-size:9px;color:var(--light)">${catNome(item?.catId)}</div></div>
              <div style="text-align:center;font-size:11px">${i.qtdSol} ${item?.unidade||'un'}</div>
              <div style="text-align:center;font-size:11px;font-weight:500;color:${corAprov}">${i.qtdAprov!==null?i.qtdAprov+' '+(item?.unidade||'un'):'—'}</div>
            </div>`;
          }).join('')}
        </div>
        ${p.obs?`<div class="alert alert-info">ℹ <span><strong>Observação:</strong> ${p.obs}</span></div>`:''}
        ${ajustados.length?`<div class="alert alert-warn">⚠ ${ajustados.length} item(s) tiveram quantidade reduzida por saldo insuficiente no depósito.</div>`:''}
      </div>
      <div>
        <div class="card" style="margin-bottom:16px">
          <div class="card-header"><span class="card-title">Linha do Tempo</span></div>
          <div class="card-body">
            ${tl.map(t=>`<div class="tl-row">
              <div class="tl-dot ${t.done?'done':t.current?'current':'pending'}">${t.done?'✓':t.current?'→':''}</div>
              <div class="tl-content"><div class="tl-title">${t.label}</div><div class="tl-desc">${t.desc}</div></div>
            </div>`).join('')}
          </div>
        </div>
        ${p.romaneioId?`<div class="card">
          <div class="card-header"><span class="card-title">Romaneio</span></div>
          <div class="card-body" style="font-size:11px;color:var(--dim);display:flex;flex-direction:column;gap:8px">
            <div>Remessa <strong>#${p.romaneioId}</strong></div>
            <button class="btn btn-secondary btn-sm" style="width:100%;justify-content:center" onclick="verRomaneio(${p.romaneioId})">📄 Ver Romaneio</button>
          </div>
        </div>`:''}
      </div>
    </div>
  `;
  goto('detalhe-pedido');
}

function cancelarPedido(id){
  const p=S.pedidos.find(x=>x.id===id);
  if(!p||p.status!=='pendente') return;
  p.status='cancelado';
  toast('Pedido #'+id+' cancelado.','error');
  renderMeusPedidos();
}

function confirmarRecebimento(id){
  const p=S.pedidos.find(x=>x.id===id);
  if(!p||p.status!=='enviado') return;
  p.status='recebido';
  const inv=S.inventario[p.escolaId]||(S.inventario[p.escolaId]={});
  const escola=S.escolas.find(e=>e.id===p.escolaId);
  p.itens.forEach(i=>{
    const qtd=i.qtdAprov||0;
    if(!inv[i.itemId]) inv[i.itemId]={qtd:0,estoque:escola?.estoques?.[0]||'Cozinha',validade:S.deposito[i.itemId]?.validade||'—',lote:S.deposito[i.itemId]?.lote||'—'};
    inv[i.itemId].qtd+=qtd;
    const dep=S.deposito[i.itemId];
    if(dep){ dep.qtd-=qtd; dep.reservado=Math.max(0,dep.reservado-qtd); }
    S.movimentacoes.unshift({id:uid(),data:new Date().toLocaleDateString('pt-BR'),tipo:'TRANSFERÊNCIA',itemId:i.itemId,qtd,origem:'Depósito',destino:nomEscola(p.escolaId),resp:'Sistema'});
  });
  toast('Recebimento do Pedido #'+id+' confirmado! Inventário atualizado.','ok');
  goto('meus-pedidos');
}

// ─── RENDER: NOVO PEDIDO ─────────────────────────────────
function renderNovoPedido(){
  if(!S.pedidoRascunho) S.pedidoRascunho=[];
  renderNovoPedidoItens();
}

function renderNovoPedidoItens(){
  const lista=S.pedidoRascunho;
  const escola=S.escolas.find(e=>e.id===S.escolaAtual);
  document.getElementById('novopedido-itens').innerHTML=lista.length?
    lista.map((r,idx)=>{
      const item=S.itens.find(i=>i.id===r.itemId);
      const disp=dispDep(r.itemId);
      return `<div class="item-line">
        <div><div style="font-size:11px;font-weight:500">${nomItem(r.itemId)}</div><div style="font-size:9px;color:var(--light)">Disp. no depósito: ${disp} ${item?.unidade||'un'}</div></div>
        <select class="form-control" style="font-size:10px;padding:3px 6px" onchange="S.pedidoRascunho[${idx}].estoque=this.value">
          ${(escola?.estoques||['Cozinha']).map(es=>`<option ${es===r.estoque?'selected':''}>${es}</option>`).join('')}
        </select>
        <input type="number" class="form-control" value="${r.qtd}" min="1" max="${disp}" style="text-align:center;padding:3px 6px;font-size:11px"
          onchange="S.pedidoRascunho[${idx}].qtd=+this.value;renderNovoPedidoResumo()">
        <button class="btn btn-ghost btn-sm" style="padding:3px;color:var(--accent)" onclick="S.pedidoRascunho.splice(${idx},1);renderNovoPedidoItens()">✕</button>
      </div>`;
    }).join(''):`<div class="empty" style="padding:24px"><div>Nenhum item adicionado. Clique em "＋ Adicionar Item".</div></div>`;
  renderNovoPedidoResumo();
}

function renderNovoPedidoResumo(){
  const lista=S.pedidoRascunho;
  const total=lista.reduce((s,r)=>s+r.qtd,0);
  document.getElementById('novopedido-resumo').innerHTML=`
    <div style="display:flex;justify-content:space-between;color:var(--dim)"><span>Itens distintos</span><strong>${lista.length}</strong></div>
    <div style="display:flex;justify-content:space-between;color:var(--dim)"><span>Total de unidades</span><strong>${total}</strong></div>
    ${lista.length===0?`<div style="color:var(--light);font-size:10px">Adicione itens para prosseguir.</div>`:''}
  `;
}

function addItemAoPedido(){
  const itemId=+document.getElementById('add-item-sel').value;
  const estoque=document.getElementById('add-item-estoque').value;
  const qtd=+document.getElementById('add-item-qtd').value;
  if(!itemId||!estoque||qtd<1){ toast('Preencha todos os campos.','error'); return; }
  if(S.pedidoRascunho.find(r=>r.itemId===itemId)){ toast('Item já adicionado.','error'); return; }
  const disp=dispDep(itemId);
  if(qtd>disp){ toast(`Quantidade excede o disponível (${disp}).`,'error'); return; }
  S.pedidoRascunho.push({itemId,estoque,qtd});
  closeModal('modal-add-item');
  renderNovoPedidoItens();
  toast('Item adicionado.');
}

function submeterPedido(){
  if(!S.pedidoRascunho.length){ toast('Adicione ao menos um item.','error'); return; }
  const novoPedido={
    id:uid(), escolaId:S.escolaAtual, status:'pendente',
    obs:document.getElementById('novopedido-obs')?.value||'',
    romaneioId:null,
    createdAt:new Date().toLocaleDateString('pt-BR'),
    itens:S.pedidoRascunho.map(r=>({itemId:r.itemId,qtdSol:r.qtd,qtdAprov:null}))
  };
  S.pedidos.push(novoPedido);
  S.pedidoRascunho=[];
  toast('Pedido #'+novoPedido.id+' enviado para aprovação!','ok');
  atualizarNotifPedidos();
  goto('meus-pedidos');
}

// modal add item: popular selects
function populateAddItemSelect(){
  const catId=+document.getElementById('add-item-cat').value;
  const sel=document.getElementById('add-item-sel');
  const itens=S.itens.filter(i=>!catId||i.catId===catId);
  sel.innerHTML='<option value="">— selecione —</option>'+itens.map(i=>`<option value="${i.id}">${i.nome} (disp: ${dispDep(i.id)} ${i.unidade})</option>`).join('');
  document.getElementById('add-item-disp').textContent='';
  sel.onchange=()=>{
    const id=+sel.value;
    document.getElementById('add-item-disp').textContent=id?`Disponível no depósito: ${dispDep(id)} unidades`:'';
  };
}

function openModalAddItem(){
  // preencher categorias
  const catSel=document.getElementById('add-item-cat');
  catSel.innerHTML='<option value="">Todas</option>'+S.categorias.map(c=>`<option value="${c.id}">${c.nome}</option>`).join('');
  populateAddItemSelect();
  // preencher estoques
  const escola=S.escolas.find(e=>e.id===S.escolaAtual);
  const estSel=document.getElementById('add-item-estoque');
  estSel.innerHTML=(escola?.estoques||['Cozinha']).map(es=>`<option>${es}</option>`).join('');
  openModal('modal-add-item');
}

// ─── RENDER: DASHBOARD GESTOR ────────────────────────────
function renderDashGestor(){
  const pendentes=S.pedidos.filter(p=>p.status==='pendente');
  const totalDep=Object.values(S.deposito).reduce((s,d)=>s+d.qtd,0);
  const totalRes=Object.values(S.deposito).reduce((s,d)=>s+d.reservado,0);

  document.getElementById('stats-gestor').innerHTML=`
    <div class="stat-card"><div class="stat-value" style="color:var(--yellow)">${pendentes.length}</div><div class="stat-label">Pedidos Pendentes</div></div>
    <div class="stat-card"><div class="stat-value">${totalDep}</div><div class="stat-label">Unidades no Depósito</div></div>
    <div class="stat-card"><div class="stat-value" style="color:var(--accent)">${totalRes}</div><div class="stat-label">Unidades Reservadas</div></div>
    <div class="stat-card"><div class="stat-value" style="color:var(--green)">${totalDep-totalRes}</div><div class="stat-label">Saldo Disponível</div></div>
  `;

  document.getElementById('dash-gestor-pendentes').innerHTML=pendentes.length?`
    <table>
      <thead><tr><th>#</th><th>Escola</th><th>Itens</th><th>Ação</th></tr></thead>
      <tbody>${pendentes.map(p=>`<tr>
        <td>#${p.id}</td><td>${nomEscola(p.escolaId)}</td><td>${p.itens.length} itens</td>
        <td><button class="btn btn-primary btn-sm" onclick="goto('pedidos-gestor')">Analisar</button></td>
      </tr>`).join('')}</tbody>
    </table>`:`<div class="empty" style="padding:24px"><div class="empty-icon">✓</div><div class="empty-title">Tudo em dia</div><div class="empty-sub">Sem pedidos pendentes</div></div>`;

  const topItens=Object.entries(S.deposito).slice(0,4);
  document.getElementById('dash-gestor-saldo').innerHTML=topItens.map(([id,d])=>{
    const disp=d.qtd-d.reservado;
    const pct=d.qtd>0?Math.round((disp/d.qtd)*100):0;
    const cor=pct<20?'var(--accent)':pct<50?'var(--yellow)':'var(--green)';
    return `<div style="padding:10px 12px;border-bottom:1px solid var(--border)">
      <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:4px">
        <span>${nomItem(id)}</span><span><strong style="color:${cor}">${disp}</strong> <span style="color:var(--light)">/ ${d.qtd}</span></span>
      </div>
      <div class="progress-bar"><div class="progress-fill" style="width:${pct}%;background:${cor}"></div></div>
    </div>`;
  }).join('');
}

// ─── RENDER: PEDIDOS GESTOR ──────────────────────────────
function renderPedidosGestor(){
  const filtro=document.getElementById('filter-gestor-status')?.value||'';
  const pedidos=S.pedidos.filter(p=>!filtro||p.status===filtro).sort((a,b)=>b.id-a.id);
  atualizarNotifPedidos();

  document.getElementById('pedidos-gestor-body').innerHTML=pedidos.length?
    pedidos.map(p=>{
      const escola=S.escolas.find(e=>e.id===p.escolaId);
      const editavel=p.status==='pendente';
      return `<div class="card" style="margin-bottom:12px">
        <div class="card-header">
          <div style="display:flex;align-items:center;gap:10px">
            <span class="card-title">Pedido #${p.id} — ${escola?.nome||'?'}</span>
            ${statusBadge(p.status)}
          </div>
          <div style="display:flex;gap:6px">
            ${editavel?`
              <button class="btn btn-danger btn-sm" onclick="recusarPedido(${p.id})">✕ Recusar</button>
              <button class="btn btn-success btn-sm" onclick="aprovarPedido(${p.id})">✓ Aprovar</button>
            `:''}
            ${p.status==='aprovado'?`<button class="btn btn-warn btn-sm" onclick="openModal('modal-novo-romaneio')">📄 Incluir em Romaneio</button>`:''}
          </div>
        </div>
        ${editavel?`<div class="card-body">
          <div style="display:grid;grid-template-columns:1fr 90px 90px 90px;gap:8px;font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:var(--light);border-bottom:1px solid var(--border);padding-bottom:7px;margin-bottom:4px">
            <span>Item</span><span style="text-align:center">Solicitado</span><span style="text-align:center">Disponível</span><span style="text-align:center">Aprovar</span>
          </div>
          ${p.itens.map((i,idx)=>{
            const item=S.itens.find(x=>x.id===i.itemId);
            const disp=dispDep(i.itemId);
            const alerta=i.qtdSol>disp;
            return `<div style="display:grid;grid-template-columns:1fr 90px 90px 90px;gap:8px;align-items:center;padding:7px 0;border-bottom:1px solid var(--border)">
              <div style="font-size:11px;font-weight:500">${nomItem(i.itemId)} ${alerta?`<span style="color:var(--accent);font-size:9px">⚠ saldo insuficiente</span>`:''}</div>
              <div style="text-align:center;font-size:11px">${i.qtdSol} ${item?.unidade||'un'}</div>
              <div style="text-align:center;font-size:11px;color:${disp<i.qtdSol?'var(--accent)':'var(--green)'}">${disp} ${item?.unidade||'un'}</div>
              <input type="number" id="aprov-${p.id}-${idx}" class="form-control" value="${Math.min(i.qtdSol,disp)}" min="0" max="${disp}" style="text-align:center;font-size:11px;padding:4px;${alerta?'border-color:var(--yellow)':''}">
            </div>`;
          }).join('')}
        </div>`:
        `<div class="card-body" style="font-size:11px;color:var(--dim)">
          ${p.itens.map(i=>`<span style="margin-right:12px">${nomItem(i.itemId)}: ${i.qtdAprov??'—'} ${S.itens.find(x=>x.id===i.itemId)?.unidade||'un'}</span>`).join('')}
          ${p.obs?`<div style="margin-top:8px;color:var(--light)">Obs: ${p.obs}</div>`:''}
        </div>`}
      </div>`;
    }).join(''):`<div class="empty"><div class="empty-icon">📋</div><div class="empty-title">Nenhum pedido encontrado</div></div>`;
}

function aprovarPedido(id){
  const p=S.pedidos.find(x=>x.id===id);
  if(!p) return;
  // ler qtds aprovadas dos inputs
  let ok=true;
  p.itens.forEach((i,idx)=>{
    const input=document.getElementById(`aprov-${id}-${idx}`);
    const v=input?+input.value:0;
    const disp=dispDep(i.itemId);
    if(v<0||v>disp){ toast(`Quantidade inválida para ${nomItem(i.itemId)}.`,'error'); ok=false; return; }
    i.qtdAprov=v;
    const dep=S.deposito[i.itemId];
    if(dep) dep.reservado+=v;
  });
  if(!ok) return;
  p.status='aprovado';
  atualizarNotifPedidos();
  toast('Pedido #'+id+' aprovado! Saldo reservado no depósito.','ok');
  renderPedidosGestor();
  renderDashGestor();
}

function recusarPedido(id){
  const p=S.pedidos.find(x=>x.id===id);
  if(!p) return;
  p.status='cancelado';
  toast('Pedido #'+id+' recusado.');
  atualizarNotifPedidos();
  renderPedidosGestor();
}

function atualizarNotifPedidos(){
  const n=S.pedidos.filter(p=>p.status==='pendente').length;
  const dot=document.getElementById('notif-pedidos');
  if(dot) dot.style.display=n>0?'inline-block':'none';
}

// ─── RENDER: DEPÓSITO ────────────────────────────────────
function renderDeposito(){
  const total=Object.values(S.deposito).reduce((s,d)=>s+d.qtd,0);
  const res=Object.values(S.deposito).reduce((s,d)=>s+d.reservado,0);
  document.getElementById('stats-deposito').innerHTML=`
    <div class="stat-card" style="border-top:3px solid var(--text)"><div class="stat-value">${total}</div><div class="stat-label">Total em Estoque</div></div>
    <div class="stat-card" style="border-top:3px solid var(--yellow)"><div class="stat-value" style="color:var(--yellow)">${res}</div><div class="stat-label">Reservado</div></div>
    <div class="stat-card" style="border-top:3px solid var(--green)"><div class="stat-value" style="color:var(--green)">${total-res}</div><div class="stat-label">Disponível</div></div>
  `;
  document.getElementById('deposito-body').innerHTML=`
    <div style="display:grid;grid-template-columns:1fr 70px 80px 80px 90px 70px;gap:8px;padding:7px 12px;font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:var(--light);border-bottom:1px solid var(--border)">
      <span>Item</span><span style="text-align:center">Total</span><span style="text-align:center">Reservado</span><span style="text-align:center">Disponível</span><span style="text-align:center">Validade</span><span style="text-align:center">Lote</span>
    </div>
    ${Object.entries(S.deposito).map(([itemId,d])=>{
      const disp=d.qtd-d.reservado;
      const cor=disp<10?'var(--accent)':disp<30?'var(--yellow)':'var(--green)';
      const item=S.itens.find(i=>i.id==itemId);
      return `<div class="inv-row" style="grid-template-columns:1fr 70px 80px 80px 90px 70px">
        <div><div class="inv-name">${nomItem(itemId)}</div><div class="inv-cat">${catNome(item?.catId)}</div></div>
        <div style="text-align:center;font-weight:500">${d.qtd}</div>
        <div style="text-align:center;color:var(--yellow)">${d.reservado}</div>
        <div style="text-align:center;font-weight:500;color:${cor}">${disp}</div>
        <div style="text-align:center;font-size:10px">${d.validade}</div>
        <div style="text-align:center;font-size:10px">${d.lote}</div>
      </div>`;
    }).join('')}
  `;
}

// ─── RENDER: ROMANEIOS ───────────────────────────────────
function renderRomaneios(){
  const body=document.getElementById('romaneios-body');
  if(!S.romaneios.length){
    body.innerHTML=`<div class="empty"><div class="empty-icon">📄</div><div class="empty-title">Nenhum romaneio gerado</div><div class="empty-sub">Clique em "＋ Gerar Romaneio" para criar o primeiro.</div></div>`;
    return;
  }
  body.innerHTML=S.romaneios.slice().reverse().map(r=>{
    const pedidos=S.pedidos.filter(p=>r.pedidoIds.includes(p.id));
    const escolas=[...new Set(pedidos.map(p=>p.escolaId))];
    return `<div class="card" style="margin-bottom:16px">
      <div class="card-header">
        <div style="display:flex;align-items:center;gap:10px">
          <span class="card-title">Remessa #${r.id}</span>
          ${statusBadge(r.status)}
        </div>
        <div style="display:flex;gap:6px">
          ${r.status==='gerado'?`<button class="btn btn-primary btn-sm" onclick="marcarEnviado(${r.id})">🚚 Marcar como Enviado</button>`:''}
          <button class="btn btn-secondary btn-sm" onclick="verRomaneio(${r.id})">📄 Ver Romaneio</button>
        </div>
      </div>
      <div class="card-body" style="font-size:11px;color:var(--dim)">
        Data: <strong>${r.data}</strong> · Gestor: <strong>${r.gestorId}</strong> · ${escolas.length} escola(s) · ${pedidos.length} pedido(s)
      </div>
    </div>`;
  }).join('');
}

function marcarEnviado(romId){
  const r=S.romaneios.find(x=>x.id===romId);
  if(!r) return;
  r.status='enviado';
  S.pedidos.filter(p=>r.pedidoIds.includes(p.id)).forEach(p=>p.status='enviado');
  toast('Remessa #'+romId+' marcada como enviada! Escolas notificadas.','ok');
  renderRomaneios();
}

function gerarRomaneio(){
  const data=document.getElementById('rom-data').value;
  if(!data){ toast('Informe a data da remessa.','error'); return; }
  const checks=document.querySelectorAll('#rom-pedidos-lista input[type=checkbox]:checked');
  const ids=[...checks].map(c=>+c.value);
  if(!ids.length){ toast('Selecione ao menos um pedido.','error'); return; }
  const novoRom={id:uid(), gestorId:'Carlos Silva', data:formatDate(data), status:'gerado', pedidoIds:ids};
  S.romaneios.push(novoRom);
  S.pedidos.filter(p=>ids.includes(p.id)).forEach(p=>p.romaneioId=novoRom.id);
  closeModal('modal-novo-romaneio');
  toast('Romaneio #'+novoRom.id+' gerado com sucesso!','ok');
  renderRomaneios();
}

function openModalNovoRomaneio(){
  const aprovados=S.pedidos.filter(p=>p.status==='aprovado');
  const lista=document.getElementById('rom-pedidos-lista');
  if(!aprovados.length){
    lista.innerHTML=`<div style="color:var(--light);font-size:11px;padding:8px 0">Nenhum pedido aprovado aguardando romaneio.</div>`;
  } else {
    lista.innerHTML=aprovados.map(p=>`
      <label style="display:flex;align-items:center;gap:10px;font-size:11px;cursor:pointer;padding:8px;border:1px solid var(--border);border-radius:5px;margin-bottom:6px">
        <input type="checkbox" value="${p.id}" checked>
        <span><strong>#${p.id}</strong> — ${nomEscola(p.escolaId)} (${p.itens.length} itens)</span>
      </label>`).join('');
  }
  const hoje=new Date().toISOString().split('T')[0];
  document.getElementById('rom-data').value=hoje;
  openModal('modal-novo-romaneio');
}

function verRomaneio(romId){
  const r=S.romaneios.find(x=>x.id===romId);
  if(!r){ toast('Romaneio não encontrado.','error'); return; }
  const pedidos=S.pedidos.filter(p=>r.pedidoIds.includes(p.id));
  const escolas=[...new Set(pedidos.map(p=>p.escolaId))];

  // consolidar itens
  const consolidado={};
  pedidos.forEach(p=>{
    p.itens.forEach(i=>{
      const qtd=i.qtdAprov??i.qtdSol;
      if(!consolidado[i.itemId]) consolidado[i.itemId]={total:0, porEscola:{}};
      consolidado[i.itemId].total+=qtd;
      if(!consolidado[i.itemId].porEscola[p.escolaId]) consolidado[i.itemId].porEscola[p.escolaId]=0;
      consolidado[i.itemId].porEscola[p.escolaId]+=qtd;
    });
  });

  document.getElementById('ver-rom-titulo').textContent=`Romaneio — Remessa #${r.id} · ${r.data}`;
  document.getElementById('ver-rom-body').innerHTML=`
    <!-- Romaneio Geral -->
    <div class="rom-preview" style="margin-bottom:20px">
      <div class="rom-preview-header">
        <div>
          <div style="font-family:'Fraunces',serif;font-size:14px;font-weight:600">Romaneio Geral — Remessa #${r.id}</div>
          <div style="font-size:9px;color:var(--light);margin-top:2px">Depósito Central · ${r.data} · Gestor: ${r.gestorId}</div>
        </div>
        <span class="badge badge-info" style="align-self:flex-start">Depósito</span>
      </div>
      <div class="rom-preview-body">
        <table>
          <thead><tr><th>Item</th><th>Qtd Total</th><th>Destinos</th></tr></thead>
          <tbody>
            ${Object.entries(consolidado).map(([itemId,c])=>{
              const item=S.itens.find(i=>i.id==itemId);
              const destinos=Object.entries(c.porEscola).map(([eid,q])=>`${nomEscola(+eid)} (${q})`).join(', ');
              return `<tr><td>${nomItem(itemId)}</td><td>${c.total} ${item?.unidade||'un'}</td><td style="color:var(--dim)">${destinos}</td></tr>`;
            }).join('')}
          </tbody>
        </table>
      </div>
    </div>

    <div style="font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:var(--light);margin-bottom:10px">Romaneios por Escola</div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      ${escolas.map(escolaId=>{
        const escola=S.escolas.find(e=>e.id===escolaId);
        const pedEscola=pedidos.filter(p=>p.escolaId===escolaId);
        const itensEscola={};
        pedEscola.forEach(p=>p.itens.forEach(i=>{
          const q=i.qtdAprov??i.qtdSol;
          if(!itensEscola[i.itemId]) itensEscola[i.itemId]=0;
          itensEscola[i.itemId]+=q;
        }));
        return `<div class="rom-preview">
          <div class="rom-preview-header">
            <div>
              <div style="font-family:'Fraunces',serif;font-size:13px;font-weight:600">${escola?.nome||'Escola'}</div>
              <div style="font-size:9px;color:var(--light);margin-top:2px">${Object.keys(itensEscola).length} itens · Remessa #${r.id} · ${r.data}</div>
            </div>
            <span class="badge badge-approved" style="align-self:flex-start">Escola</span>
          </div>
          <div class="rom-preview-body">
            <table>
              <thead><tr><th>Item</th><th>Qtd</th><th>☐ Rec.</th></tr></thead>
              <tbody>
                ${Object.entries(itensEscola).map(([itemId,q])=>{
                  const item=S.itens.find(i=>i.id==itemId);
                  return `<tr><td>${nomItem(itemId)}</td><td>${q} ${item?.unidade||'un'}</td><td>☐</td></tr>`;
                }).join('')}
              </tbody>
            </table>
            <div class="rom-sign">
              <span>Entregue por: _______________</span>
              <span>Recebido por: _______________</span>
              <span>Data: ___/___/___</span>
            </div>
          </div>
        </div>`;
      }).join('')}
    </div>
  `;
  openModal('modal-ver-romaneio');
}

// ─── ENTRADA FORNECEDOR ───────────────────────────────────
let efItens=[];
function renderEF(){
  efItens=[{itemId:'',qtd:50,lote:'L2025A',validade:'2027-06-01'}];
  renderEFItens();
}
function addEfItem(){ efItens.push({itemId:'',qtd:10,lote:'',validade:''}); renderEFItens(); }
function renderEFItens(){
  document.getElementById('ef-itens').innerHTML=efItens.map((ef,idx)=>`
    <div style="display:grid;grid-template-columns:1fr 70px 90px 110px 32px;gap:8px;align-items:center;margin-bottom:8px">
      <select class="form-control" style="font-size:10px" onchange="efItens[${idx}].itemId=+this.value;renderEFResumo()">
        <option value="">— item —</option>
        ${S.itens.map(i=>`<option value="${i.id}" ${ef.itemId===i.id?'selected':''}>${i.nome}</option>`).join('')}
      </select>
      <input type="number" class="form-control" value="${ef.qtd}" style="font-size:11px;text-align:center" oninput="efItens[${idx}].qtd=+this.value;renderEFResumo()">
      <input class="form-control" value="${ef.lote}" placeholder="Lote" style="font-size:11px" oninput="efItens[${idx}].lote=this.value">
      <input type="date" class="form-control" value="${ef.validade}" style="font-size:11px" oninput="efItens[${idx}].validade=this.value">
      <button class="btn btn-ghost btn-sm" style="color:var(--accent)" onclick="efItens.splice(${idx},1);renderEFItens()">✕</button>
    </div>
  `).join('');
  renderEFResumo();
}
function renderEFResumo(){
  const validos=efItens.filter(e=>e.itemId);
  document.getElementById('ef-resumo').innerHTML=`
    <div style="display:flex;justify-content:space-between;color:var(--dim)"><span>Itens distintos</span><strong>${validos.length}</strong></div>
    <div style="display:flex;justify-content:space-between;color:var(--dim)"><span>Total unidades</span><strong>${validos.reduce((s,e)=>s+e.qtd,0)}</strong></div>
    ${validos.map(e=>{
      const dep=S.deposito[e.itemId];
      const atual=dep?dep.qtd:0;
      return `<div style="border-top:1px solid var(--border);padding-top:8px;color:var(--dim)">
        <div style="margin-bottom:2px">${nomItem(e.itemId)}</div>
        <div style="color:var(--green)">+${e.qtd} un → novo saldo: ${atual+e.qtd}</div>
      </div>`;
    }).join('')}
    <button class="btn btn-primary" style="margin-top:8px;width:100%;justify-content:center" onclick="confirmarEntrada()">✓ Confirmar Entrada</button>
  `;
}
function confirmarEntrada(){
  const fornecedor=document.getElementById('ef-fornecedor').value;
  const resp=document.getElementById('ef-resp').value;
  const data=document.getElementById('ef-data').value;
  const validos=efItens.filter(e=>e.itemId&&e.qtd>0);
  if(!validos.length){ toast('Adicione ao menos um item.','error'); return; }
  validos.forEach(e=>{
    if(!S.deposito[e.itemId]) S.deposito[e.itemId]={qtd:0,reservado:0,validade:'—',lote:'—'};
    S.deposito[e.itemId].qtd+=e.qtd;
    if(e.lote) S.deposito[e.itemId].lote=e.lote;
    if(e.validade) S.deposito[e.itemId].validade=formatDate(e.validade);
    S.movimentacoes.unshift({id:uid(),data:formatDate(data),tipo:'ENTRADA',itemId:e.itemId,qtd:e.qtd,origem:fornecedor||'Fornecedor',destino:'Depósito',resp:resp||'Gestor'});
  });
  toast('Entrada registrada! Depósito atualizado.','ok');
  efItens=[{itemId:'',qtd:10,lote:'',validade:''}];
  renderEFItens();
  renderDeposito();
}

// ─── ADMIN: ESCOLAS ───────────────────────────────────────
function renderEscolas(){
  const busca=(document.getElementById('busca-escola')?.value||'').toLowerCase();
  const lista=S.escolas.filter(e=>e.nome.toLowerCase().includes(busca));
  document.getElementById('escolas-body').innerHTML=lista.length?`
    <table>
      <thead><tr><th>#</th><th>Nome</th><th>Responsável</th><th>Estoques</th><th>Ações</th></tr></thead>
      <tbody>${lista.map(e=>`<tr>
        <td>${e.id}</td><td>${e.nome}</td><td>${e.resp}</td><td>${e.estoques.join(', ')}</td>
        <td><button class="btn btn-ghost btn-sm" onclick="editarEscola(${e.id})">Editar</button>
            <button class="btn btn-ghost btn-sm" style="color:var(--accent)" onclick="deletarEscola(${e.id})">Excluir</button></td>
      </tr>`).join('')}</tbody>
    </table>`:`<div class="empty" style="padding:24px"><div class="empty-icon">🏫</div><div class="empty-title">Nenhuma escola encontrada</div></div>`;
}
function openModalEscola(){ document.getElementById('escola-edit-id').value=''; document.getElementById('modal-escola-titulo').textContent='Nova Escola'; ['nome','resp','end','tel'].forEach(f=>document.getElementById('escola-'+f).value=''); document.getElementById('escola-estoques').value='Cozinha, Almoxarifado'; openModal('modal-escola'); }
function editarEscola(id){ const e=S.escolas.find(x=>x.id===id); if(!e) return; document.getElementById('escola-edit-id').value=id; document.getElementById('modal-escola-titulo').textContent='Editar Escola'; document.getElementById('escola-nome').value=e.nome; document.getElementById('escola-resp').value=e.resp; document.getElementById('escola-end').value=e.end; document.getElementById('escola-tel').value=e.tel; document.getElementById('escola-estoques').value=e.estoques.join(', '); openModal('modal-escola'); }
function salvarEscola(){
  const id=+document.getElementById('escola-edit-id').value;
  const nome=document.getElementById('escola-nome').value.trim();
  if(!nome){ toast('Nome obrigatório.','error'); return; }
  const dados={nome,resp:document.getElementById('escola-resp').value,end:document.getElementById('escola-end').value,tel:document.getElementById('escola-tel').value,estoques:document.getElementById('escola-estoques').value.split(',').map(s=>s.trim()).filter(Boolean)};
  if(id){ const e=S.escolas.find(x=>x.id===id); Object.assign(e,dados); toast('Escola atualizada!','ok'); }
  else { S.escolas.push({id:uid(),...dados}); toast('Escola cadastrada!','ok'); }
  closeModal('modal-escola'); renderEscolas();
}
function deletarEscola(id){ S.escolas=S.escolas.filter(e=>e.id!==id); toast('Escola removida.'); renderEscolas(); }

// ─── ADMIN: ITENS ─────────────────────────────────────────
function renderItens(){
  const busca=(document.getElementById('busca-item')?.value||'').toLowerCase();
  const catFiltro=+document.getElementById('filter-item-cat')?.value||0;
  // preencher select de categorias
  const sel=document.getElementById('filter-item-cat');
  if(sel&&sel.options.length===1) S.categorias.forEach(c=>{const o=document.createElement('option');o.value=c.id;o.textContent=c.nome;sel.appendChild(o);});
  const lista=S.itens.filter(i=>(!catFiltro||i.catId===catFiltro)&&(!busca||i.nome.toLowerCase().includes(busca)));
  document.getElementById('itens-body').innerHTML=lista.length?`
    <table>
      <thead><tr><th>#</th><th>Nome</th><th>Categoria</th><th>Unidade</th><th>Ações</th></tr></thead>
      <tbody>${lista.map(i=>`<tr>
        <td>${i.id}</td><td>${i.nome}</td><td>${catNome(i.catId)}</td><td>${i.unidade}</td>
        <td><button class="btn btn-ghost btn-sm" onclick="editarItem(${i.id})">Editar</button>
            <button class="btn btn-ghost btn-sm" style="color:var(--accent)" onclick="deletarItem(${i.id})">Excluir</button></td>
      </tr>`).join('')}</tbody>
    </table>`:`<div class="empty" style="padding:24px"><div class="empty-icon">🗂</div><div class="empty-title">Nenhum item encontrado</div></div>`;
}
function openModalItem(){ document.getElementById('item-edit-id').value=''; document.getElementById('modal-item-titulo').textContent='Novo Item'; document.getElementById('item-nome').value=''; document.getElementById('item-desc').value=''; populateItemCatSelect(); openModal('modal-item'); }
function editarItem(id){ const i=S.itens.find(x=>x.id===id); if(!i) return; document.getElementById('item-edit-id').value=id; document.getElementById('modal-item-titulo').textContent='Editar Item'; document.getElementById('item-nome').value=i.nome; document.getElementById('item-desc').value=i.desc; populateItemCatSelect(i.catId); document.getElementById('item-unidade').value=i.unidade; openModal('modal-item'); }
function populateItemCatSelect(selId=null){ const sel=document.getElementById('item-cat'); sel.innerHTML=S.categorias.map(c=>`<option value="${c.id}" ${c.id===selId?'selected':''}>${c.nome}</option>`).join(''); }
function salvarItem(){
  const id=+document.getElementById('item-edit-id').value;
  const nome=document.getElementById('item-nome').value.trim();
  if(!nome){ toast('Nome obrigatório.','error'); return; }
  const dados={nome,catId:+document.getElementById('item-cat').value,unidade:document.getElementById('item-unidade').value,desc:document.getElementById('item-desc').value};
  if(id){ const i=S.itens.find(x=>x.id===id); Object.assign(i,dados); toast('Item atualizado!','ok'); }
  else { const nId=uid(); S.itens.push({id:nId,...dados}); S.deposito[nId]={qtd:0,reservado:0,validade:'—',lote:'—'}; toast('Item adicionado ao catálogo!','ok'); }
  closeModal('modal-item'); renderItens();
}
function deletarItem(id){ S.itens=S.itens.filter(i=>i.id!==id); delete S.deposito[id]; toast('Item removido.'); renderItens(); }

// ─── ADMIN: CATEGORIAS ────────────────────────────────────
function renderCategorias(){
  document.getElementById('categorias-body').innerHTML=`
    <table>
      <thead><tr><th>#</th><th>Nome</th><th>Descrição</th><th>Itens</th><th>Ações</th></tr></thead>
      <tbody>${S.categorias.map(c=>`<tr>
        <td>${c.id}</td><td>${c.nome}</td><td>${c.desc}</td>
        <td>${S.itens.filter(i=>i.catId===c.id).length}</td>
        <td><button class="btn btn-ghost btn-sm" onclick="editarCategoria(${c.id})">Editar</button>
            <button class="btn btn-ghost btn-sm" style="color:var(--accent)" onclick="deletarCategoria(${c.id})">Excluir</button></td>
      </tr>`).join('')}</tbody>
    </table>
  `;
}
function openModalCategoria(){ document.getElementById('cat-edit-id').value=''; document.getElementById('modal-cat-titulo').textContent='Nova Categoria'; document.getElementById('cat-nome').value=''; document.getElementById('cat-desc').value=''; openModal('modal-categoria'); }
function editarCategoria(id){ const c=S.categorias.find(x=>x.id===id); if(!c) return; document.getElementById('cat-edit-id').value=id; document.getElementById('modal-cat-titulo').textContent='Editar Categoria'; document.getElementById('cat-nome').value=c.nome; document.getElementById('cat-desc').value=c.desc; openModal('modal-categoria'); }
function salvarCategoria(){
  const id=+document.getElementById('cat-edit-id').value;
  const nome=document.getElementById('cat-nome').value.trim();
  if(!nome){ toast('Nome obrigatório.','error'); return; }
  const dados={nome,desc:document.getElementById('cat-desc').value};
  if(id){ const c=S.categorias.find(x=>x.id===id); Object.assign(c,dados); toast('Categoria atualizada!','ok'); }
  else { S.categorias.push({id:uid(),...dados}); toast('Categoria criada!','ok'); }
  closeModal('modal-categoria'); renderCategorias();
}
function deletarCategoria(id){ const usada=S.itens.some(i=>i.catId===id); if(usada){ toast('Não é possível excluir: categoria possui itens.','error'); return; } S.categorias=S.categorias.filter(c=>c.id!==id); toast('Categoria removida.'); renderCategorias(); }

// ─── MOVIMENTAÇÕES ────────────────────────────────────────
function renderMovimentacoes(){
  const tipo=document.getElementById('filter-mov-tipo')?.value||'';
  const lista=S.movimentacoes.filter(m=>!tipo||m.tipo===tipo);
  const tipoBadge={ENTRADA:'badge-received',TRANSFERÊNCIA:'badge-approved',BAIXA:'badge-canceled',DEVOLUÇÃO:'badge-pending'};
  document.getElementById('movimentacoes-body').innerHTML=lista.length?`
    <table>
      <thead><tr><th>#</th><th>Data</th><th>Tipo</th><th>Item</th><th>Qtd</th><th>Origem</th><th>Destino</th><th>Resp.</th></tr></thead>
      <tbody>${lista.map(m=>`<tr>
        <td>#${m.id}</td><td>${m.data}</td>
        <td><span class="badge ${tipoBadge[m.tipo]||'badge-info'}">${m.tipo}</span></td>
        <td>${nomItem(m.itemId)}</td><td>${m.qtd}</td>
        <td>${m.origem}</td><td>${m.destino}</td><td>${m.resp}</td>
      </tr>`).join('')}</tbody>
    </table>`:`<div class="empty" style="padding:24px"><div class="empty-icon">📊</div><div class="empty-title">Nenhuma movimentação</div></div>`;
}

// ─── HELPERS ─────────────────────────────────────────────
function formatDate(str){
  if(!str) return '—';
  if(str.includes('/')) return str;
  const [y,m,d]=str.split('-');
  return `${d}/${m}/${y}`;
}

// ─── OVERRIDES (buttons that open modals) ────────────────
// sobrescrever para injetar lógica
document.addEventListener('click', e=>{
  if(e.target.closest('[data-modal]')){
    const m=e.target.closest('[data-modal]').dataset.modal;
    openModal(m);
  }
});

// substituir chamadas nos botões do HTML que chamam openModal direto
window.openModal = function(id){
  if(id==='modal-add-item'){ openModalAddItem(); return; }
  if(id==='modal-novo-romaneio'){ openModalNovoRomaneio(); return; }
  document.getElementById(id)?.classList.add('open');
};

// ─── INIT ─────────────────────────────────────────────────
atualizarNotifPedidos();
renderDashEscola();
</script>
</body>
</html>