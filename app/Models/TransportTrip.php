<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportTrip extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'branch_id', 'driver_id', 'vehicle_id',
        'trip_number', 'trip_type', 'status', 'origin_snapshot', 'destination_snapshot',
        'planned_departure_at', 'planned_arrival_at', 'departed_at', 'arrived_at',
        'notes', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'origin_snapshot' => 'array',
            'destination_snapshot' => 'array',
            'planned_departure_at' => 'datetime',
            'planned_arrival_at' => 'datetime',
            'departed_at' => 'datetime',
            'arrived_at' => 'datetime',
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

    public function driver(): BelongsTo
    {
        return $this->belongsTo(FleetDriver::class, 'driver_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class, 'vehicle_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TransportTripEvent::class)->orderBy('occurred_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(DeliveryTask::class, 'transport_trip_id')->orderBy('sequence_number');
    }
}
