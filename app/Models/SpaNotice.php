<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpaNotice extends Model
{
    /** A second serve notice falls due this many days after the first serve. */
    public const SECOND_SERVE_AFTER_DAYS = 14;

    protected $connection = 'sqlsrv';
    protected $table      = 'spa_notices';

    protected $fillable = [
        'spa_application_id', 'file_number', 'notice_type',
        'recipient_name', 'phone', 'served_by',
        'served_date', 'scheduled_date',
        'sms_sent', 'sms_sent_at', 'status', 'created_by',
    ];

    protected $casts = [
        'sms_sent'       => 'boolean',
        'sms_sent_at'    => 'datetime',
        'served_date'    => 'date',
        'scheduled_date' => 'date',
    ];

    public function application()
    {
        return $this->belongsTo(SpaApplication::class, 'spa_application_id');
    }

    public function servedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'served_by');
    }
}
