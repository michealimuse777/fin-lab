<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\SecurityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('finbank-api-token')->plainTextToken;

        SecurityLog::info('account_registered', $request, 201, 'account', $user->id, [
            'email' => $user->email,
        ], $user);

        return response()->json([
            'message' => 'User registered',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            SecurityLog::warning('login_failed', $request, 401, 'account', null, [
                'email' => $validated['email'],
                'result' => 'bad_credentials',
            ]);

            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        if ($user->is_active === false) {
            SecurityLog::warning('login_failed', $request, 403, 'account', $user->id, [
                'email' => $validated['email'],
                'result' => 'account_inactive',
            ], $user);

            return response()->json([
                'message' => 'Account is inactive',
            ], 403);
        }

        $token = $user->createToken('finbank-api-token')->plainTextToken;

        SecurityLog::info('login_success', $request, 200, 'account', $user->id, [
            'email' => $user->email,
        ], $user);

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $accessToken = $user->currentAccessToken();

        if ($accessToken !== null && method_exists($accessToken, 'delete')) {
            $accessToken->delete();
        }

        SecurityLog::info('logout', $request, 200, 'account', $user->id);

        return response()->json([
            'message' => 'Logged out',
        ]);
    }
}
