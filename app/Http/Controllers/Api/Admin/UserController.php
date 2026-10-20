<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminUserResource as UserResource;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        return UserResource::collection(User::whereIn('role', ['admin', 'redacteur'])->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::defaults()],
            'role'     => ['required', Rule::in(['admin', 'redacteur'])],
        ]);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'role'      => $data['role'],
            'is_active' => true,
        ]);

        AuditLog::record('Compte créé', ['nouveau_user_id' => $user->id, 'role' => $user->role]);

        return response()->json([
            'message' => 'Compte créé avec succès.',
            'user'    => new UserResource($user),
        ], 201);
    }

    public function show(User $utilisateur)
    {
        return new UserResource($utilisateur);
    }

    public function update(Request $request, User $utilisateur): JsonResponse
    {
        $data = $request->validate([
            'name'  => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($utilisateur->id)],
            'role'  => ['sometimes', Rule::in(['admin', 'redacteur'])],
        ]);

        $utilisateur->update($data);

        AuditLog::record('Compte modifié', ['user_id' => $utilisateur->id]);

        return response()->json([
            'message' => 'Compte mis à jour.',
            'user'    => new UserResource($utilisateur->fresh()),
        ]);
    }

    public function suspendre(Request $request, User $utilisateur): JsonResponse
    {
        if ($request->user()->id === $utilisateur->id) {
            return response()->json(['message' => 'Vous ne pouvez pas suspendre votre propre compte.'], 422);
        }

        $utilisateur->update(['is_active' => false]);
        $utilisateur->tokens()->delete();

        AuditLog::record('Compte suspendu', ['user_id' => $utilisateur->id]);

        return response()->json(['message' => 'Compte suspendu.', 'user' => new UserResource($utilisateur->fresh())]);
    }

    public function reactiver(User $utilisateur): JsonResponse
    {
        $utilisateur->update(['is_active' => true]);

        AuditLog::record('Compte réactivé', ['user_id' => $utilisateur->id]);

        return response()->json(['message' => 'Compte réactivé.', 'user' => new UserResource($utilisateur->fresh())]);
    }
}
