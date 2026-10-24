<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'store_id', 'campaign_code', 'name', 'description',
        'objective', 'status', 'currency', 'budget_minor', 'scheduled_start_at',
        'scheduled_end_at', 'approved_at', 'started_at', 'ended_at', 'created_by',
        'approved_by', 'audience_snapshot', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'budget_minor' => 'integer',
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'approved_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'audience_snapshot' => 'array',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function channels(): HasMany
    {
        return $this->hasMany(CampaignChannel::class)->orderBy('id');
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(CampaignCreative::class)->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CampaignEvent::class)->orderBy('occurred_at');
    }

    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(CampaignDailyMetric::class);
    }
}
