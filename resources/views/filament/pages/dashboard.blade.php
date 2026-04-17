<x-filament-panels::page>

    <div class="welcome-root">

        {{-- HERO --}}
        <div class="hero">
            <div class="hero-bg-grid"></div>
            <div class="hero-bg-glow"></div>

            <div class="hero-inner">
                <div class="hero-eyebrow">
                    <span class="pulse-dot"></span>
                    Sistema Ativo
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

        {{-- NAVIGATION CARDS --}}
        <div class="nav-section">
            <p class="nav-label">Acesso Rápido</p>

            <div class="nav-grid">

                @can('Listar Pedidos')
                <a href="{{ route('filament.admin.resources.pedidos.index') }}" class="nav-card nav-card--blue">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Pedidos</h3>
                        <p>Gerencie solicitações de manutenção</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Escolas')
                <a href="{{ route('filament.admin.resources.escolas.index') }}" class="nav-card nav-card--teal">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Escolas</h3>
                        <p>Cadastro de unidades escolares</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Turmas')
                <a href="{{ route('filament.admin.resources.turmas.index') }}" class="nav-card nav-card--amber">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Turmas</h3>
                        <p>Organização de turmas e séries</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Responder Avaliações')
                <a href="{{ route('filament.admin.pages.avaliacoes-professor') }}" class="nav-card nav-card--green">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m5.25 2.25a8.97 8.97 0 0 1-8.25 8.964A8.97 8.97 0 0 1 3.75 12a8.97 8.97 0 0 1 8.25-8.964A8.97 8.97 0 0 1 20.25 12Z" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Avaliações</h3>
                        <p>Responder avaliações das suas turmas</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Professores')
                <a href="{{ route('filament.admin.resources.professores.index') }}" class="nav-card nav-card--purple">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Professores</h3>
                        <p>Gestão do corpo docente</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Contratos')
                <a href="{{ route('filament.admin.resources.contratos.index') }}" class="nav-card nav-card--orange">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Contratos</h3>
                        <p>Gestão de contratos ativos</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Pedidos: Merenda')
                <a href="{{ route('filament.admin.resources.pedidos-merenda.index') }}" class="nav-card nav-card--green">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8.25v-1.5m0 1.5c-1.355 0-2.697.056-4.024.166C6.845 8.51 6 9.473 6 10.608v2.513m6-4.871c1.355 0 2.697.056 4.024.166C17.155 8.51 18 9.473 18 10.608v2.513M15 20.604v-3.354c0-1.135-.845-2.098-1.976-2.192a48.76 48.76 0 0 0-6.048 0C5.845 15.152 5 16.115 5 17.25v3.354M9 20.604v-3.354M12 20.604v-3.354m0-11.853a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Merenda</h3>
                        <p>Pedidos de merenda escolar</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Inventários')
                <a href="{{ route('filament.admin.pages.inventarios') }}" class="nav-card nav-card--teal">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5h16.5v4.5H3.75V4.5Zm0 7.5h7.5v7.5h-7.5V12Zm10.5 0h6v3h-6v-3Zm0 6h6v1.5h-6V18Z" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Inventários</h3>
                        <p>Painel macro dos estoques escolares</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Gestão de Inventário')
                <a href="{{ route('filament.admin.pages.gestao-inventario') }}" class="nav-card nav-card--green">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5v10.125c0 .621-.504 1.125-1.125 1.125H4.875A1.125 1.125 0 0 1 3.75 17.625V7.5m16.5 0-1.279-3.196A1.125 1.125 0 0 0 17.93 3.75H6.07c-.46 0-.874.28-1.042.708L3.75 7.5m16.5 0H3.75m4.5 4.5h7.5" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Gestão de Inventário</h3>
                        <p>Operação do estoque da escola</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Pedidos de Inventário')
                <a href="{{ route('filament.admin.resources.pedidos-inventario.index') }}" class="nav-card nav-card--rose">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75A2.25 2.25 0 0 1 4.5 4.5h15A2.25 2.25 0 0 1 21.75 6.75v10.5A2.25 2.25 0 0 1 19.5 19.5h-15a2.25 2.25 0 0 1-2.25-2.25V6.75Zm4.5 3.75h10.5m-10.5 3h6" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Pedidos de Inventário</h3>
                        <p>Solicitações escola → matriz e romaneios</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Gestão de Estoque')
                <a href="{{ route('filament.admin.pages.gestao-estoque') }}" class="nav-card nav-card--amber">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Gestão de Estoque</h3>
                        <p>Controle e movimentação de estoque</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Gestão de Margens')
                <a href="{{ route('filament.admin.pages.gestao-margens') }}" class="nav-card nav-card--purple">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Gestão de Margens</h3>
                        <p>Controle de margens e indicadores</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Equipe Gestora')
                <a href="{{ route('filament.admin.resources.equipe-gestora.index') }}" class="nav-card nav-card--teal">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Equipe Gestora</h3>
                        <p>Gestão da equipe administrativa</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Componente Curricular')
                <a href="{{ route('filament.admin.resources.componentes-curriculares.index') }}" class="nav-card nav-card--blue">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Componentes Curriculares</h3>
                        <p>Disciplinas e grade curricular</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Séries')
                <a href="{{ route('filament.admin.resources.series.index') }}" class="nav-card nav-card--orange">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Séries</h3>
                        <p>Séries e anos escolares</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Usuários')
                <a href="{{ route('filament.admin.resources.usuarios.index') }}" class="nav-card nav-card--slate">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Usuários</h3>
                        <p>Controle de acesso e permissões</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Empresa Contratada')
                <a href="{{ route('filament.admin.resources.empresas-contratadas.index') }}" class="nav-card nav-card--orange">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Empresas</h3>
                        <p>Empresas contratadas e parceiros</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Níveis de Acesso')
                <a href="{{ route('filament.admin.resources.niveis-de-acesso.index') }}" class="nav-card nav-card--purple">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Níveis de Acesso</h3>
                        <p>Roles e permissões do sistema</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Dominios de Email')
                <a href="{{ route('filament.admin.resources.dominio-emails.index') }}" class="nav-card nav-card--amber">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Domínios de E-mail</h3>
                        <p>Domínios permitidos para acesso</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Visualizar Feedback de Pedidos')
                <a href="{{ route('filament.admin.pages.feedback-pedidos') }}" class="nav-card nav-card--rose">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Feedbacks</h3>
                        <p>Avaliações dos pedidos concluídos</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan
            </div>
        </div>

    </div>

    <style>
        /* ─── RESET LOCAL ─────────────────────────────────────── */
        .welcome-root *,
        .welcome-root *::before,
        .welcome-root *::after {
            box-sizing: border-box;
        }

        .welcome-root {
            font-family: 'Georgia', 'Times New Roman', serif;
        }

        /* ─── HERO ────────────────────────────────────────────── */
        .hero {
            position: relative;
            overflow: hidden;
            border-radius: 1.25rem;
            padding: 3.5rem 2.5rem 3rem;
            margin-bottom: 2rem;
            background: linear-gradient(135deg, #0c1e3e 0%, #0f2d5e 50%, #0a1f45 100%);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .hero-bg-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            mask-image: radial-gradient(ellipse at center, black 40%, transparent 80%);
        }

        .hero-bg-glow {
            position: absolute;
            top: -80px;
            right: -60px;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(14, 99, 196, 0.25) 0%, transparent 70%);
            pointer-events: none;
        }

        .hero-inner {
            position: relative;
            z-index: 1;
            max-width: 640px;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #86efac;
            font-family: 'Courier New', monospace;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            margin-bottom: 1.25rem;
        }

        .pulse-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #22c55e;
            animation: pulse-green 2s ease-in-out infinite;
            flex-shrink: 0;
        }

        @keyframes pulse-green {
            0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.5); }
            50%       { box-shadow: 0 0 0 6px rgba(34, 197, 94, 0); }
        }

        .hero-title {
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 700;
            color: #f0f6ff;
            line-height: 1.15;
            margin: 0 0 1rem;
            letter-spacing: -0.02em;
        }

        .hero-title-accent {
            background: linear-gradient(90deg, #60a5fa, #93c5fd, #bfdbfe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-subtitle {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.55);
            line-height: 1.65;
            margin: 0;
            font-family: system-ui, sans-serif;
            font-weight: 400;
        }

        /* ─── NAV SECTION ─────────────────────────────────────── */
        .nav-section {
            animation: fadeUp 0.5s ease-out 0.15s both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .nav-label {
            font-family: 'Courier New', monospace;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: #6b7280;
            margin: 0 0 1rem 0.25rem;
        }

        .dark .nav-label { color: #4b5563; }

        .nav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 0.875rem;
        }

        /* ─── NAV CARD ────────────────────────────────────────── */
        .nav-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.1rem 1.25rem;
            border-radius: 0.875rem;
            text-decoration: none;
            border: 1.5px solid transparent;
            background: white;
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.07), 0 1px 2px rgba(0, 0, 0, 0.04);
        }

        .dark .nav-card {
            background: rgb(17 24 39);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        .nav-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.1);
        }

        .nav-card-icon {
            flex-shrink: 0;
            width: 42px;
            height: 42px;
            border-radius: 0.625rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-card-icon svg {
            width: 20px;
            height: 20px;
        }

        .nav-card-body {
            flex: 1;
            min-width: 0;
        }

        .nav-card-body h3 {
            font-family: system-ui, sans-serif;
            font-size: 0.925rem;
            font-weight: 600;
            color: #111827;
            margin: 0 0 0.2rem;
            line-height: 1.3;
        }

        .dark .nav-card-body h3 { color: #f3f4f6; }

        .nav-card-body p {
            font-family: system-ui, sans-serif;
            font-size: 0.78rem;
            color: #6b7280;
            margin: 0;
            line-height: 1.4;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dark .nav-card-body p { color: #9ca3af; }

        .nav-card-arrow {
            font-size: 1rem;
            opacity: 0;
            transform: translateX(-4px);
            transition: opacity 0.18s ease, transform 0.18s ease;
            flex-shrink: 0;
        }

        .nav-card:hover .nav-card-arrow {
            opacity: 1;
            transform: translateX(0);
        }

        /* ─── COLOR VARIANTS ──────────────────────────────────── */
        .nav-card--blue   { --c: #2563eb; --ic: #dbeafe; }
        .nav-card--green  { --c: #16a34a; --ic: #dcfce7; }
        .nav-card--amber  { --c: #d97706; --ic: #fef3c7; }
        .nav-card--purple { --c: #7c3aed; --ic: #ede9fe; }
        .nav-card--teal   { --c: #0d9488; --ic: #ccfbf1; }
        .nav-card--rose   { --c: #e11d48; --ic: #ffe4e6; }
        .nav-card--slate  { --c: #475569; --ic: #e2e8f0; }
        .nav-card--orange { --c: #ea580c; --ic: #fed7aa; }

        .nav-card:hover   { border-color: var(--c); }
        .nav-card-icon    { background: var(--ic); color: var(--c); }
        .nav-card-arrow   { color: var(--c); }

        .dark .nav-card--blue   { --ic: rgba(37, 99, 235, 0.18); }
        .dark .nav-card--green  { --ic: rgba(22, 163, 74, 0.18); }
        .dark .nav-card--amber  { --ic: rgba(217, 119, 6, 0.18); }
        .dark .nav-card--purple { --ic: rgba(124, 58, 237, 0.18); }
        .dark .nav-card--teal   { --ic: rgba(13, 148, 136, 0.18); }
        .dark .nav-card--rose   { --ic: rgba(225, 29, 72, 0.18); }
        .dark .nav-card--slate  { --ic: rgba(71, 85, 105, 0.18); }
        .dark .nav-card--orange { --ic: rgba(234, 88, 12, 0.18); }

        /* ─── RESPONSIVE ──────────────────────────────────────── */
        @media (max-width: 640px) {
            .hero { padding: 2.5rem 1.5rem 2rem; }
            .nav-grid { grid-template-columns: 1fr; }
        }
    </style>

</x-filament-panels::page>
