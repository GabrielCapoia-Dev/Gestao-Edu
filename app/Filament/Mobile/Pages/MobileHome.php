<?php

namespace App\Filament\Mobile\Pages;

use App\Filament\Admin\Pages\Relatorios\RelatorioComponenteProfessorFaltando;
use App\Filament\Admin\Pages\Relatorios\RelatorioProfessorComponenteTurma;
use App\Filament\Admin\Pages\Relatorios\RelatoriosDashboard;
use App\Filament\Admin\Resources\DominioEmails\DominioEmailResource;
use App\Filament\Admin\Resources\Roles\RoleResource;
use App\Filament\Admin\Resources\Users\UserResource;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class MobileHome extends Page
{
    protected string $view = 'filament.mobile.pages.mobile-home';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::HomeModern;

    protected static ?string $navigationLabel = 'Inicio';

    protected static ?string $title = 'Painel mobile';

    protected static ?string $slug = 'inicio';

    protected static ?int $navigationSort = -100;

    public static function canAccess(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        return collect([
            UserResource::canAccess(),
            DominioEmailResource::canAccess(),
            RoleResource::canAccess(),
            RelatoriosDashboard::canAccess(),
            RelatorioProfessorComponenteTurma::canAccess(),
            RelatorioComponenteProfessorFaltando::canAccess(),
        ])->contains(true);
    }

    public function getAccessCards(): array
    {
        return array_values(array_filter([
            $this->makeCard(
                canAccess: UserResource::canAccess(),
                title: 'Usuarios',
                description: 'Gerencie acessos, liberacoes e vinculos principais.',
                url: UserResource::getUrl(panel: 'mobile'),
                icon: Heroicon::OutlinedUsers,
                tone: 'slate',
            ),
            $this->makeCard(
                canAccess: DominioEmailResource::canAccess(),
                title: 'Dominios de e-mail',
                description: 'Controle quais dominios podem entrar no sistema.',
                url: DominioEmailResource::getUrl(panel: 'mobile'),
                icon: Heroicon::OutlinedEnvelope,
                tone: 'amber',
            ),
            $this->makeCard(
                canAccess: RoleResource::canAccess(),
                title: 'Niveis de acesso',
                description: 'Revise perfis, permissoes e composicoes reutilizaveis.',
                url: RoleResource::getUrl(panel: 'mobile'),
                icon: Heroicon::OutlinedShieldCheck,
                tone: 'sky',
            ),
        ]));
    }

    public function getReportCards(): array
    {
        return array_values(array_filter([
            $this->makeCard(
                canAccess: RelatoriosDashboard::canAccess(),
                title: 'Central de relatorios',
                description: 'Veja o panorama geral e entre nos relatórios disponiveis.',
                url: RelatoriosDashboard::getUrl(panel: 'mobile'),
                icon: Heroicon::OutlinedChartBar,
                tone: 'emerald',
            ),
            $this->makeCard(
                canAccess: RelatorioProfessorComponenteTurma::canAccess(),
                title: 'Professor por turma',
                description: 'Cruze escola, serie, turma e componente em uma unica consulta.',
                url: RelatorioProfessorComponenteTurma::getUrl(panel: 'mobile'),
                icon: Heroicon::OutlinedClipboardDocumentList,
                tone: 'violet',
            ),
            $this->makeCard(
                canAccess: RelatorioComponenteProfessorFaltando::canAccess(),
                title: 'Componentes sem professor',
                description: 'Identifique faltas de cobertura e exporte o consolidado.',
                url: RelatorioComponenteProfessorFaltando::getUrl(panel: 'mobile'),
                icon: Heroicon::OutlinedDocumentChartBar,
                tone: 'rose',
            ),
        ]));
    }

    public function getSectionsCount(): int
    {
        return collect([
            count($this->getAccessCards()) > 0,
            count($this->getReportCards()) > 0,
        ])->filter()->count();
    }

    protected function makeCard(
        bool $canAccess,
        string $title,
        string $description,
        string $url,
        Heroicon $icon,
        string $tone,
    ): ?array {
        if (! $canAccess) {
            return null;
        }

        return [
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'icon' => $icon,
            'tone' => $tone,
        ];
    }
}
