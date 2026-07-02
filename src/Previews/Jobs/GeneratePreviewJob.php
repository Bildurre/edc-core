<?php

namespace Bgm\Core\Previews\Jobs;

use Bgm\Core\Previews\PreviewService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Genera el PNG de (entidad, locale) en cola (DC-05: la concurrencia de
 * Chromes se acota con el número de workers). Único mientras esté pendiente:
 * editar tres veces seguidas no encola tres renders iguales.
 */
class GeneratePreviewJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 120;

    public int $tries = 2;

    public function __construct(
        public string $modelClass,
        public int|string $modelId,
        public string $locale,
    ) {}

    public function uniqueId(): string
    {
        return "{$this->modelClass}:{$this->modelId}:{$this->locale}";
    }

    public function handle(PreviewService $service): void
    {
        $query = $this->modelClass::query();

        // Si la entidad ya no existe (o está en la papelera), no hay nada
        // que renderizar: el job muere en silencio.
        $entity = $query->find($this->modelId);

        if ($entity === null) {
            return;
        }

        $service->generate($entity, $this->locale);
    }
}
