<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'ai_tool_call_id', 'status', 'requested_by',
        'decided_by', 'decision_notes', 'requested_at', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function toolCall(): BelongsTo
    {
        return $this->belongsTo(AiToolCall::class, 'ai_tool_call_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
