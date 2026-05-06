<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivingOfficer extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'receiving_officers';
    protected $primaryKey = 'id';

    protected $fillable = [
        'first_name',
        'last_name',
        'phone_number',
        'email',
        'office_name',
        'designation',
        'notes',
        'created_by',
        'updated_by',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getNameAttribute(): string
    {
        return $this->getFullNameAttribute();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function scopeSearchByName($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'LIKE', "%{$search}%")
              ->orWhere('last_name', 'LIKE', "%{$search}%");
        });
    }
}
