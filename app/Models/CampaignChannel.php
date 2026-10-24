<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'campaign_id', 'channel_key', 'publish_mode', 'status',
        'external_campaign_id', 'settings_snapshot', 'metadata',
    ];

    protected function casts(): array
    {
        return ['settings_snapshot' => 'array', 'metadata' => 'array'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(CampaignCreative::class)->orderBy('id');
    }

    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(CampaignDailyMetric::class)->orderBy('metric_date');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CampaignEvent::class)->orderBy('occurred_at');
    }
}
