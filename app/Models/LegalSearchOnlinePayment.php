<?php

namespace App\Models;

use App\Models\OnlineLegalSearch\OnlineLsUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalSearchOnlinePayment extends Model
{
    // The Online Legal Search portal lives on the sqlsrv connection
    // (alongside online_ls_users and online_ls_search_logs).
    protected $connection = 'sqlsrv';

    protected $fillable = [
        'reference',
        'tracking_id',
        'user_id',
        'online_ls_user_id',
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

    public function onlineLsUser(): BelongsTo
    {
        return $this->belongsTo(OnlineLsUser::class, 'online_ls_user_id');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
