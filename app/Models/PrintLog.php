<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintLog extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'print_logs';

    protected $fillable = [
        'reference_number',
        'document_type',
        'print_type',
        'status',
        'user_id'
    ];

    /**
     * Get the user who printed the document.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
