<?php

namespace Bgm\Core\Backup\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;

/**
 * Gestor de copias de seguridad (doc 06): listar, crear, descargar y borrar
 * los zips que genera spatie/laravel-backup. Protegido por manage-web.
 * DC-16: crear es síncrono (BBDD grandes irían en cola; se documenta).
 */
class BackupController extends Controller
{
    public function index()
    {
        return response()->json(['data' => $this->list()]);
    }

    public function store()
    {
        // --disable-notifications: el propio admin ya informa del resultado.
        $exit = Artisan::call('backup:run', ['--disable-notifications' => true]);

        abort_if($exit !== 0, 500, trim(Artisan::output()) ?: 'backup:run failed');

        return response()->json(['data' => $this->list()], 201);
    }

    public function download(string $file)
    {
        $backup = $this->find($file);

        return Storage::disk($this->disk())->download($backup->path(), $file);
    }

    public function destroy(string $file)
    {
        $this->find($file)->delete();

        return response()->noContent();
    }

    /** Copias existentes, de más nueva a más vieja. */
    protected function list(): array
    {
        return $this->destination()->backups()
            ->map(fn (Backup $backup) => [
                'file' => basename($backup->path()),
                'date' => $backup->date()->toIso8601String(),
                'size' => $backup->sizeInBytes(),
            ])
            ->values()
            ->all();
    }

    protected function find(string $file): Backup
    {
        $backup = $this->destination()->backups()
            ->first(fn (Backup $backup) => basename($backup->path()) === $file);

        abort_unless($backup, 404);

        return $backup;
    }

    protected function destination(): BackupDestination
    {
        return BackupDestination::create($this->disk(), config('backup.backup.name'));
    }

    protected function disk(): string
    {
        return config('motor.backup.disk', 'backups');
    }
}
