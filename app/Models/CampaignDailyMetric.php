<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignDailyMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'campaign_id', 'campaign_channel_id', 'metric_date',
        'impressions', 'clicks', 'conversions', 'spend_minor',
        'attributed_revenue_minor', 'source', 'recorded_by', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'impressions' => 'integer',
            'clicks' => 'integer',
            'conversions' => 'integer',
            'spend_minor' => 'integer',
            'attributed_revenue_minor' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(CampaignChannel::class, 'campaign_channel_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
