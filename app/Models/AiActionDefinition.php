<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiActionDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'name', 'description', 'handler_key', 'required_permission',
        'risk_level', 'approval_required', 'is_enabled', 'input_schema',
        'output_schema', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'approval_required' => 'boolean',
            'is_enabled' => 'boolean',
            'input_schema' => 'array',
            'output_schema' => 'array',
            'metadata' => 'array',
        ];
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(AiToolCall::class, 'ai_action_definition_id');
    }
}
