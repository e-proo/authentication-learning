<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignCreative extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'campaign_id', 'campaign_channel_id', 'creative_type',
        'name', 'headline', 'primary_text', 'call_to_action', 'destination_url',
        'asset_reference', 'status', 'version', 'generated_by_ai', 'created_by',
        'reviewed_by', 'approved_at', 'published_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'generated_by_ai' => 'boolean',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
