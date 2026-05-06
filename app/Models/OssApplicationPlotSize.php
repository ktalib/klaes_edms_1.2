<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OssApplicationPlotSize extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';

    protected $table = 'oss_application_plot_sizes';

    protected $fillable = [
        'oss_application_id',
        'plot_number',
        'plot_size',
        'type',
    ];

    public function application()
    {
        return $this->belongsTo(LandsOneStopShopApplication::class, 'oss_application_id');
    }
}
