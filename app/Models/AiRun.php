<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'branch_id', 'user_id', 'run_number', 'goal',
        'status', 'model_provider', 'model_name', 'context_snapshot',
        'prompt_tokens', 'completion_tokens', 'estimated_cost_minor',
        'actual_cost_minor', 'error_code', 'started_at', 'finished_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'context_snapshot' => 'array',
            'metadata' => 'array',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'estimated_cost_minor' => 'integer',
            'actual_cost_minor' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(AiToolCall::class)->orderBy('created_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AiRunEvent::class)->orderBy('occurred_at');
    }
}
