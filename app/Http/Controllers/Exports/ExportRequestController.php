<?php

namespace App\Http\Controllers\Exports;

use App\Http\Controllers\Controller;
use App\Models\ExportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportRequestController extends Controller
{
    public function download(ExportRequest $exportRequest): StreamedResponse
    {
        $this->authorize('download', $exportRequest);

        abort_unless($exportRequest->file_disk && $exportRequest->file_path, 404);

        $disk = Storage::disk($exportRequest->file_disk);

        if (! $disk->exists($exportRequest->file_path)) {
            Log::warning('Arquivo de exportação pronto não encontrado no disco.', [
                'export_request_id' => $exportRequest->getKey(),
                'disk' => $exportRequest->file_disk,
                'path' => $exportRequest->file_path,
            ]);

            abort(404);
        }

        return $disk->download(
            $exportRequest->file_path,
            $exportRequest->file_name ?: 'exportacao',
            array_filter(['Content-Type' => $exportRequest->mime])
        );
    }

    public function cancel(ExportRequest $exportRequest): RedirectResponse
    {
        $this->authorize('cancel', $exportRequest);

        $exportRequest->requestCancellation();

        return back()->with('status', 'Cancelamento solicitado.');
    }
}
