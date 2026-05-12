<?php

namespace App\Contracts\Exports;

use App\Models\ExportRequest;
use App\Services\Exports\ExportFileResult;

interface ExportHandler
{
    public function handle(ExportRequest $exportRequest): ExportFileResult;
}
