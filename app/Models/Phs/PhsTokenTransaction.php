<?php

namespace App\Models\Phs;

use Illuminate\Database\Eloquent\Model;

class PhsTokenTransaction extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'phs_token_transactions';

    protected $fillable = [
        'phs_institution_id',
        'phs_member_id',
        'type',
        'tokens',
        'balance_after',
        'package_name',
        'amount',
        'payment_method',
        'status',
        'reference_no',
        'notes',
        'approved_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function institution()
    {
        return $this->belongsTo(PhsInstitution::class, 'phs_institution_id');
    }

    public function member()
    {
        return $this->belongsTo(PhsMember::class, 'phs_member_id');
    }
}
