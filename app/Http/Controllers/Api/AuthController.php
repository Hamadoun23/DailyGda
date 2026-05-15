<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string'],
        ]);

        $username = Str::lower(trim($credentials['username']));

        /** @var User|null $user */
        $user = User::query()->where('username', $username)->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            ActivityLogger::loginFailed($request, $username);

            throw ValidationException::withMessages([
                'username' => ['Identifiants invalides.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'username' => ['Compte désactivé.'],
            ]);
        }

        $user->tokens()->delete();
        $token = $user->createToken('gda-api')->plainTextToken;

        ActivityLogger::login($user, $request, viaApi: true);

        return response()->json([
            'user' => $this->userPayload($user),
            'token' => $token,
        ]);
    }

    public function whoami(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        return response()->json([
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request)
    {
        if ($user = $request->user()) {
            ActivityLogger::logout($user, $request, viaApi: true);
        }

        $token = $request->bearerToken();
        if ($token) {
            $pat = PersonalAccessToken::findToken($token);
            $pat?->delete();
        }

        return response()->json(['message' => 'Déconnecté']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'role' => $user->role,
            'is_admin' => $user->isAdmin(),
            'is_partner' => $user->isPartner(),
            'avatar_initials' => $user->avatar_initials,
            'current_progress' => $user->current_progress,
        ];
    }
}
