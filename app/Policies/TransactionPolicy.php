<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function view(User $user, Transaction $transaction): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($transaction->sender_id === $user->id) {
            return true;
        }

        if ($transaction->recipient_id === $user->id) {
            return true;
        }

        return false;
    }
}
