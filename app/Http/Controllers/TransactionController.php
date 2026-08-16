<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use App\Support\SecurityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TransactionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'currency' => ['required', 'string', 'in:NGN'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        // Never trust sender_id from the request.
        // The sender must be the authenticated user from the API token.
        $sender = $request->user();
        $recipient = User::find($validated['recipient_id']);

        if ($recipient === null) {
            return response()->json([
                'message' => 'Recipient not found',
            ], 404);
        }

        $transaction = Transaction::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'status' => 'completed',
            'description' => $validated['description'] ?? null,
        ]);

        SecurityLog::info('transaction_created', $request, 201, 'transaction', $transaction->id, [
            'transaction_id' => $transaction->id,
            'recipient_id' => $recipient->id,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'transaction_status' => $transaction->status,
        ]);

        $largeTransactionThreshold = (float) config('finbank.large_transaction_threshold');

        if ((float) $transaction->amount > $largeTransactionThreshold) {
            SecurityLog::warning('large_transaction_detected', $request, 201, 'transaction', $transaction->id, [
                'transaction_id' => $transaction->id,
                'recipient_id' => $recipient->id,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'threshold' => $largeTransactionThreshold,
            ]);
        }

        return response()->json([
            'message' => 'Transaction created',
            'transaction' => $transaction,
        ], 201);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $transactions = Transaction::with(['sender', 'recipient'])
            ->where('sender_id', $user->id)
            ->orWhere('recipient_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'transactions' => $transactions,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $transaction = Transaction::with(['sender', 'recipient'])->find($id);

        if ($transaction === null) {
            return response()->json([
                'message' => 'Transaction not found',
            ], 404);
        }

        if (Gate::allows('view', $transaction) === false) {
            SecurityLog::warning('idor_attempt', $request, 403, 'transaction', $transaction->id, [
                'transaction_id' => $transaction->id,
                'sender_id' => $transaction->sender_id,
                'recipient_id' => $transaction->recipient_id,
                'reason' => 'transaction_owner_check_failed',
            ]);

            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        return response()->json([
            'transaction' => $transaction,
        ]);
    }
}
