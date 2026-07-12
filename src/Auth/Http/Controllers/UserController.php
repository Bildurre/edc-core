<?php

namespace Edc\Core\Auth\Http\Controllers;

use Edc\Core\Auth\Http\Resources\UserResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Gestión de usuarios del admin (doc 05): lo típico y básico — listar con
 * búsqueda, crear (con rol), editar (rol y contraseña opcionales) y borrar.
 * Guardas: nadie se borra a sí mismo ni se cambia su propio rol (evita
 * quedarse fuera del panel). Protegido por el permiso manage-users.
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = $this->model()::query()
            ->with('roles')
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->tap(function ($query) use ($request) {
                // Contrato de `sort` de los index: alfabético (defecto: los
                // usuarios se listan por nombre) o por id para latest/oldest.
                match ($request->string('sort')->toString()) {
                    'name_desc' => $query->orderByDesc('name'),
                    'latest' => $query->orderByDesc('id'),
                    'oldest' => $query->orderBy('id'),
                    default => $query->orderBy('name'),
                };
            })
            ->paginate(20);

        return UserResource::collection($users);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $user = $this->model()::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $user->syncRoles([$data['role']]);

        return (new UserResource($user->load('roles')))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $id)
    {
        $user = $this->model()::query()->findOrFail($id);
        $data = $this->validateData($request, $user->id);

        $user->fill(['name' => $data['name'], 'email' => $data['email']]);
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        // El propio rol no se toca: evita que un admin se degrade sin querer.
        if ($user->id !== $request->user()->id) {
            $user->syncRoles([$data['role']]);
        }

        return new UserResource($user->load('roles'));
    }

    /** Acción rápida: marca o desmarca el email como verificado. */
    public function toggleVerified(int $id)
    {
        $user = $this->model()::query()->findOrFail($id);
        $user->email_verified_at = $user->email_verified_at ? null : now();
        $user->save();

        return new UserResource($user->load('roles'));
    }

    public function destroy(Request $request, int $id)
    {
        abort_if($id === $request->user()->id, 422, __('motor::motor.users_cannot_delete_self'));

        $this->model()::query()->findOrFail($id)->delete();

        return response()->noContent();
    }

    protected function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($ignoreId),
            ],
            // En edición la contraseña es opcional (vacía = no cambiar).
            'password' => [$ignoreId ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['required', Rule::in(config('motor.auth.roles', []))],
        ]);
    }

    /** @return class-string<Model> */
    protected function model(): string
    {
        return config('auth.providers.users.model');
    }
}
