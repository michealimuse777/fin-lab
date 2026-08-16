<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    /**
     * These are the fields Laravel may fill through Transaction::create().
     *
     * The sender_id is controlled by the server from $request->user().
     * A client must never be trusted to choose who is sending the money.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sender_id',
        'recipient_id',
        'amount',
        'currency',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
