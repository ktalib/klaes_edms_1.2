<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpaPayment extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'spa_payments';

    protected $fillable = [
        'spa_bill_id', 'spa_application_id',
        'amount_paid', 'receipt_number', 'payment_date',
        'payment_method', 'recorded_by',
    ];

    protected $casts = [
        'amount_paid'  => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function bill()
    {
        return $this->belongsTo(SpaBill::class, 'spa_bill_id');
    }

    public function application()
    {
        return $this->belongsTo(SpaApplication::class, 'spa_application_id');
    }
}
