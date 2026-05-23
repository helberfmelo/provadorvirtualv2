<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['E-mail ou senha invalidos.'],
            ]);
        }

        $token = $user->createToken('provadorvirtual-spa')->plainTextToken;
        $merchant = $user->merchants()->first();

        app(AuditLogger::class)->log($request, $merchant, 'auth.login', 'auth', 'info', [
            'email' => $user->email,
        ], actor: $user);

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $merchant = $request->user()?->merchants()->first();

        app(AuditLogger::class)->log($request, $merchant, 'auth.logout', 'auth', 'info');

        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Sessao encerrada com sucesso.',
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'merchants' => $user->merchants()
                ->select('merchants.id', 'merchants.name', 'merchants.slug', 'merchants.billing_status')
                ->get(),
        ]);
    }
}
