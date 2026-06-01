<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpaBill extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'spa_bills';

    protected $fillable = [
        'spa_application_id', 'bill_type', 'description',
        'amount', 'reference_id', 'due_date', 'status', 'created_by',
    ];

    protected $casts = [
        'amount'   => 'decimal:2',
        'due_date' => 'date',
    ];

    public function application()
    {
        return $this->belongsTo(SpaApplication::class, 'spa_application_id');
    }

    public function payments()
    {
        return $this->hasMany(SpaPayment::class, 'spa_bill_id');
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount_paid');
    }

    public function getBalanceAttribute(): float
    {
        return (float) $this->amount - $this->total_paid;
    }
}
