<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\SecurityLog;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function profile(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ]);

        $blockedFields = [];

        foreach (['role', 'is_active'] as $field) {
            if ($request->has($field)) {
                $blockedFields[] = $field;
            }
        }

        if (count($blockedFields) > 0) {
            SecurityLog::warning('mass_assignment_attempt', $request, 200, 'account', $user->id, [
                'blocked_fields' => $blockedFields,
                'allowed_fields' => ['name', 'email'],
            ]);
        }

        // Only these validated fields may be updated from the profile endpoint.
        // role and is_active are ignored even if the client sends them.
        $user->update($validated);

        SecurityLog::info('profile_updated', $request, 200, 'account', $user->id, [
            'changed_fields' => array_keys($validated),
        ]);

        return response()->json([
            'message' => 'Profile updated',
            'user' => $user,
        ]);
    }

    public function showUser(Request $request, int $id)
    {
        $authenticatedUser = $request->user();
        $targetUser = User::find($id);

        if ($targetUser === null) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        if ($authenticatedUser->role !== 'admin' && $authenticatedUser->id !== $targetUser->id) {
            SecurityLog::warning('authorization_denied', $request, 403, 'account', $targetUser->id, [
                'reason' => 'user_profile_access_denied',
            ]);

            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        return response()->json([
            'user' => $targetUser,
        ]);
    }
}
