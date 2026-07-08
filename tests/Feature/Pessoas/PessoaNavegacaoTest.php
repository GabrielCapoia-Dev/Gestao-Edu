<?php

namespace Tests\Feature\Pessoas;

use App\Filament\Admin\Resources\DominioEmails\DominioEmailResource;
use App\Filament\Admin\Resources\Roles\RoleResource;
use App\Filament\Admin\Resources\Servidores\ServidorResource;
use Tests\TestCase;

class PessoaNavegacaoTest extends TestCase
{
    public function test_recursos_de_acesso_ficam_aninhados_em_pessoas(): void
    {
        $this->assertSame('Pessoas', ServidorResource::getNavigationLabel());
        $this->assertSame('Acesso', ServidorResource::getNavigationGroup());

        $reflection = new \ReflectionClass(RoleResource::class);
        $parent = $reflection->getProperty('navigationParentItem');
        $parent->setAccessible(true);
        $this->assertSame('Pessoas', $parent->getValue());

        $reflection = new \ReflectionClass(DominioEmailResource::class);
        $parent = $reflection->getProperty('navigationParentItem');
        $parent->setAccessible(true);
        $this->assertSame('Pessoas', $parent->getValue());
    }
}