<?php

namespace App\Services;

use App\Models\FuncaoAdministrativa;
use App\Models\Pessoa;
use App\Models\Servidor;
use Illuminate\Database\Eloquent\Builder;

class PessoaObrasService extends PessoaManutencaoService
{
    public function criarPessoaObras(array $dadosPessoa, array $dadosObras): Servidor
    {
        return parent::criarPessoaManutencao($dadosPessoa, $dadosObras);
    }

    public function atualizarPessoaObras(
        Pessoa|Servidor $pessoa,
        array $dadosPessoa,
        array $dadosObras,
    ): Servidor {
        return parent::atualizarPessoaManutencao($pessoa, $dadosPessoa, $dadosObras);
    }

    public function converterProfessorParaObras(
        Pessoa|Servidor $pessoa,
        array $dadosPessoa,
        array $dadosObras,
    ): Servidor {
        return parent::converterProfessorParaManutencao($pessoa, $dadosPessoa, $dadosObras);
    }

    public function converterEquipeGestoraParaObras(
        Pessoa|Servidor $pessoa,
        array $dadosPessoa,
        array $dadosObras,
    ): Servidor {
        return parent::converterEquipeGestoraParaManutencao($pessoa, $dadosPessoa, $dadosObras);
    }

    public function encerrarVinculosObras(
        Pessoa|Servidor $pessoa,
        bool $reconciliarAcesso = true,
    ): void {
        parent::encerrarVinculosManutencao($pessoa, $reconciliarAcesso);
    }

    protected function nomeCargo(): string
    {
        return 'Obras';
    }

    protected function nomeRole(): string
    {
        return 'Obras';
    }

    protected function campoValidacao(): string
    {
        return 'obras';
    }

    protected function origemVinculo(): string
    {
        return 'obras';
    }

    protected function funcaoPadrao(): FuncaoAdministrativa
    {
        return FuncaoAdministrativa::obrasPadrao();
    }

    protected function aplicarEscopoFuncao(Builder $query): Builder
    {
        return $query->obras();
    }
}
