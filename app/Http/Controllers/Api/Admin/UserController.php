<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => User::query()->orderBy('name')->get()->map(fn (User $user) => $this->serialize($user)),
            'roles' => collect(config('rbac.roles'))->map(fn (array $role, string $key) => [
                'key' => $key,
                'name' => $role['name'],
                'description' => $role['description'],
                'permissions' => $role['permissions'],
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', Rule::in(array_keys(config('rbac.roles')))],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => trim($data['name']),
                'email' => strtolower($data['email']),
                'password' => Hash::make($data['password']),
            ]);
            $user->assignRoles($data['roles']);

            return $user;
        });

        return response()->json(['data' => $this->serialize($user)], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'roles' => ['sometimes', 'required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', Rule::in(array_keys(config('rbac.roles')))],
        ]);

        $rolesChanged = false;
        if (array_key_exists('roles', $data)) {
            $currentRoles = $user->roleNames();
            $nextRoles = array_values(array_unique($data['roles']));
            sort($currentRoles);
            sort($nextRoles);
            $rolesChanged = $currentRoles !== $nextRoles;
        }

        if ($rolesChanged && $request->user()->is($user)) {
            return response()->json(['message' => 'Vous ne pouvez pas modifier vos propres rôles.'], 422);
        }

        if ($rolesChanged && $user->hasRole('admin') && ! in_array('admin', $data['roles'], true)) {
            $otherAdmins = User::query()->whereKeyNot($user->id)->get()->contains(fn (User $candidate) => $candidate->hasRole('admin'));
            if (! $otherAdmins) {
                return response()->json(['message' => 'Le dernier administrateur ne peut pas perdre son rôle.'], 422);
            }
        }

        DB::transaction(function () use ($data, $user, $rolesChanged) {
            $user->fill(collect($data)->except('roles')->all())->save();
            if ($rolesChanged) {
                $user->assignRoles($data['roles']);
                $user->tokens()->delete();
            }
        });

        return response()->json(['data' => $this->serialize($user->fresh())]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->is($user)) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 422);
        }

        if ($user->hasRole('admin')) {
            $otherAdmins = User::query()->whereKeyNot($user->id)->get()->contains(fn (User $candidate) => $candidate->hasRole('admin'));
            if (! $otherAdmins) {
                return response()->json(['message' => 'Le dernier administrateur ne peut pas être supprimé.'], 422);
            }
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Compte supprimé.']);
    }

    private function serialize(User $user): array
    {
        $roles = config('rbac.roles');

        return [
            ...$user->only(['id', 'name', 'email', 'created_at']),
            'roles' => $user->roleNames(),
            'permissions' => $user->permissions(),
            'role_names' => collect($user->roleNames())->map(fn (string $role) => $roles[$role]['name'] ?? $role)->all(),
        ];
    }
}
