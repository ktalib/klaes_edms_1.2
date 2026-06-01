<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SpaMemo extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'spa_memos';

    protected $fillable = [
        'spa_application_id', 'memo_no', 'prepared_by',
        'forwarded_to', 'forwarded_at',
        'commissioner_decision', 'commissioner_notes', 'decided_at',
        'created_by',
    ];

    protected $casts = [
        'forwarded_at' => 'datetime',
        'decided_at'   => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(SpaApplication::class, 'spa_application_id');
    }

    public function preparedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'prepared_by');
    }

    public static function generateMemoNumber(): string
    {
        $year  = now()->format('Y');
        $last  = DB::connection('sqlsrv')
            ->table('spa_memos')
            ->where('memo_no', 'like', "SPA/{$year}/%")
            ->orderByDesc('id')
            ->value('memo_no');

        $seq = $last ? ((int) substr($last, strrpos($last, '/') + 1)) + 1 : 1;

        return "SPA/{$year}/" . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
