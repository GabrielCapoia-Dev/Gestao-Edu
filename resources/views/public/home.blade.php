@extends('public.layout')

@section('title', 'Gestão Edu | Secretaria Municipal de Educação de Umuarama')
@section('description', 'Página inicial pública do Gestão Edu, sistema institucional da Secretaria Municipal de Educação de Umuarama.')

@section('content')
    <section class="public-hero">
        <div class="wrap hero-grid">
            <div class="hero-copy">
                <p class="eyebrow">Prefeitura Municipal de Umuarama</p>
                <h1>Gestão escolar simples, clara e conectada.</h1>
                <p class="lead">
                    O Gestão Edu é a plataforma institucional da Secretaria Municipal de Educação de Umuarama para
                    organizar rotinas escolares, administrativas e operacionais da rede municipal de ensino.
                </p>
                <p class="lead">
                    O acesso é destinado a usuários autorizados, com recursos liberados conforme perfil,
                    vínculo institucional, escola, setor e responsabilidade de trabalho.
                </p>
                <div class="hero-actions">
                    <a class="button" href="/admin/login">Acessar sistema</a>
                    <a class="button secondary" href="{{ route('public.privacy', [], false) }}">Política de Privacidade</a>
                </div>
            </div>

            <aside class="hero-panel" aria-label="Resumo do sistema">
                <span class="status-pill">
                    <span class="status-dot" aria-hidden="true"></span>
                    Ambiente institucional
                </span>

                <div>
                    <h2>Umuarama - PR</h2>
                    <p>
                        Uma base integrada para apoiar o acompanhamento de escolas, turmas, professores,
                        avaliações, documentos, solicitações, estoque e alimentação escolar.
                    </p>
                </div>

                <ul class="metric-list">
                    <li>
                        <strong>Acesso controlado</strong>
                        <span>Permissões, papéis e vínculos definem o que cada usuário pode consultar ou registrar.</span>
                    </li>
                    <li>
                        <strong>Fluxos rastreáveis</strong>
                        <span>Registros, históricos e relatórios ajudam a acompanhar decisões e processos internos.</span>
                    </li>
                    <li>
                        <strong>Uso institucional</strong>
                        <span>A plataforma atende rotinas oficiais da Secretaria Municipal de Educação.</span>
                    </li>
                </ul>
            </aside>
        </div>
    </section>

    <section class="section compact">
        <div class="wrap">
            <div class="section-header">
                <p class="eyebrow">Finalidade</p>
                <h2>Uma plataforma para a rotina da rede municipal.</h2>
                <p>
                    O sistema reúne informações essenciais para que equipes da Secretaria, escolas, gestores,
                    professores e setores administrativos trabalhem com dados organizados, acesso seguro e
                    acompanhamento consistente dos processos educacionais.
                </p>
            </div>

            <div class="grid">
                <article class="card">
                    <h3>Gestão pedagógica</h3>
                    <p>
                        Apoia cadastros escolares, turmas, professores, componentes curriculares, avaliações,
                        pareceres, relatórios pedagógicos e acompanhamento de estudantes.
                    </p>
                </article>
                <article class="card">
                    <h3>Administração e acesso</h3>
                    <p>
                        Organiza usuários, perfis, permissões, vínculos com escolas e setores, mantendo o acesso
                        alinhado às responsabilidades institucionais.
                    </p>
                </article>
                <article class="card">
                    <h3>Operação da rede</h3>
                    <p>
                        Centraliza solicitações de manutenção, alimentação escolar, controle de estoque,
                        documentos e acompanhamentos administrativos.
                    </p>
                </article>
            </div>
        </div>
    </section>

   
@endsection
