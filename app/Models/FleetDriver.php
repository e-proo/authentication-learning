<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FleetDriver extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'branch_id', 'user_id', 'driver_code', 'display_name',
        'phone', 'driver_type', 'status', 'license_number', 'license_expires_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'license_expires_at' => 'date',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(TransportTrip::class, 'driver_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(DeliveryTask::class, 'driver_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(DriverShift::class, 'driver_id');
    }
}
