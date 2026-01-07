<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AlunoLaudo extends Pivot
{
    protected $table = 'aluno_laudo';

    public $incrementing = true;

    protected $fillable = [
        'aluno_id',
        'laudo_id',
        'anexo_laudo_path',
    ];

    protected static function booted(): void
    {
        static::creating(function (AlunoLaudo $pivot) {
            if (! $pivot->anexo_laudo_path) {
                return;
            }

            $pivot->loadMissing('aluno', 'laudo');

            $aluno = $pivot->aluno;
            $laudo = $pivot->laudo;

            if (! $aluno || ! $laudo) {
                return;
            }

            $oldPath = $pivot->anexo_laudo_path; 

            $extension = pathinfo($oldPath, PATHINFO_EXTENSION) ?: 'pdf';

            $novoNomeArquivo = sprintf(
                '%s-%s.%s',
                $aluno->cgm,
                Str::slug($laudo->nome, '_'),
                $extension
            );

            $novoPath = 'laudos/' . $novoNomeArquivo;

            if ($oldPath !== $novoPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->move($oldPath, $novoPath);
                $pivot->anexo_laudo_path = $novoPath;
            }
        });
    }

    public function aluno()
    {
        return $this->belongsTo(Aluno::class, 'aluno_id');
    }

    public function laudo()
    {
        return $this->belongsTo(Laudo::class, 'laudo_id');
    }
}
