<?php

namespace App\Http\Controllers\Exports;

use App\Http\Controllers\Controller;
use App\Models\ExportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportRequestController extends Controller
{
    public function download(ExportRequest $exportRequest): StreamedResponse
    {
        $this->authorize('download', $exportRequest);

        abort_unless($exportRequest->file_disk && $exportRequest->file_path, 404);
        abort_unless(Storage::disk($exportRequest->file_disk)->exists($exportRequest->file_path), 404);

        return Storage::disk($exportRequest->file_disk)->download(
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
