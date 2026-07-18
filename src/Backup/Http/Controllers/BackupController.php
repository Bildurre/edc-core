<?php

namespace Edc\Core\Backup\Http\Controllers;

use Edc\Core\Backup\BackupRestorer;
use Edc\Core\Backup\BackupSettings;
use Edc\Core\Backup\Jobs\RunBackupJob;
use Edc\Core\Backup\MotorBackup;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;

/**
 * Gestor de copias de seguridad (doc 06): listar, crear, subir, restaurar,
 * descargar y borrar los zips que genera spatie/laravel-backup, y configurar
 * la copia AUTOMÁTICA (activada, frecuencia, hora, retención) que programa
 * el motor. Protegido por manage-web. DC-16: crear va SIEMPRE en cola (la
 * petición no espera al zip; la vista sondea el listado con `pending`).
 */
class BackupController extends Controller
{
    public function __construct(protected BackupSettings $settings) {}

    public function index()
    {
        return response()->json([
            'data' => $this->list(),
            'schedule' => $this->settings->get(),
            // Hay una copia manual en curso: la vista sondea hasta que acabe.
            'pending' => Cache::has(MotorBackup::PENDING_CACHE_KEY),
        ]);
    }

    public function store()
    {
        // SIEMPRE en cola (DC-16): la petición vuelve al momento y el worker
        // crea el zip. Con la cola 'sync' (instalaciones pequeñas) se difiere
        // a después de la respuesta — mismo patrón (y mismo guard de tests)
        // que HasPreviewImage::regeneratePreviews(): en la suite el diferido
        // apuntaría a terminating callbacks que no corren y esquivaría
        // Queue::fake(); con dispatch() la cola sync de tests ejecuta inline.
        $filename = 'manual-'.now()->format('Y-m-d-H-i-s').'.zip';

        Cache::put(MotorBackup::PENDING_CACHE_KEY, $filename, now()->addMinutes(15));

        $syncQueue = config('queue.default') === 'sync' && ! app()->runningUnitTests();
        $syncQueue
            ? RunBackupJob::dispatchAfterResponse($filename)
            : RunBackupJob::dispatch($filename);

        return response()->json([
            'data' => $this->list(),
            'queued' => true,
            'pending' => Cache::has(MotorBackup::PENDING_CACHE_KEY),
        ], 202);
    }

    /**
     * Sube una copia externa (zip de spatie/laravel-backup o equivalente):
     * se valida que sea un zip con una BBDD restaurable dentro (dump SQL o
     * fichero SQLite) y se guarda en el destino con el prefijo `upload-`
     * (el listado la marca con origen "subida").
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => [
                'required', 'file', 'extensions:zip',
                'max:'.((int) config('motor.backup.upload_max_mb', 500)) * 1024,
            ],
        ]);

        $upload = $request->file('file');

        abort_unless(
            app(BackupRestorer::class)->containsDatabase($upload->getRealPath()),
            422,
            __('motor::motor.backup_upload_invalid'),
        );

        $base = Str::slug(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'copia';
        $name = 'upload-'.$base.'-'.now()->format('Y-m-d-H-i-s').'.zip';

        Storage::disk($this->disk())->putFileAs(config('backup.backup.name'), $upload, $name);

        return response()->json(['data' => $this->list()], 201);
    }

    /**
     * RESTAURA una copia: importa la BBDD del zip MACHACANDO la actual (la
     * SPA pide doble confirmación). Solo la base de datos: los archivos de
     * storage que pueda traer el zip no se tocan (ver BackupRestorer).
     */
    public function restore(string $file)
    {
        $backup = $this->find($file);

        // El zip puede vivir en un disco remoto (S3): cópialo a un temporal
        // local para poder abrirlo con ZipArchive.
        $temp = tempnam(sys_get_temp_dir(), 'motor-restore-');
        $source = Storage::disk($this->disk())->readStream($backup->path());
        $target = fopen($temp, 'wb');
        stream_copy_to_stream($source, $target);
        fclose($target);
        if (is_resource($source)) {
            fclose($source);
        }

        try {
            $restored = app(BackupRestorer::class)->restore($temp);
        } finally {
            @unlink($temp);
        }

        return response()->json(['restored' => $restored]);
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
                'origin' => $this->origin(basename($backup->path())),
            ])
            ->values()
            ->all();
    }

    /**
     * Origen de una copia, derivado del prefijo del nombre (sin estado
     * aparte): `manual-` las crea el botón del admin, `upload-` las subidas;
     * el resto (nombre-fecha de spatie) son de la copia automática.
     */
    protected function origin(string $file): string
    {
        return match (true) {
            str_starts_with($file, 'manual-') => 'manual',
            str_starts_with($file, 'upload-') => 'upload',
            default => 'auto',
        };
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
