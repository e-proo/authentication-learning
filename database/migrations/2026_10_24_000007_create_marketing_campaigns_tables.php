<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('store_id')->nullable();
            $table->string('campaign_code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('objective')->index();
            $table->string('status')->default('draft')->index();
            $table->char('currency', 3)->default('YER');
            $table->unsignedBigInteger('budget_minor')->default(0);
            $table->timestamp('scheduled_start_at')->nullable();
            $table->timestamp('scheduled_end_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('audience_snapshot')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'store_id'])
                ->references(['organization_id', 'id'])->on('stores')->restrictOnDelete();
            $table->unique(['organization_id', 'campaign_code']);
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'status', 'created_at']);
        });

        Schema::create('campaign_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('campaign_id');
            $table->string('channel_key')->index();
            $table->string('publish_mode')->default('internal');
            $table->string('status')->default('draft')->index();
            $table->string('external_campaign_id')->nullable();
            $table->jsonb('settings_snapshot')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'campaign_id'])
                ->references(['organization_id', 'id'])->on('campaigns')->cascadeOnDelete();
            $table->unique(['organization_id', 'campaign_id', 'channel_key']);
            $table->unique(['organization_id', 'campaign_id', 'id']);
            $table->index(['organization_id', 'status', 'channel_key']);
        });

        Schema::create('campaign_creatives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('campaign_id');
            $table->foreignId('campaign_channel_id')->nullable();
            $table->string('creative_type')->default('text')->index();
            $table->string('name')->nullable();
            $table->string('headline')->nullable();
            $table->text('primary_text');
            $table->string('call_to_action')->nullable();
            $table->text('destination_url')->nullable();
            $table->string('asset_reference')->nullable();
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('generated_by_ai')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'campaign_id'])
                ->references(['organization_id', 'id'])->on('campaigns')->cascadeOnDelete();
            $table->foreign(['organization_id', 'campaign_id', 'campaign_channel_id'])
                ->references(['organization_id', 'campaign_id', 'id'])->on('campaign_channels')->restrictOnDelete();
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'campaign_id', 'status']);
        });

        Schema::create('campaign_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('campaign_id');
            $table->foreignId('campaign_channel_id')->nullable();
            $table->string('event_type')->index();
            $table->string('previous_status')->nullable();
            $table->string('status')->nullable()->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->foreign(['organization_id', 'campaign_id'])
                ->references(['organization_id', 'id'])->on('campaigns')->cascadeOnDelete();
            $table->foreign(['organization_id', 'campaign_id', 'campaign_channel_id'])
                ->references(['organization_id', 'campaign_id', 'id'])->on('campaign_channels')->restrictOnDelete();
            $table->index(['campaign_id', 'occurred_at']);
        });

        Schema::create('campaign_daily_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('campaign_id');
            $table->foreignId('campaign_channel_id');
            $table->date('metric_date');
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->unsignedBigInteger('spend_minor')->default(0);
            $table->unsignedBigInteger('attributed_revenue_minor')->default(0);
            $table->string('source')->default('manual')->index();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'campaign_id'])
                ->references(['organization_id', 'id'])->on('campaigns')->cascadeOnDelete();
            $table->foreign(['organization_id', 'campaign_id', 'campaign_channel_id'])
                ->references(['organization_id', 'campaign_id', 'id'])->on('campaign_channels')->cascadeOnDelete();
            $table->unique(['organization_id', 'campaign_channel_id', 'metric_date']);
            $table->index(['organization_id', 'campaign_id', 'metric_date']);
        });

        DB::statement("ALTER TABLE campaigns ADD CONSTRAINT campaigns_status_valid CHECK (status IN ('draft', 'in_review', 'approved', 'active', 'paused', 'completed', 'cancelled'))");
        DB::statement("ALTER TABLE campaigns ADD CONSTRAINT campaigns_objective_valid CHECK (objective IN ('awareness', 'traffic', 'leads', 'sales', 'engagement', 'retention', 'other'))");
        DB::statement('ALTER TABLE campaigns ADD CONSTRAINT campaigns_budget_nonnegative CHECK (budget_minor >= 0)');
        DB::statement('ALTER TABLE campaigns ADD CONSTRAINT campaigns_schedule_valid CHECK (scheduled_end_at IS NULL OR scheduled_start_at IS NULL OR scheduled_end_at >= scheduled_start_at)');
        DB::statement("ALTER TABLE campaign_channels ADD CONSTRAINT campaign_channels_key_valid CHECK (channel_key IN ('internal', 'instagram', 'facebook', 'google_ads', 'tiktok', 'telegram', 'other'))");
        DB::statement("ALTER TABLE campaign_channels ADD CONSTRAINT campaign_channels_mode_valid CHECK (publish_mode IN ('internal', 'external'))");
        DB::statement("ALTER TABLE campaign_channels ADD CONSTRAINT campaign_channels_status_valid CHECK (status IN ('draft', 'ready', 'published', 'paused', 'failed'))");
        DB::statement("ALTER TABLE campaign_creatives ADD CONSTRAINT campaign_creatives_type_valid CHECK (creative_type IN ('text', 'image', 'video', 'carousel'))");
        DB::statement("ALTER TABLE campaign_creatives ADD CONSTRAINT campaign_creatives_status_valid CHECK (status IN ('draft', 'in_review', 'approved', 'rejected', 'published'))");
        DB::statement('ALTER TABLE campaign_creatives ADD CONSTRAINT campaign_creatives_version_positive CHECK (version > 0)');
        DB::statement("ALTER TABLE campaign_daily_metrics ADD CONSTRAINT campaign_daily_metrics_source_valid CHECK (source IN ('manual', 'imported', 'estimated'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_daily_metrics');
        Schema::dropIfExists('campaign_events');
        Schema::dropIfExists('campaign_creatives');
        Schema::dropIfExists('campaign_channels');
        Schema::dropIfExists('campaigns');
    }
};
