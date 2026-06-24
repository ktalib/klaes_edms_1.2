<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalSearchOnlinePayment extends Model
{
    protected $fillable = [
        'reference',
        'user_id',
        'email',
        'file_number',
        'search_params',
        'amount',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'search_params' => 'array',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
