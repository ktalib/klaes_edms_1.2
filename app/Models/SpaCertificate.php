<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SpaCertificate extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'spa_certificates';

    protected $fillable = [
        'spa_application_id', 'cert_number', 'file_number',
        'holder_name', 'from_use', 'to_use',
        'issue_date', 'expiry_date', 'issued_by', 'status', 'created_by',
    ];

    protected $casts = [
        'issue_date'  => 'date',
        'expiry_date' => 'date',
    ];

    public function application()
    {
        return $this->belongsTo(SpaApplication::class, 'spa_application_id');
    }

    public function issuedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'issued_by');
    }

    public static function generateCertNumber(): string
    {
        $year = now()->format('Y');
        $last = DB::connection('sqlsrv')
            ->table('spa_certificates')
            ->where('cert_number', 'like', "SPA-COP/{$year}/%")
            ->orderByDesc('id')
            ->value('cert_number');

        $seq = $last ? ((int) substr($last, strrpos($last, '/') + 1)) + 1 : 1;

        return "SPA-COP/{$year}/" . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
