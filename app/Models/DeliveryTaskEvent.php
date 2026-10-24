<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryTaskEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'organization_id', 'delivery_task_id', 'event_type', 'previous_status',
        'status', 'actor_user_id', 'location', 'notes', 'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(DeliveryTask::class, 'delivery_task_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
