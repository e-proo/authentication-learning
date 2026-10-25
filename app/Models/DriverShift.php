<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'driver_id', 'vehicle_id', 'shift_code', 'status',
        'started_at', 'ended_at', 'odometer_start', 'odometer_end',
        'pre_trip_checklist', 'post_trip_checklist', 'start_location', 'end_location',
        'start_device_id', 'end_device_id', 'notes', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'odometer_start' => 'integer',
            'odometer_end' => 'integer',
            'pre_trip_checklist' => 'array',
            'post_trip_checklist' => 'array',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(FleetDriver::class, 'driver_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class, 'vehicle_id');
    }
}
