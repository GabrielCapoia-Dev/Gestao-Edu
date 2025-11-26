<?php

namespace App\Http\Controllers;

use App\Models\AlunoLaudo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;

class LaudoArquivoController extends Controller
{
    protected function laudosDisk(): FilesystemAdapter
    {
        return Storage::disk('laudos');
    }

    public function show(AlunoLaudo $alunoLaudo)
    {
        // policy: view
        $this->authorize('view', $alunoLaudo);

        $disk = $this->laudosDisk();
        $path = $alunoLaudo->anexo_laudo_path;

        // Se não tiver caminho ou não for string válida → 404
        if (blank($path) || ! is_string($path)) {
            return abort(404);
        }

        if (! $disk->exists($path)) {
            return abort(404);
        }

        return $disk->response($path);
    }

    public function download(AlunoLaudo $alunoLaudo)
    {
        // policy: download
        $this->authorize('download', $alunoLaudo);

        $disk = $this->laudosDisk();
        $path = $alunoLaudo->anexo_laudo_path;

        // Mesmo tratamento aqui
        if (blank($path) || ! is_string($path)) {
            return abort(404);
        }

        if (! $disk->exists($path)) {
            return abort(404);
        }

        $filename = $this->makeFilename($alunoLaudo);

        return $disk->download($path, $filename);
    }

    protected function makeFilename(AlunoLaudo $alunoLaudo): string
    {
        $aluno = $alunoLaudo->aluno;
        $laudo = $alunoLaudo->laudo;

        $cgm  = $aluno->cgm;
        $nome = str($laudo->nome)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-');

        return "{$cgm}-{$nome}.pdf";
    }
}
