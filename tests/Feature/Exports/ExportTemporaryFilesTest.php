<?php

namespace Tests\Feature\Exports;

use App\Models\ExportRequest;
use App\Services\Exports\ExportTemporaryFiles;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportTemporaryFilesTest extends TestCase
{
    public function test_cada_tentativa_usa_temporario_isolado_e_limpa_apenas_a_propria_tentativa(): void
    {
        Storage::fake('local');

        $request = new ExportRequest();
        $request->setAttribute('id', 'retry-test');
        $temporaryFiles = app(ExportTemporaryFiles::class);

        $firstAttempt = $temporaryFiles->begin($request);
        $firstJson = $temporaryFiles->path($firstAttempt, 'part-0001.jsonl');
        $temporaryFiles->writeJsonLines($firstJson, [['id' => 1]]);

        $secondAttempt = $temporaryFiles->begin($request);
        $secondJson = $temporaryFiles->path($secondAttempt, 'part-0001.jsonl');
        $temporaryFiles->writeJsonLines($secondJson, [['id' => 1]]);

        $this->assertNotSame($firstAttempt, $secondAttempt);
        $this->assertCount(1, $temporaryFiles->readJsonLines($secondJson));

        $temporaryFiles->cleanup($secondAttempt);

        $this->assertTrue(Storage::disk('local')->exists($firstAttempt.'/part-0001.jsonl'));
        $this->assertFalse(Storage::disk('local')->exists($secondAttempt.'/part-0001.jsonl'));

        $temporaryFiles->cleanup($firstAttempt);
    }

    public function test_cleanup_remove_o_pai_vazio_mas_preserva_pai_com_outra_tentativa(): void
    {
        Storage::fake('local');

        $request = new ExportRequest();
        $request->setAttribute('id', 'parent-cleanup-test');
        $temporaryFiles = app(ExportTemporaryFiles::class);

        $onlyAttempt = $temporaryFiles->begin($request);
        $temporaryFiles->writeJsonLines($temporaryFiles->path($onlyAttempt, 'part.jsonl'), [['id' => 1]]);
        $temporaryFiles->cleanup($onlyAttempt);

        $this->assertSame([], Storage::disk('local')->directories('exports-tmp/parent-cleanup-test'));

        $firstAttempt = $temporaryFiles->begin($request);
        $secondAttempt = $temporaryFiles->begin($request);
        $temporaryFiles->writeJsonLines($temporaryFiles->path($firstAttempt, 'part.jsonl'), [['id' => 1]]);
        $temporaryFiles->writeJsonLines($temporaryFiles->path($secondAttempt, 'part.jsonl'), [['id' => 2]]);

        $temporaryFiles->cleanup($firstAttempt);

        $this->assertTrue(Storage::disk('local')->exists($secondAttempt.'/part.jsonl'));
        $this->assertSame([$secondAttempt], Storage::disk('local')->directories('exports-tmp/parent-cleanup-test'));

        $temporaryFiles->cleanup($secondAttempt);
    }
}
