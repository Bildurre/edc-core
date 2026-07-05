<?php

namespace Bgm\Core\Pdf\Http\Controllers;

use Bgm\Core\Pdf\Http\Resources\GeneratedPdfResource;
use Bgm\Core\Pdf\Models\GeneratedPdf;
use Bgm\Core\Pdf\Models\PdfCollectionItem;
use Bgm\Core\Pdf\PdfService;
use Bgm\Core\Previews\PreviewRegistry;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Colección temporal "para imprimir" (doc 02): añadir/quitar entidades
 * renderizables con copias y generar su PDF temporal. Funciona para
 * usuarios logueados Y para invitados (como en CDL): sin token Sanctum, la
 * SPA manda un token de invitado (uuid en localStorage) en la cabecera
 * X-Collection-Token.
 */
class PdfCollectionController extends Controller
{
    public function __construct(
        protected PreviewRegistry $previewables,
        protected PdfService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $locale = app()->getLocale();

        $items = $this->scope($request, PdfCollectionItem::query())
            ->orderBy('id')
            ->get()
            ->map(function (PdfCollectionItem $item) use ($locale) {
                $model = $this->previewables->modelFor($item->entity)::query()->find($item->entity_id);

                return [
                    'id' => $item->id,
                    'entity' => $item->entity,
                    'entity_id' => $item->entity_id,
                    'copies' => $item->copies,
                    'label' => $model?->previewLabel($locale),
                    'preview' => $model?->previewUrl($locale, $item->entity),
                    'missing' => $model === null,
                ];
            });

        return response()->json(['data' => $items]);
    }

    /** Añade una entidad (o actualiza sus copias si ya estaba). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity' => ['required', 'string', Rule::in($this->previewables->keys())],
            'id' => ['required', 'integer'],
            'copies' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        // La entidad tiene que existir (y ser visible).
        $this->previewables->modelFor($data['entity'])::query()->findOrFail($data['id']);

        [$user, $token] = $this->owner($request);

        PdfCollectionItem::query()->updateOrCreate(
            [
                'user_id' => $user?->getKey(),
                'guest_token' => $user ? null : $token,
                'entity' => $data['entity'],
                'entity_id' => $data['id'],
            ],
            ['copies' => $data['copies'] ?? 1],
        );

        return $this->index($request)->setStatusCode(201);
    }

    public function destroy(Request $request, int $item): JsonResponse
    {
        $this->scope($request, PdfCollectionItem::query())
            ->whereKey($item)
            ->delete();

        return $this->index($request);
    }

    public function clear(Request $request): JsonResponse
    {
        $this->scope($request, PdfCollectionItem::query())->delete();

        return response()->json(['data' => []]);
    }

    /** Genera el PDF temporal de la colección actual. */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'locale' => ['nullable', 'string'],
            'layout' => ['nullable', 'string'],
        ]);

        [$user, $token] = $this->owner($request);

        $items = $this->scope($request, PdfCollectionItem::query())
            ->get()
            ->map(fn (PdfCollectionItem $item) => [
                'entity' => $item->entity,
                'id' => $item->entity_id,
                'copies' => $item->copies,
            ])
            ->all();

        abort_if($items === [], 422, __('motor::motor.collection_empty'));

        $pdf = $this->service->generateCollection(
            $user,
            $items,
            $data['locale'] ?? app()->getLocale(),
            $data['layout'] ?? null,
            guestToken: $token,
        );

        return (new GeneratedPdfResource($pdf))
            ->additional(['message' => __('motor::motor.pdfs_queued')])
            ->response()
            ->setStatusCode(202);
    }

    /** Estado de un PDF temporal propio (para sondear hasta 'ready'). */
    public function show(Request $request, int $pdf): JsonResponse
    {
        $model = $this->scope($request, GeneratedPdf::query(), 'owner_id')->findOrFail($pdf);

        return (new GeneratedPdfResource($model))->response();
    }

    /**
     * Dueño de la colección: el usuario del token Sanctum si lo hay; si no,
     * el token de invitado de la cabecera (obligatorio y con pinta de uuid).
     * Con usuario Y cabecera de invitado, lo del invitado se ADOPTA: la
     * colección vive en la cuenta, no en el navegador.
     *
     * @return array{0: ?User, 1: ?string}
     */
    protected function owner(Request $request): array
    {
        // Guard por defecto (sesión/tests) con fallback al token Sanctum: la
        // ruta es pública y el guard se resuelve bajo demanda.
        $user = $request->user() ?? $request->user('sanctum');
        if ($user) {
            $this->adoptGuest($user, (string) $request->header('X-Collection-Token', ''));

            return [$user, null];
        }

        $token = (string) $request->header('X-Collection-Token', '');
        abort_unless((bool) preg_match('/^[A-Za-z0-9\-]{16,64}$/', $token), 401, __('motor::motor.collection_token_missing'));

        return [null, $token];
    }

    /**
     * Adopta lo acumulado como invitado al autenticarse: los items pasan a
     * la cuenta (si ya existía el mismo, gana el de MÁS copias) y los PDF
     * temporales del token quedan a nombre del usuario.
     */
    protected function adoptGuest(User $user, string $token): void
    {
        if ($token === '' || ! preg_match('/^[A-Za-z0-9\-]{16,64}$/', $token)) {
            return;
        }

        PdfCollectionItem::query()
            ->where('guest_token', $token)
            ->get()
            ->each(function (PdfCollectionItem $item) use ($user) {
                $mine = PdfCollectionItem::query()
                    ->where('user_id', $user->getKey())
                    ->where('entity', $item->entity)
                    ->where('entity_id', $item->entity_id)
                    ->first();

                if ($mine) {
                    $mine->update(['copies' => max($mine->copies, $item->copies)]);
                    $item->delete();
                } else {
                    $item->update(['user_id' => $user->getKey(), 'guest_token' => null]);
                }
            });

        GeneratedPdf::query()
            ->where('guest_token', $token)
            ->update(['owner_id' => $user->getKey(), 'guest_token' => null]);
    }

    /** Restringe una query a lo del dueño actual (usuario o invitado). */
    protected function scope(Request $request, Builder $query, string $ownerColumn = 'user_id'): Builder
    {
        [$user, $token] = $this->owner($request);

        return $user
            ? $query->where($ownerColumn, $user->getKey())
            : $query->where('guest_token', $token);
    }
}
