<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'shipment_id', 'transport_trip_id', 'driver_id',
        'task_number', 'task_type', 'status', 'sequence_number', 'location_snapshot',
        'recipient_name', 'recipient_phone', 'proof_reference', 'failure_reason',
        'attempt_number', 'created_by', 'assigned_at', 'started_at', 'arrived_at',
        'completed_at', 'failed_at', 'cancelled_at', 'notes', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'location_snapshot' => 'array',
            'attempt_number' => 'integer',
            'sequence_number' => 'integer',
            'metadata' => 'array',
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'arrived_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(TransportTrip::class, 'transport_trip_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(FleetDriver::class, 'driver_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeliveryTaskEvent::class)->orderBy('occurred_at');
    }
}
