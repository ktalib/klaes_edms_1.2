<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsentApplication extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'consent_applications';

    protected $fillable = [
        'application_tracking_no',
        'file_number',
        'c_of_o_no',
        'consent_type',
        'applicant_name',
        'applicant_address',
        'party_name',
        'party_address',
        'additional_parties',
        'additional_properties',
        'consideration',
        'consideration_words',
        'application_date',
        'application_submitted_date',
        'application_type',
        'status',
        'print_count',
        'user_id',
        'created_by',
        'property_description',
        'additional_applicants',
        'right_of_occupancy_number',
        'right_of_occupancy_landuse',
        'purpose_of_right_of_occupancy',
        'original_holder_name',
        'correspondence_address',
        'postal_address_gsm',
        'nationality_state_of_origin',
        'nationality',
            'state_of_origin',
            'stage_of_development',
        'location_of_right_of_occupancy',
        'date_of_grant',
        'date_of_grant_purpose',
        'special_mortgage_terms'
    ];
    
    protected $casts = [
        'application_date' => 'date',
        'application_submitted_date' => 'date',
        'date_of_grant' => 'date',
        'print_count' => 'integer',
        'additional_parties' => 'array',
        'additional_applicants' => 'array',
        'additional_properties' => 'array'
    ];

    /**
     * Get the user who created the application.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
