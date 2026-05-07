<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\User;
use App\Services\Avaliacoes\AvaliacaoDocumentoExportService;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use ZipArchive;

class AlunoTransferenciaParecerService
{
    public function __construct(
        private readonly AvaliacaoDocumentoExportService $documentoExportService,
        private readonly AlunoMovimentacaoService $movimentacaoService,
    ) {}

    public function exportarETransferir(Aluno $aluno, User $usuario): BinaryFileResponse|Response
    {
        $aluno->loadMissing('turma.avaliacoes');

        if (! $aluno->estaMatriculado()) {
            throw new RuntimeException('Somente alunos matriculados podem gerar parecer de transferencia.');
        }

        if (! $aluno->turma) {
            throw new NotFoundHttpException('Turma do aluno nao encontrada.');
        }

        $avaliacoes = $aluno->turma->avaliacoes()
            ->orderBy('data_inicio')
            ->orderBy('avaliacoes.id')
            ->get(['avaliacoes.id', 'nome', 'data_inicio', 'data_fim', 'tipo_avaliacao_id', 'periodo_avaliacao_id']);

        if ($avaliacoes->isEmpty()) {
            throw new NotFoundHttpException('Nenhuma avaliacao encontrada para gerar o parecer de transferencia.');
        }

        $zipPath = storage_path('app/parecer-transferencia-'.Str::uuid().'.zip');
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Nao foi possivel criar o arquivo de parecer de transferencia.');
        }

        $arquivos = 0;

        foreach ($avaliacoes as $avaliacao) {
            $documento = $this->documentoExportService->gerarPdfAluno(
                (int) $avaliacao->id,
                $aluno,
                $usuario,
                'parecer-transferencia'
            );

            $zip->addFromString($documento['filename'], $documento['contents']);
            $arquivos++;
        }

        $zip->close();

        if ($arquivos === 0) {
            @unlink($zipPath);

            throw new NotFoundHttpException('Nenhum documento foi gerado para o parecer de transferencia.');
        }

        $this->movimentacaoService->transferir($aluno, $usuario);

        return response()
            ->download($zipPath, Str::slug('parecer-transferencia-'.$aluno->nome.'-'.$aluno->cgm).'.zip', [
                'Content-Type' => 'application/zip',
            ])
            ->deleteFileAfterSend(true);
    }
}
