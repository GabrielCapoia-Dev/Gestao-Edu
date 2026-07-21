<?php

namespace App\Services\Dashboard;

use App\Models\Aviso;
use App\Models\AvisoLeitura;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AvisoBannerService
{
    public const AVISOS_POR_PAGINA = 3;

    public function __construct(
        private readonly PublicoAlvoService $publicoAlvoService,
    ) {}

    public function queryPara(User $user, ?CarbonInterface $agora = null): Builder
    {
        return $this->queryVigentesPara($user, $agora)
            ->whereDoesntHave('leituras', fn (Builder $leituras): Builder => $leituras
                ->where('user_id', $user->getKey())
                ->whereColumn('aviso_leituras.versao_envio', 'avisos.versao_envio'))
            ->orderByRaw("case prioridade
                when 'urgente' then 4
                when 'alta' then 3
                when 'normal' then 2
                when 'baixa' then 1
                else 0
            end desc")
            ->orderByRaw('case when posicao_preferencial is null then 1 else 0 end')
            ->orderBy('posicao_preferencial')
            ->orderByDesc('inicio_exibicao')
            ->orderBy('ordem_manual')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function marcarComoLido(User $user, int $avisoId, int $versaoEnvio): void
    {
        $aviso = $this->queryVigentesPara($user)
            ->whereKey($avisoId)
            ->where('versao_envio', $versaoEnvio)
            ->firstOrFail();

        AvisoLeitura::query()->updateOrCreate(
            [
                'aviso_id' => $aviso->getKey(),
                'user_id' => $user->getKey(),
                'versao_envio' => $aviso->versao_envio,
            ],
            ['lido_em' => now()],
        );
    }

    private function queryVigentesPara(User $user, ?CarbonInterface $agora = null): Builder
    {
        $query = Aviso::query()
            ->vigentes($agora)
            ->select('avisos.*');

        return $this->publicoAlvoService->aplicarEscopo(
            $query,
            $user,
            'publico_alvo_id',
        );
    }

    /** @return Collection<int, Aviso> */
    public function avisosPara(User $user, ?CarbonInterface $agora = null): Collection
    {
        return $this->queryPara($user, $agora)->get();
    }

    /** @return Collection<int, Collection<int, Aviso>> */
    public function paginasPara(User $user, ?CarbonInterface $agora = null): Collection
    {
        return $this->avisosPara($user, $agora)
            ->chunk(self::AVISOS_POR_PAGINA)
            ->map(fn (Collection $pagina): Collection => $this->organizarPagina($pagina))
            ->values();
    }

    /**
     * Resolve conflitos de posição sem remover avisos ou deixar lacunas visuais.
     * O primeiro aviso do ranking ocupa a posição solicitada; conflitos seguem
     * para o primeiro espaço livre, preservando a ordem original.
     *
     * @param Collection<int, Aviso> $avisos
     * @return Collection<int, Aviso>
     */
    public function organizarPagina(Collection $avisos): Collection
    {
        $avisos = $avisos->take(self::AVISOS_POR_PAGINA)->values();
        $posicoes = [0 => null, 1 => null, 2 => null];
        $alocados = [];

        foreach ($avisos as $indice => $aviso) {
            $posicao = $aviso->posicao_preferencial;

            if (! in_array($posicao, [1, 2, 3], true) || $posicoes[$posicao - 1] !== null) {
                continue;
            }

            $posicoes[$posicao - 1] = $aviso;
            $alocados[$indice] = true;
        }

        foreach ($avisos as $indice => $aviso) {
            if (isset($alocados[$indice])) {
                continue;
            }

            $espacoLivre = array_search(null, $posicoes, true);

            if ($espacoLivre === false) {
                break;
            }

            $posicoes[$espacoLivre] = $aviso;
        }

        return collect($posicoes)->filter()->values();
    }
}
