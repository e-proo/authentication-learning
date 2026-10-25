<?php

namespace App\Services\Marketing;

use App\Models\Campaign;
use App\Models\CampaignChannel;
use App\Models\CampaignCreative;
use App\Models\CampaignDailyMetric;
use App\Models\CampaignEvent;
use App\Models\Organization;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CampaignService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function createCampaign(Organization $organization, User $actor, array $data): Campaign
    {
        return DB::transaction(function () use ($organization, $actor, $data): Campaign {
            if (! empty($data['store_id'])) {
                $storeExists = DB::table('stores')
                    ->where('organization_id', $organization->id)
                    ->where('id', $data['store_id'])
                    ->exists();

                if (! $storeExists) {
                    throw ValidationException::withMessages([
                        'store_id' => 'The selected store is not part of this organization.',
                    ]);
                }
            }

            $campaign = Campaign::query()->create([
                'organization_id' => $organization->id,
                'store_id' => $data['store_id'] ?? null,
                'campaign_code' => $this->newCampaignCode($organization),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'objective' => $data['objective'],
                'status' => 'draft',
                'currency' => $data['currency'] ?? 'YER',
                'budget_minor' => $data['budget_minor'] ?? 0,
                'scheduled_start_at' => $data['scheduled_start_at'] ?? null,
                'scheduled_end_at' => $data['scheduled_end_at'] ?? null,
                'created_by' => $actor->id,
                'audience_snapshot' => $data['audience_snapshot'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            $this->recordEvent($organization, $campaign, null, $actor, 'campaign.created', null, 'draft');
            $this->audit->record($actor, $organization, 'campaign.created', $campaign, [
                'campaign_code' => $campaign->campaign_code,
                'objective' => $campaign->objective,
                'status' => $campaign->status,
            ]);

            return $campaign->refresh();
        });
    }

    public function createChannel(
        Organization $organization,
        Campaign $campaign,
        User $actor,
        array $data,
    ): CampaignChannel {
        return DB::transaction(function () use ($organization, $campaign, $actor, $data): CampaignChannel {
            $campaign = $this->lockCampaign($organization, $campaign);

            if ($campaign->status !== 'draft') {
                throw new ConflictHttpException('Channels can only be added while the campaign is in draft.');
            }

            $channelKey = $data['channel_key'];
            $publishMode = $data['publish_mode'];

            if (($channelKey === 'internal') !== ($publishMode === 'internal')) {
                throw ValidationException::withMessages([
                    'publish_mode' => 'Internal channels must use internal mode and external platforms must use external mode.',
                ]);
            }

            if (CampaignChannel::query()
                ->where('organization_id', $organization->id)
                ->where('campaign_id', $campaign->id)
                ->where('channel_key', $channelKey)
                ->exists()) {
                throw new ConflictHttpException('This channel is already attached to the campaign.');
            }

            $channel = CampaignChannel::query()->create([
                'organization_id' => $organization->id,
                'campaign_id' => $campaign->id,
                'channel_key' => $channelKey,
                'publish_mode' => $publishMode,
                'status' => 'draft',
                'settings_snapshot' => $data['settings_snapshot'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            $this->recordEvent($organization, $campaign, $channel, $actor, 'campaign.channel_created', 'draft', 'draft');
            $this->audit->record($actor, $organization, 'campaign.channel_created', $channel, [
                'campaign_id' => $campaign->id,
                'channel_key' => $channel->channel_key,
                'publish_mode' => $channel->publish_mode,
            ]);

            return $channel->refresh();
        });
    }

    public function createCreative(
        Organization $organization,
        Campaign $campaign,
        User $actor,
        array $data,
    ): CampaignCreative {
        return DB::transaction(function () use ($organization, $campaign, $actor, $data): CampaignCreative {
            $campaign = $this->lockCampaign($organization, $campaign);

            if ($campaign->status !== 'draft') {
                throw new ConflictHttpException('Creatives can only be added while the campaign is in draft.');
            }

            $channel = null;
            if (! empty($data['campaign_channel_id'])) {
                $channel = CampaignChannel::query()
                    ->where('organization_id', $organization->id)
                    ->where('campaign_id', $campaign->id)
                    ->whereKey($data['campaign_channel_id'])
                    ->firstOrFail();
            }

            $creative = CampaignCreative::query()->create([
                'organization_id' => $organization->id,
                'campaign_id' => $campaign->id,
                'campaign_channel_id' => $channel?->id,
                'creative_type' => $data['creative_type'],
                'name' => $data['name'] ?? null,
                'headline' => $data['headline'] ?? null,
                'primary_text' => $data['primary_text'],
                'call_to_action' => $data['call_to_action'] ?? null,
                'destination_url' => $data['destination_url'] ?? null,
                'asset_reference' => $data['asset_reference'] ?? null,
                'status' => 'draft',
                'version' => 1,
                'generated_by_ai' => false,
                'created_by' => $actor->id,
                'metadata' => $data['metadata'] ?? null,
            ]);

            $this->recordEvent(
                $organization,
                $campaign,
                $channel,
                $actor,
                'campaign.creative_created',
                null,
                'draft',
                null,
                ['creative_id' => $creative->id, 'creative_type' => $creative->creative_type],
            );
            $this->audit->record($actor, $organization, 'campaign.creative_created', $creative, [
                'campaign_id' => $campaign->id,
                'channel_id' => $channel?->id,
                'creative_type' => $creative->creative_type,
                'version' => $creative->version,
            ]);

            return $creative->refresh();
        });
    }

    public function submitForReview(
        Organization $organization,
        Campaign $campaign,
        User $actor,
        ?string $notes = null,
    ): Campaign {
        return DB::transaction(function () use ($organization, $campaign, $actor, $notes): Campaign {
            $campaign = $this->lockCampaign($organization, $campaign);

            if ($campaign->status !== 'draft') {
                throw new ConflictHttpException('Only draft campaigns can be submitted for review.');
            }

            if (! CampaignChannel::query()->where('organization_id', $organization->id)->where('campaign_id', $campaign->id)->exists()) {
                throw ValidationException::withMessages(['campaign' => 'Add at least one channel before submitting the campaign.']);
            }

            if (! CampaignCreative::query()->where('organization_id', $organization->id)->where('campaign_id', $campaign->id)->exists()) {
                throw ValidationException::withMessages(['campaign' => 'Add at least one creative before submitting the campaign.']);
            }

            $campaign->forceFill(['status' => 'in_review'])->save();
            $this->recordEvent($organization, $campaign, null, $actor, 'campaign.submitted_for_review', 'draft', 'in_review', $notes);
            $this->audit->record($actor, $organization, 'campaign.submitted_for_review', $campaign, ['status' => 'in_review']);

            return $campaign->refresh();
        });
    }

    public function submitCreativeForReview(
        Organization $organization,
        Campaign $campaign,
        CampaignCreative $creative,
        User $actor,
        ?string $notes = null,
    ): CampaignCreative {
        return DB::transaction(function () use ($organization, $campaign, $creative, $actor, $notes): CampaignCreative {
            $campaign = $this->lockCampaign($organization, $campaign);
            $creative = $this->lockCreative($organization, $campaign, $creative);

            if (! in_array($campaign->status, ['in_review', 'approved'], true)) {
                throw new ConflictHttpException('The campaign must be under review before a creative can be submitted.');
            }

            if ($creative->status !== 'draft') {
                throw new ConflictHttpException('Only draft creatives can be submitted for review.');
            }

            $creative->forceFill(['status' => 'in_review'])->save();
            $channel = $creative->campaign_channel_id
                ? CampaignChannel::query()->where('organization_id', $organization->id)->whereKey($creative->campaign_channel_id)->first()
                : null;

            $this->recordEvent($organization, $campaign, $channel, $actor, 'campaign.creative_submitted_for_review', 'draft', 'in_review', $notes, [
                'creative_id' => $creative->id,
            ]);
            $this->audit->record($actor, $organization, 'campaign.creative_submitted_for_review', $creative, [
                'campaign_id' => $campaign->id,
                'status' => 'in_review',
            ]);

            return $creative->refresh();
        });
    }

    public function approveCreative(
        Organization $organization,
        Campaign $campaign,
        CampaignCreative $creative,
        User $actor,
        ?string $notes = null,
    ): CampaignCreative {
        return DB::transaction(function () use ($organization, $campaign, $creative, $actor, $notes): CampaignCreative {
            $campaign = $this->lockCampaign($organization, $campaign);
            $creative = $this->lockCreative($organization, $campaign, $creative);

            if ($creative->status !== 'in_review') {
                throw new ConflictHttpException('Only creatives under review can be approved.');
            }

            $creative->forceFill([
                'status' => 'approved',
                'reviewed_by' => $actor->id,
                'approved_at' => now(),
            ])->save();

            $channel = $creative->campaign_channel_id
                ? CampaignChannel::query()->where('organization_id', $organization->id)->whereKey($creative->campaign_channel_id)->first()
                : null;

            $this->recordEvent($organization, $campaign, $channel, $actor, 'campaign.creative_approved', 'in_review', 'approved', $notes, [
                'creative_id' => $creative->id,
            ]);
            $this->audit->record($actor, $organization, 'campaign.creative_approved', $creative, [
                'campaign_id' => $campaign->id,
                'status' => 'approved',
            ]);

            return $creative->refresh();
        });
    }

    public function approveCampaign(
        Organization $organization,
        Campaign $campaign,
        User $actor,
        ?string $notes = null,
    ): Campaign {
        return DB::transaction(function () use ($organization, $campaign, $actor, $notes): Campaign {
            $campaign = $this->lockCampaign($organization, $campaign);

            if ($campaign->status !== 'in_review') {
                throw new ConflictHttpException('Only campaigns under review can be approved.');
            }

            $creatives = CampaignCreative::query()
                ->where('organization_id', $organization->id)
                ->where('campaign_id', $campaign->id)
                ->lockForUpdate()
                ->get();

            if ($creatives->isEmpty() || $creatives->contains(fn (CampaignCreative $creative): bool => $creative->status !== 'approved')) {
                throw ValidationException::withMessages([
                    'campaign' => 'Every campaign creative must be approved before the campaign can be approved.',
                ]);
            }

            $campaign->forceFill([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $actor->id,
            ])->save();

            $this->recordEvent($organization, $campaign, null, $actor, 'campaign.approved', 'in_review', 'approved', $notes);
            $this->audit->record($actor, $organization, 'campaign.approved', $campaign, ['status' => 'approved']);

            return $campaign->refresh();
        });
    }

    public function publishChannel(
        Organization $organization,
        Campaign $campaign,
        CampaignChannel $channel,
        User $actor,
        ?string $notes = null,
    ): CampaignChannel {
        return DB::transaction(function () use ($organization, $campaign, $channel, $actor, $notes): CampaignChannel {
            $campaign = $this->lockCampaign($organization, $campaign);
            $channel = CampaignChannel::query()
                ->where('organization_id', $organization->id)
                ->where('campaign_id', $campaign->id)
                ->whereKey($channel->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($campaign->status !== 'approved' && $campaign->status !== 'active') {
                throw new ConflictHttpException('The campaign must be approved before a channel can be published.');
            }

            if ($channel->publish_mode !== 'internal' || $channel->channel_key !== 'internal') {
                throw new HttpException(501, 'External campaign publishing is not configured. No external publication was attempted.');
            }

            if ($channel->status === 'published') {
                return $channel;
            }

            $approvedCreativeExists = CampaignCreative::query()
                ->where('organization_id', $organization->id)
                ->where('campaign_id', $campaign->id)
                ->where('campaign_channel_id', $channel->id)
                ->where('status', 'approved')
                ->exists();

            if (! $approvedCreativeExists) {
                throw ValidationException::withMessages([
                    'channel' => 'At least one approved creative is required for this channel.',
                ]);
            }

            $channel->forceFill(['status' => 'published'])->save();
            if ($campaign->status !== 'active') {
                $campaign->forceFill([
                    'status' => 'active',
                    'started_at' => now(),
                ])->save();
            }

            $this->recordEvent($organization, $campaign, $channel, $actor, 'campaign.channel_published_internal', null, 'published', $notes);
            $this->audit->record($actor, $organization, 'campaign.internal_channel_published', $channel, [
                'campaign_id' => $campaign->id,
                'channel_key' => $channel->channel_key,
                'status' => 'published',
            ]);

            return $channel->refresh();
        });
    }

    public function recordDailyMetrics(
        Organization $organization,
        Campaign $campaign,
        CampaignChannel $channel,
        User $actor,
        array $data,
    ): CampaignDailyMetric {
        return DB::transaction(function () use ($organization, $campaign, $channel, $actor, $data): CampaignDailyMetric {
            $campaign = Campaign::query()
                ->where('organization_id', $organization->id)
                ->whereKey($campaign->id)
                ->lockForUpdate()
                ->firstOrFail();

            $channel = CampaignChannel::query()
                ->where('organization_id', $organization->id)
                ->where('campaign_id', $campaign->id)
                ->whereKey($channel->id)
                ->lockForUpdate()
                ->firstOrFail();

            $metricDate = $data['metric_date'];
            $metric = CampaignDailyMetric::query()
                ->where('organization_id', $organization->id)
                ->where('campaign_channel_id', $channel->id)
                ->whereDate('metric_date', $metricDate)
                ->lockForUpdate()
                ->first();

            $values = [
                'impressions' => $data['impressions'] ?? 0,
                'clicks' => $data['clicks'] ?? 0,
                'conversions' => $data['conversions'] ?? 0,
                'spend_minor' => $data['spend_minor'] ?? 0,
                'attributed_revenue_minor' => $data['attributed_revenue_minor'] ?? 0,
                'source' => $data['source'],
                'recorded_by' => $actor->id,
                'metadata' => $data['metadata'] ?? null,
            ];

            if ($metric) {
                $metric->fill($values)->save();
            } else {
                $metric = CampaignDailyMetric::query()->create([
                    'organization_id' => $organization->id,
                    'campaign_id' => $campaign->id,
                    'campaign_channel_id' => $channel->id,
                    'metric_date' => $metricDate,
                    ...$values,
                ]);
            }

            $this->recordEvent($organization, $campaign, $channel, $actor, 'campaign.metrics_recorded', null, null, null, [
                'metric_date' => (string) $metricDate,
                'source' => $metric->source,
                'metric_id' => $metric->id,
            ]);
            $this->audit->record($actor, $organization, 'campaign.metrics_recorded', $metric, [
                'campaign_id' => $campaign->id,
                'channel_id' => $channel->id,
                'source' => $metric->source,
                'metric_date' => (string) $metricDate,
            ]);

            return $metric->refresh();
        });
    }

    private function lockCampaign(Organization $organization, Campaign $campaign): Campaign
    {
        return Campaign::query()
            ->where('organization_id', $organization->id)
            ->whereKey($campaign->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockCreative(Organization $organization, Campaign $campaign, CampaignCreative $creative): CampaignCreative
    {
        return CampaignCreative::query()
            ->where('organization_id', $organization->id)
            ->where('campaign_id', $campaign->id)
            ->whereKey($creative->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function newCampaignCode(Organization $organization): string
    {
        do {
            $code = 'CMP-' . Str::upper(Str::random(10));
        } while (Campaign::query()
            ->where('organization_id', $organization->id)
            ->where('campaign_code', $code)
            ->exists());

        return $code;
    }

    private function recordEvent(
        Organization $organization,
        Campaign $campaign,
        ?CampaignChannel $channel,
        ?User $actor,
        string $eventType,
        ?string $previousStatus,
        ?string $status,
        ?string $notes = null,
        array $metadata = [],
    ): CampaignEvent {
        return CampaignEvent::query()->create([
            'organization_id' => $organization->id,
            'campaign_id' => $campaign->id,
            'campaign_channel_id' => $channel?->id,
            'event_type' => $eventType,
            'previous_status' => $previousStatus,
            'status' => $status,
            'actor_user_id' => $actor?->id,
            'notes' => $notes,
            'metadata' => $metadata ?: null,
            'occurred_at' => now(),
        ]);
    }
}
