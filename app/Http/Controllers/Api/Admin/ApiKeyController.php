<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Support\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function index(): JsonResponse
    {
        $keys = ApiKey::with('creator:id,name')->latest()->get()->map(fn (ApiKey $key) => [
            'id'           => $key->id,
            'name'         => $key->name,
            'prefix'       => $key->prefix,
            'is_active'    => $key->is_active,
            'created_by'   => $key->creator?->name,
            'last_used_at' => $key->last_used_at,
            'revoked_at'   => $key->revoked_at,
            'created_at'   => $key->created_at,
        ]);

        return response()->json(['data' => $keys]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        ['key' => $apiKey, 'plainText' => $plainText] = ApiKey::generate($data['name'], $request->user()?->id);

        AuditLog::record('Clé API créée', ['api_key_id' => $apiKey->id, 'name' => $apiKey->name]);

        return response()->json([
            'message' => 'Clé API créée. Copiez-la maintenant : elle ne sera plus jamais affichée.',
            'key'     => $plainText,
            'data'    => [
                'id'     => $apiKey->id,
                'name'   => $apiKey->name,
                'prefix' => $apiKey->prefix,
            ],
        ], 201);
    }

    public function destroy(Request $request, ApiKey $apiKey): JsonResponse
    {
        $apiKey->update([
            'is_active'  => false,
            'revoked_at' => now(),
            'revoked_by' => $request->user()?->id,
        ]);

        AuditLog::record('Clé API révoquée', ['api_key_id' => $apiKey->id, 'name' => $apiKey->name]);

        return response()->json(['message' => 'Clé API révoquée.']);
    }
}
