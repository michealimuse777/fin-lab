<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use App\Support\SecurityLog;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function users()
    {
        $users = User::orderBy('id')->get();

        return response()->json([
            'users' => $users,
        ]);
    }

    public function transactions()
    {
        $transactions = Transaction::with(['sender', 'recipient'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'transactions' => $transactions,
        ]);
    }

    public function changeRole(Request $request, int $id)
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:user,admin'],
        ]);

        $actor = $request->user();
        $targetUser = User::find($id);

        if ($targetUser === null) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $oldRole = $targetUser->role;
        $newRole = $validated['role'];

        $targetUser->role = $newRole;
        $targetUser->save();

        SecurityLog::warning('role_changed', $request, 200, 'account', $targetUser->id, [
            'old_role' => $oldRole,
            'new_role' => $newRole,
        ], $actor);

        return response()->json([
            'message' => 'Role updated',
            'user' => $targetUser,
        ]);
    }

    public function changeStatus(Request $request, int $id)
    {
        $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $actor = $request->user();
        $targetUser = User::find($id);

        if ($targetUser === null) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $oldStatus = $targetUser->is_active;
        $newStatus = $request->boolean('is_active');

        $targetUser->is_active = $newStatus;
        $targetUser->save();

        SecurityLog::warning('account_status_changed', $request, 200, 'account', $targetUser->id, [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ], $actor);

        return response()->json([
            'message' => 'Account status updated',
            'user' => $targetUser,
        ]);
    }
}
