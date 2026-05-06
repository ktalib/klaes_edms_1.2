<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillBalance extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';

    protected $table = 'deeds_bill_balances_metadata';

    protected $fillable = [
        'reference',
        'file_number',
        'applicant_name',
        'applicant_address',
        'location_station',
        'district',
        'area',
        'unit_cost',
        'amount',
        'prepared_at',
        'date_of_expiry',
        'rent_from_year',
        'rent_to_year',
        'app_plot_number',
        'app_street_name',
        'app_district',
        'app_lga',
        'app_state',
        'loc_plot_number',
        'loc_street_name',
        'loc_district',
        'loc_lga',
        'loc_state',
        'billing_id',
    ];

    protected $casts = [
        'area' => 'float',
        'unit_cost' => 'float',
        'amount' => 'float',
        'prepared_at' => 'datetime',
        'date_of_expiry' => 'date',
        'rent_from_year' => 'integer',
        'rent_to_year' => 'integer',
        'billing_id' => 'integer',
    ];
}
