<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegalSearchToken extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'legal_search_tokens';

    protected $fillable = [
        'token',
        'file_number',
        'applicant_name',
        'client_name',
        'property_location',
        'client_address',
        'payment_reason',
        'amount_paid',
        'receipt_number',
        'date_paid',
        'is_used',
        'used_at',
        'created_by'
    ];

    protected $casts = [
        'date_paid' => 'date',
        'is_used' => 'boolean',
        'used_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
