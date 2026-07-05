<?php

namespace Bgm\Core\Pdf\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * PDF generado (doc 02). Polimórfico: la entidad dueña es cualquier modelo
 * del juego (o ninguna: exports globales / colecciones temporales).
 */
class GeneratedPdf extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $table = 'generated_pdfs';

    protected $fillable = [
        'type', 'source_type', 'source_id', 'owner_id', 'guest_token', 'locale',
        'layout', 'path', 'filename', 'status', 'error', 'payload',
        'is_permanent', 'expires_at', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_permanent' => 'boolean',
            'expires_at' => 'datetime',
            'generated_at' => 'datetime',
        ];
    }

    /** Entidad dueña (Faction, Deck, Page... del juego), si la hay. */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'owner_id');
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY && $this->path !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** URL pública del PDF (null si aún no está generado). */
    public function url(): ?string
    {
        return $this->isReady()
            ? Storage::disk(config('motor.pdf.disk'))->url($this->path)
            : null;
    }

    public function deleteFile(): void
    {
        if ($this->path) {
            Storage::disk(config('motor.pdf.disk'))->delete($this->path);
        }
    }
}
