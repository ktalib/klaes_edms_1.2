<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StreetName extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'street_names';

    protected $fillable = ['name'];
}
