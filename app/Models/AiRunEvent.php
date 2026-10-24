<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRunEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'organization_id', 'ai_run_id', 'ai_tool_call_id', 'event_type',
        'status', 'actor_user_id', 'notes', 'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }

    public function toolCall(): BelongsTo
    {
        return $this->belongsTo(AiToolCall::class, 'ai_tool_call_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
