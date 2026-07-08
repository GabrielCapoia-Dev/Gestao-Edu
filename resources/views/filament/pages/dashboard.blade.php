@php
    $quickLinks = [
        [
            'permission' => 'Listar Pedidos',
            'route' => route('filament.admin.resources.pedidos.index'),
            'tone' => 'blue',
            'icon' => 'heroicon-o-clipboard-document-list',
            'title' => 'Pedidos',
            'description' => 'Gerencie solicitações de manutenção.',
        ],
        [
            'permission' => 'Listar Escolas',
            'route' => route('filament.admin.resources.escolas.index'),
            'tone' => 'teal',
            'icon' => 'heroicon-o-building-office-2',
            'title' => 'Escolas',
            'description' => 'Cadastro de unidades escolares.',
        ],
        [
            'permission' => 'Listar Turmas',
            'route' => route('filament.admin.resources.turmas.index'),
            'tone' => 'amber',
            'icon' => 'heroicon-o-rectangle-stack',
            'title' => 'Turmas',
            'description' => 'Organização de turmas e séries.',
        ],
        [
            'permission' => 'Responder Avaliações',
            'route' => route('filament.admin.pages.avaliacoes-professor'),
            'tone' => 'green',
            'icon' => 'heroicon-o-check-badge',
            'title' => 'Minhas Avaliações',
            'description' => 'Responda avaliações das suas turmas.',
        ],
        [
            'permission' => 'Listar Avaliações',
            'route' => route('filament.admin.pages.avaliacoes-gestao'),
            'tone' => 'teal',
            'icon' => 'heroicon-o-rectangle-stack',
            'title' => 'Gestão de Avaliações',
            'description' => 'Crie e organize ciclos avaliativos.',
        ],
        [
            'permission' => 'Listar Pautas',
            'route' => route('filament.admin.pages.avaliacoes-pautas'),
            'tone' => 'blue',
            'icon' => 'heroicon-o-document-text',
            'title' => 'Pautas',
            'description' => 'Gerencie perguntas e vínculos por componente.',
        ],
        [
            'permission' => 'Listar Alternativas',
            'route' => route('filament.admin.pages.avaliacoes-alternativas'),
            'tone' => 'amber',
            'icon' => 'heroicon-o-arrows-right-left',
            'title' => 'Alternativas',
            'description' => 'Cadastre opções para avaliação e observação.',
        ],
        [
            'permission' => 'Listar Professores',
            'route' => route('filament.admin.resources.professores.index'),
            'tone' => 'purple',
            'icon' => 'heroicon-o-user-group',
            'title' => 'Professores',
            'description' => 'Gestão do corpo docente.',
        ],
        [
            'permission' => 'Listar Contratos',
            'route' => route('filament.admin.resources.contratos.index'),
            'tone' => 'orange',
            'icon' => 'heroicon-o-document-duplicate',
            'title' => 'Contratos',
            'description' => 'Gestão de contratos ativos.',
        ],
        [
            'permission' => 'Listar Pedidos: Merenda',
            'route' => route('filament.admin.resources.pedidos-merenda.index'),
            'tone' => 'green',
            'icon' => 'heroicon-o-document-duplicate',
            'title' => 'Merenda',
            'description' => 'Pedidos de merenda escolar.',
        ],
        [
            'permission' => 'Listar Inventários',
            'route' => route('filament.admin.pages.inventarios'),
            'tone' => 'teal',
            'icon' => 'heroicon-o-clipboard-document-list',
            'title' => 'Inventários',
            'description' => 'Painel macro dos estoques escolares.',
        ],
        [
            'permission' => 'Listar Gestão de Inventário',
            'route' => route('filament.admin.pages.gestao-inventario'),
            'tone' => 'green',
            'icon' => 'heroicon-o-clipboard-document-list',
            'title' => 'Gestão de Inventário',
            'description' => 'Operação do estoque da escola.',
        ],
        [
            'permission' => 'Listar Pedidos de Inventário',
            'route' => route('filament.admin.resources.pedidos-inventario.index'),
            'tone' => 'rose',
            'icon' => 'heroicon-o-clipboard-document-list',
            'title' => 'Pedidos de Inventário',
            'description' => 'Solicitações escola -> matriz e romaneios.',
        ],
        [
            'permission' => 'Listar Gestão de Estoque',
            'route' => route('filament.admin.pages.gestao-estoque'),
            'tone' => 'amber',
            'icon' => 'heroicon-o-cube',
            'title' => 'Gestão de Estoque',
            'description' => 'Controle e movimentação de estoque.',
        ],
        [
            'permission' => 'Listar Gestão de Margens',
            'route' => route('filament.admin.pages.gestao-margens'),
            'tone' => 'purple',
            'icon' => 'heroicon-o-chart-bar-square',
            'title' => 'Gestão de Margens',
            'description' => 'Controle de margens e indicadores.',
        ],
        [
            'permission' => 'Listar Componente Curricular',
            'route' => route('filament.admin.resources.componentes-curriculares.index'),
            'tone' => 'blue',
            'icon' => 'heroicon-o-document-text',
            'title' => 'Componentes Curriculares',
            'description' => 'Disciplinas e grade curricular.',
        ],
        [
            'permission' => 'Listar Séries',
            'route' => route('filament.admin.resources.series.index'),
            'tone' => 'orange',
            'icon' => 'heroicon-o-list-bullet',
            'title' => 'Séries',
            'description' => 'Séries e anos escolares.',
        ],
        [
            'permission' => 'Listar Servidores',
            'route' => route('filament.admin.resources.servidores.index', ['tab' => 'com_acesso']),
            'tone' => 'slate',
            'icon' => 'heroicon-o-users',
            'title' => 'Pessoas',
            'description' => 'Cadastro, vínculos pedagógicos e acesso ao sistema.',
        ],
        [
            'permission' => 'Listar Empresa Contratada',
            'route' => route('filament.admin.resources.empresas-contratadas.index'),
            'tone' => 'orange',
            'icon' => 'heroicon-o-building-office-2',
            'title' => 'Empresas',
            'description' => 'Empresas contratadas e parceiros.',
        ],
        [
            'permission' => 'Listar Níveis de Acesso',
            'route' => route('filament.admin.resources.niveis-de-acesso.index'),
            'tone' => 'purple',
            'icon' => 'heroicon-o-shield-check',
            'title' => 'Níveis de Acesso',
            'description' => 'Roles e permissões do sistema.',
        ],
        [
            'permission' => 'Listar Dominios de Email',
            'route' => route('filament.admin.resources.dominio-emails.index'),
            'tone' => 'amber',
            'icon' => 'heroicon-o-envelope',
            'title' => 'Domínios de E-mail',
            'description' => 'Domínios permitidos para acesso.',
        ],
        [
            'permission' => 'Visualizar Feedback de Pedidos',
            'route' => route('filament.admin.pages.feedback-pedidos'),
            'tone' => 'rose',
            'icon' => 'heroicon-o-star',
            'title' => 'Feedbacks',
            'description' => 'Avaliações dos pedidos concluídos.',
        ],
    ];
