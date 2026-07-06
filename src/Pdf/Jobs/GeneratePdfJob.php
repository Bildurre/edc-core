<?php

namespace Edc\Core\Pdf\Jobs;

use Edc\Core\Pdf\Models\GeneratedPdf;
use Edc\Core\Pdf\PdfCompositionException;
use Edc\Core\Pdf\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Ensambla un GeneratedPdf en cola. Si falla, el registro queda en 'failed'
 * con un error APTO para el admin: solo los PdfCompositionException exponen
 * su mensaje; cualquier otra excepción (SQL, ficheros...) se guarda como
 * error genérico y el detalle completo va a los logs al relanzarse.
 */
class GeneratePdfJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 300;

    public int $tries = 2;

    public function __construct(public int $pdfId) {}

    public function uniqueId(): string
    {
        return "pdf:{$this->pdfId}";
    }

    public function handle(PdfService $service): void
    {
        $pdf = GeneratedPdf::query()->find($this->pdfId);

        if ($pdf === null) {
            return;
        }

        try {
            $service->compose($pdf);
        } catch (Throwable $e) {
            $pdf->update([
                'status' => GeneratedPdf::STATUS_FAILED,
                'error' => $e instanceof PdfCompositionException
                    ? $e->getMessage()
                    : __('motor::motor.pdf_error_internal'),
            ]);

            throw $e;
        }
    }
}
