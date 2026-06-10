@extends('public.layout')

@section('title', 'Gestao Edu | Secretaria Municipal de Educacao de Umuarama')
@section('description', 'Homepage publica do Gestao Edu, sistema institucional da Secretaria Municipal de Educacao de Umuarama.')

@section('content')
    <section class="hero">
        <div class="wrap hero-grid">
            <div>
                <p class="eyebrow">Sistema institucional</p>
                <h1>Gestao Edu</h1>
                <p class="lead">
                    Plataforma da Secretaria Municipal de Educacao de Umuarama para apoiar a gestao escolar,
                    administrativa e operacional da rede municipal de ensino.
                </p>
                <p class="lead">
                    O sistema atende servidores autorizados da Secretaria, equipes gestoras, escolas, professores
                    e setores administrativos, centralizando informacoes e fluxos de trabalho em um ambiente seguro.
                </p>
                <div class="hero-actions">
                    <a class="button secondary" href="/admin/login">Acessar sistema</a>
                    <a class="button" href="{{ route('public.privacy', [], false) }}">Politica de Privacidade</a>
                </div>
            </div>

            <aside class="hero-panel" aria-label="Resumo do sistema">
                <strong>Umuarama - PR</strong>
                <p>
                    Uma base unica para organizar processos educacionais, acompanhar informacoes da rede
                    e apoiar decisoes com mais rastreabilidade.
                </p>
            </aside>
        </div>
    </section>

    <section class="section">
        <div class="wrap">
            <p class="eyebrow">Finalidade</p>
            <h2>Para que o Gestao Edu serve</h2>
            <p>
                O Gestao Edu foi criado para reunir, em um ambiente digital, rotinas que fazem parte da administracao
                da educacao municipal. A plataforma auxilia no acompanhamento de usuarios, unidades escolares,
                turmas, professores, avaliacoes, relatorios, manutencoes, estoque e demais processos internos.
            </p>
        </div>
    </section>

    <section class="section alt">
        <div class="wrap">
            <p class="eyebrow">Publico atendido</p>
            <h2>Quem utiliza a plataforma</h2>
            <div class="grid">
                <article class="card">
                    <h3>Secretaria Municipal de Educacao</h3>
                    <p>Equipes tecnicas e administrativas usam o sistema para acompanhar dados, permissoes, relatorios e processos da rede.</p>
                </article>
                <article class="card">
                    <h3>Escolas e equipes gestoras</h3>
                    <p>Diretores, coordenadores e equipes escolares acessam informacoes relacionadas a sua unidade e aos fluxos liberados.</p>
                </article>
                <article class="card">
                    <h3>Professores e servidores</h3>
                    <p>Usuarios autorizados acessam funcionalidades conforme sua funcao, perfil e vinculos institucionais.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="wrap">
            <p class="eyebrow">Modulos</p>
            <h2>Principais areas de apoio</h2>
            <div class="grid">
                <article class="card">
                    <h3>Gestao pedagogica</h3>
                    <p>Apoio a cadastros educacionais, turmas, professores, avaliacoes e relatorios pedagogicos.</p>
                </article>
                <article class="card">
                    <h3>Administracao e acesso</h3>
                    <p>Controle de usuarios, autorizacoes, perfis de acesso e registros operacionais.</p>
                </article>
                <article class="card">
                    <h3>Operacao da rede</h3>
                    <p>Fluxos de manutencao, estoque, alimentacao escolar, documentos e acompanhamentos internos.</p>
                </article>
            </div>
        </div>
    </section>
@endsection
