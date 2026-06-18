<?php

namespace App\Support;

use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

final class ReportPdfGenerator
{
    /**
     * @param  array<string, mixed>  $viewData
     */
    public static function download(string $filename, array $viewData, string $pageLabel): PdfBuilder
    {
        return Pdf::view('reports.pdf', array_merge($viewData, [
            'pageLabel' => $pageLabel,
        ]))
            ->driver('dompdf')
            ->landscape()
            ->format(Format::A4)
            ->margins(8, 10, 8, 10)
            ->name($filename);
    }
}
