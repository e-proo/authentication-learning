<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FleetVehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'branch_id', 'vehicle_code', 'registration_plate',
        'vehicle_type', 'capacity_kg', 'make', 'model', 'status', 'metadata',
    ];

    protected function casts(): array
    {
        return ['capacity_kg' => 'integer', 'metadata' => 'array'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(TransportTrip::class, 'vehicle_id');
    }
}
