<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AllocationListEntry extends Model
{
    use HasFactory;

    protected $table = 'allocation_list_stage';
    protected $connection = 'sqlsrv';

    protected $fillable = [
        'title',
        'first_name',
        'middle_name',
        'last_name',
        'plot_number',
        'district',
        'lga',
        'state',
        'allottee_address',
        'allocated_by',
        'is_allocated',
        'created_by',
        'updated_by',
    ];
}
