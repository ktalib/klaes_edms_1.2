<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CadastralOfficer extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'cadastral_officers';

    protected $fillable = [
        'name',
        'rank',
    ];
}
