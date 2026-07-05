<?php

namespace Bgm\Core\Backup\Http\Controllers;

use Bgm\Core\Backup\BackupSettings;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;

/**
 * Gestor de copias de seguridad (doc 06): listar, crear, descargar y borrar
 * los zips que genera spatie/laravel-backup, y configurar la copia
 * AUTOMÁTICA (activada, frecuencia, hora, retención) que programa el motor.
 * Protegido por manage-web. DC-16: crear es síncrono (BBDD grandes irían en
 * cola; se documenta).
 */
class BackupController extends Controller
{
    public function __construct(protected BackupSettings $settings) {}

    public function index()
    {
        return response()->json(['data' => $this->list(), 'schedule' => $this->settings->get()]);
    }

    public function store()
    {
        // --disable-notifications: el propio admin ya informa del resultado.
        $exit = Artisan::call('backup:run', ['--disable-notifications' => true]);

        abort_if($exit !== 0, 500, trim(Artisan::output()) ?: 'backup:run failed');

        return response()->json(['data' => $this->list()], 201);
    }

    /** Configura la copia automática (la aplica el scheduler del motor). */
    public function updateSchedule(Request $request)
    {
        $data = $request->validate([
            'auto' => ['required', 'boolean'],
            'frequency' => ['required', Rule::in(['daily', 'weekly'])],
            'time' => ['required', 'date_format:H:i'],
            'weekday' => ['required', 'integer', 'min:1', 'max:7'],
            'keep_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        return response()->json(['schedule' => $this->settings->update($data)]);
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
