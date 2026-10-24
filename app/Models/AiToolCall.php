<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AiToolCall extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'ai_run_id', 'ai_action_definition_id', 'tool_name',
        'status', 'input_snapshot', 'output_snapshot', 'request_fingerprint',
        'idempotency_key', 'approval_required', 'requested_by', 'approved_by',
        'executed_by', 'error_code', 'requested_at', 'approved_at', 'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'input_snapshot' => 'array',
            'output_snapshot' => 'array',
            'approval_required' => 'boolean',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }

    public function actionDefinition(): BelongsTo
    {
        return $this->belongsTo(AiActionDefinition::class, 'ai_action_definition_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function approval(): HasOne
    {
        return $this->hasOne(AiApproval::class, 'ai_tool_call_id');
    }
}
