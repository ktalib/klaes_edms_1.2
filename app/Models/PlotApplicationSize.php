<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlotApplicationSize extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'plot_application_sizes';

    protected $fillable = [
        'application_id',
        'application_type',
        'plot_number',
        'plot_size',
        'type',
    ];
}