@endphp

<x-filament-panels::page>
    <div class="welcome-root">
        <div class="hero">
            <div class="hero-bg-grid"></div>
            <div class="hero-bg-glow"></div>

            <div class="hero-inner">
                <div class="hero-eyebrow">
                    <span class="pulse-dot"></span>
                    Sistema ativo
                </div>

                <h1 class="hero-title">
                    Bem-vindo ao<br>
                    <span class="hero-title-accent">Gestão Edu</span>
                </h1>

                <p class="hero-subtitle">
                    Central de gestão escolar. Acesse rapidamente os módulos do sistema abaixo.
                </p>
            </div>
        </div>

        <div class="nav-section">
            <p class="nav-label">Acesso rápido</p>

            <div class="nav-grid">
                @foreach ($quickLinks as $quickLink)
                    @can($quickLink['permission'])
                        <a href="{{ $quickLink['route'] }}" class="nav-card nav-card--{{ $quickLink['tone'] }}">
                            <div class="nav-card-icon">
                                <x-filament::icon :icon="$quickLink['icon']" />
                            </div>

                            <div class="nav-card-body">
                                <h3>{{ $quickLink['title'] }}</h3>
                                <p>{{ $quickLink['description'] }}</p>
                            </div>

                            <div class="nav-card-arrow">-></div>
                        </a>
                    @endcan
                @endforeach
            </div>
        </div>
    </div>
</x-filament-panels::page>
