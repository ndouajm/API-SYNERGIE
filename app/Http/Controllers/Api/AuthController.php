<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', strtolower($credentials['email']))->first();
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken($credentials['device_name'] ?? 'bureau-web')->plainTextToken,
            'user' => $this->serializeUser($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {

        return response()->json($this->serializeUser($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Session fermée.']);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $rules = [
            'password' => ['required', 'string', 'min:12'],
        ];

        if ($request->filled('current_password')) {
            $rules['current_password'] = ['required', 'string', 'current_password'];
        }

        $validated = $request->validate($rules, [
            'current_password.current_password' => 'Le mot de passe actuel est incorrect.',
            'password.min' => 'Le nouveau mot de passe doit comporter au moins 12 caractères.',
            'password.required' => 'Veuillez saisir un nouveau mot de passe.',
        ]);

        $user = $request->user();
        $user->password = Hash::make($validated['password']);
        $user->save();

        $currentToken = $user->currentAccessToken();
        if ($currentToken) {
            $user->tokens()->where('id', '!=', $currentToken->id)->delete();
        }

        return response()->json(['message' => 'Mot de passe mis à jour avec succès.']);
    }

    private function serializeUser(User $user): array
    {
        return [
            ...$user->only(['id', 'name', 'email']),
            'roles' => $user->roleNames(),
            'permissions' => $user->permissions(),
        ];
    }
}
