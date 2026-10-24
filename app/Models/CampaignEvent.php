<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'organization_id', 'campaign_id', 'campaign_channel_id', 'event_type',
        'previous_status', 'status', 'actor_user_id', 'notes', 'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(CampaignChannel::class, 'campaign_channel_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
