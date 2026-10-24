<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_action_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description');
            $table->string('handler_key');
            $table->string('required_permission')->index();
            $table->string('risk_level')->index();
            $table->boolean('approval_required')->default(false);
            $table->boolean('is_enabled')->default(true)->index();
            $table->jsonb('input_schema')->nullable();
            $table->jsonb('output_schema')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('run_number')->unique();
            $table->text('goal');
            $table->string('status')->default('provider_unconfigured')->index();
            $table->string('model_provider')->nullable();
            $table->string('model_name')->nullable();
            $table->jsonb('context_snapshot')->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedBigInteger('estimated_cost_minor')->default(0);
            $table->unsignedBigInteger('actual_cost_minor')->default(0);
            $table->string('error_code')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'branch_id'])
                ->references(['organization_id', 'id'])->on('branches')->restrictOnDelete();
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'user_id', 'created_at']);
        });

        Schema::create('ai_tool_calls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('ai_run_id');
            $table->foreignId('ai_action_definition_id')->constrained('ai_action_definitions')->restrictOnDelete();
            $table->string('tool_name');
            $table->string('status')->default('awaiting_approval')->index();
            $table->jsonb('input_snapshot');
            $table->jsonb('output_snapshot')->nullable();
            $table->char('request_fingerprint', 64);
            $table->string('idempotency_key')->nullable();
            $table->boolean('approval_required')->default(false);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('error_code')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'ai_run_id'])
                ->references(['organization_id', 'id'])->on('ai_runs')->cascadeOnDelete();
            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'idempotency_key']);
            $table->index(['organization_id', 'status', 'created_at']);
        });

        Schema::create('ai_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('ai_tool_call_id');
            $table->string('status')->default('pending')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_notes')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'ai_tool_call_id'])
                ->references(['organization_id', 'id'])->on('ai_tool_calls')->cascadeOnDelete();
            $table->unique(['organization_id', 'ai_tool_call_id']);
        });

        Schema::create('ai_run_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('ai_run_id');
            $table->foreignId('ai_tool_call_id')->nullable();
            $table->string('event_type')->index();
            $table->string('status')->nullable()->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->foreign(['organization_id', 'ai_run_id'])
                ->references(['organization_id', 'id'])->on('ai_runs')->cascadeOnDelete();
            $table->foreign(['organization_id', 'ai_tool_call_id'])
                ->references(['organization_id', 'id'])->on('ai_tool_calls')->cascadeOnDelete();
            $table->index(['ai_run_id', 'occurred_at']);
        });

        DB::statement("ALTER TABLE ai_action_definitions ADD CONSTRAINT ai_actions_risk_valid CHECK (risk_level IN ('read_only', 'suggestion', 'sensitive_write'))");
        DB::statement("ALTER TABLE ai_runs ADD CONSTRAINT ai_runs_status_valid CHECK (status IN ('created', 'provider_unconfigured', 'awaiting_approval', 'completed', 'failed', 'cancelled'))");
        DB::statement("ALTER TABLE ai_tool_calls ADD CONSTRAINT ai_tool_calls_status_valid CHECK (status IN ('awaiting_approval', 'running', 'executed', 'rejected', 'failed'))");
        DB::statement("ALTER TABLE ai_approvals ADD CONSTRAINT ai_approvals_status_valid CHECK (status IN ('pending', 'approved', 'rejected'))");

        $now = now();
        $actions = [
            ['inventory.low_stock', 'Find low-stock inventory', 'Read available inventory balances at or below their reorder level.', 'inventory.low_stock', 'inventory.view', 'read_only', false],
            ['shipment.summary', 'Summarize a shipment', 'Read a shipment status, timeline, and safe shipment/order identifiers.', 'shipment.summary', 'shipments.view', 'read_only', false],
            ['invoice.summary', 'Summarize an invoice', 'Read invoice totals, payment status, and remaining balance.', 'invoice.summary', 'finance.view', 'read_only', false],
            ['campaign.metrics_summary', 'Summarize campaign metrics', 'Read recorded campaign metrics with the existing source labels.', 'campaign.metrics_summary', 'marketing.view', 'read_only', false],
            ['campaign.prepare_draft', 'Prepare a campaign draft', 'Prepare a campaign input draft without saving or publishing it.', 'campaign.prepare_draft', 'marketing.manage', 'suggestion', false],
            ['inventory.adjust_stock', 'Adjust stock', 'Apply a stock adjustment through the inventory domain service.', 'inventory.adjust_stock', 'inventory.adjust', 'sensitive_write', true],
            ['orders.cancel', 'Cancel an order', 'Cancel an eligible confirmed order and release reservations.', 'orders.cancel', 'orders.manage', 'sensitive_write', true],
            ['campaign.publish_internal', 'Publish an internal campaign channel', 'Publish an approved campaign to the internal channel only.', 'campaign.publish_internal', 'marketing.manage', 'sensitive_write', true],
            ['return.approve', 'Approve a return request', 'Approve a return case that is awaiting a decision.', 'return.approve', 'returns.manage', 'sensitive_write', true],
            ['shipment.update_status', 'Update a shipment status', 'Apply an allowed non-delivery shipment status change through the shipment service.', 'shipment.update_status', 'shipments.manage', 'sensitive_write', true],
        ];

        foreach ($actions as [$slug, $name, $description, $handler, $permission, $risk, $approval]) {
            DB::table('ai_action_definitions')->insert([
                'slug' => $slug,
                'name' => $name,
                'description' => $description,
                'handler_key' => $handler,
                'required_permission' => $permission,
                'risk_level' => $risk,
                'approval_required' => $approval,
                'is_enabled' => true,
                'input_schema' => json_encode(['type' => 'object'], JSON_THROW_ON_ERROR),
                'output_schema' => json_encode(['type' => 'object'], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_run_events');
        Schema::dropIfExists('ai_approvals');
        Schema::dropIfExists('ai_tool_calls');
        Schema::dropIfExists('ai_runs');
        Schema::dropIfExists('ai_action_definitions');
    }
};
