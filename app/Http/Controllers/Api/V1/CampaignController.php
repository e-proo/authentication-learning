<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignChannel;
use App\Models\CampaignCreative;
use App\Models\CampaignDailyMetric;
use App\Models\Organization;
use App\Services\Authorization\OrganizationAccess;
use App\Services\Marketing\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    public function index(Request $request, Organization $organization, OrganizationAccess $access): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'marketing.view');

        $query = Campaign::query()
            ->where('organization_id', $organization->id)
            ->withCount(['channels', 'creatives'])
            ->latest();

        if ($request->filled('status')) {
            $validated = $request->validate([
                'status' => [Rule::in(['draft', 'in_review', 'approved', 'active', 'paused', 'completed', 'cancelled'])],
            ]);
            $query->where('status', $validated['status']);
        }

        return response()->json(['data' => $query->paginate(25)]);
    }

    public function store(
        Request $request,
        Organization $organization,
        OrganizationAccess $access,
        CampaignService $campaigns,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'marketing.manage');

        $validated = $request->validate([
            'store_id' => [
                'nullable', 'integer',
                Rule::exists('stores', 'id')->where('organization_id', $organization->id),
            ],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'objective' => ['required', Rule::in(['awareness', 'traffic', 'leads', 'sales', 'engagement', 'retention', 'other'])],
            'currency' => ['sometimes', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'budget_minor' => ['sometimes', 'integer', 'min:0', 'max:9000000000000000'],
            'scheduled_start_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date', 'after_or_equal:scheduled_start_at'],
            'audience_snapshot' => ['nullable', 'array:buyer_type,geographic_regions,interests,keywords,segment_name'],
            'audience_snapshot.buyer_type' => ['nullable', Rule::in(['B2B', 'B2C', 'all'])],
            'audience_snapshot.geographic_regions' => ['nullable', 'array', 'max:100'],
            'audience_snapshot.geographic_regions.*' => ['string', 'max:120'],
            'audience_snapshot.interests' => ['nullable', 'array', 'max:100'],
            'audience_snapshot.interests.*' => ['string', 'max:120'],
            'audience_snapshot.keywords' => ['nullable', 'array', 'max:100'],
            'audience_snapshot.keywords.*' => ['string', 'max:120'],
            'audience_snapshot.segment_name' => ['nullable', 'string', 'max:180'],
            'metadata' => ['nullable', 'array:source,reference'],
            'metadata.source' => ['nullable', 'string', 'max:80'],
            'metadata.reference' => ['nullable', 'string', 'max:180'],
        ]);

        $campaign = $campaigns->createCampaign($organization, $request->user(), $validated);

        return response()->json(['data' => $campaign], 201);
    }

    public function show(Request $request, Organization $organization, string $campaign, OrganizationAccess $access): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'marketing.view');

        $record = Campaign::query()
            ->where('organization_id', $organization->id)
            ->whereKey($campaign)
            ->with([
                'store',
                'creator:id,name,email',
                'approver:id,name,email',
                'channels.creatives',
                'channels.dailyMetrics',
                'creatives.channel',
                'events.actor:id,name',
            ])
            ->firstOrFail();

        return response()->json(['data' => $record]);
    }

    public function createChannel(
        Request $request,
        Organization $organization,
        string $campaign,
        OrganizationAccess $access,
        CampaignService $campaigns,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'marketing.manage');

        $validated = $request->validate([
            'channel_key' => ['required', Rule::in(['internal', 'instagram', 'facebook', 'google_ads', 'tiktok', 'telegram', 'other'])],
            'publish_mode' => ['required', Rule::in(['internal', 'external'])],
            'settings_snapshot' => ['nullable', 'array:target_url,tracking_source,tracking_medium,tracking_campaign'],
            'settings_snapshot.target_url' => ['nullable', 'url:https', 'max:2000'],
            'settings_snapshot.tracking_source' => ['nullable', 'string', 'max:120'],
            'settings_snapshot.tracking_medium' => ['nullable', 'string', 'max:120'],
            'settings_snapshot.tracking_campaign' => ['nullable', 'string', 'max:180'],
            'metadata' => ['nullable', 'array:external_reference,notes'],
            'metadata.external_reference' => ['nullable', 'string', 'max:180'],
            'metadata.notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (($validated['channel_key'] === 'internal') !== ($validated['publish_mode'] === 'internal')) {
            return response()->json([
                'message' => 'The internal channel must use internal mode; external platform channels must use external mode.',
            ], 422);
        }

        $campaignRecord = Campaign::query()
            ->where('organization_id', $organization->id)
            ->whereKey($campaign)
            ->firstOrFail();

        $channel = $campaigns->createChannel($organization, $campaignRecord, $request->user(), $validated);

        return response()->json(['data' => $channel], 201);
    }

    public function createCreative(
        Request $request,
        Organization $organization,
        string $campaign,
        OrganizationAccess $access,
        CampaignService $campaigns,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'marketing.manage');

        $validated = $request->validate([
            'campaign_channel_id' => [
                'required', 'integer',
                Rule::exists('campaign_channels', 'id')
                    ->where('organization_id', $organization->id)
                    ->where('campaign_id', $campaign),
            ],
            'creative_type' => ['required', Rule::in(['text', 'image', 'video', 'carousel'])],
            'name' => ['nullable', 'string', 'max:180'],
            'headline' => ['nullable', 'string', 'max:180'],
            'primary_text' => ['required', 'string', 'max:10000'],
            'call_to_action' => ['nullable', Rule::in(['learn_more', 'shop_now', 'sign_up', 'contact_us', 'get_quote', 'message', 'download', 'none'])],
            'destination_url' => ['nullable', 'url:https', 'max:2000'],
            'asset_reference' => ['nullable', 'string', 'max:500'],
            'metadata' => ['nullable', 'array:language,version_note'],
            'metadata.language' => ['nullable', 'string', 'max:20'],
            'metadata.version_note' => ['nullable', 'string', 'max:500'],
        ]);

        $campaignRecord = Campaign::query()
            ->where('organization_id', $organization->id)
            ->whereKey($campaign)
            ->firstOrFail();

        $creative = $campaigns->createCreative($organization, $campaignRecord, $request->user(), $validated);

        return response()->json(['data' => $creative], 201);
    }

    public function submitForReview(
        Request $request,
        Organization $organization,
        string $campaign,
        OrganizationAccess $access,
        CampaignService $campaigns,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'marketing.manage');
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        $record = Campaign::query()->where('organization_id', $organization->id)->whereKey($campaign)->firstOrFail();

        return response()->json([
            'data' => $campaigns->submitForReview($organization, $record, $request->user(), $validated['notes'] ?? null),
        ]);
    }

    public function submitCreativeForReview(
        Request $request,
        Organization $organization,
        string $campaign,
        string $creative,
        OrganizationAccess $access,
        CampaignService $campaigns,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'marketing.manage');
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        $campaignRecord = Campaign::query()->where('organization_id', $organization->id)->whereKey($campaign)->firstOrFail();
        $creativeRecord = CampaignCreative::query()->where('organization_id', $organization->id)
            ->where('campaign_id', $campaignRecord->id)->whereKey($creative)->firstOrFail();

        return response()->json([
            'data' => $campaigns->submitCreativeForReview($organization, $campaignRecord, $creativeRecord, $request->user(), $validated['notes'] ?? null),
        ]);
    }

    public function approveCreative(
        Request $request,
        Organization $organization,
        string $campaign,
        string $creative,
        OrganizationAccess $access,
        CampaignService $campaigns,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'marketing.approve');
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        $campaignRecord = Campaign::query()->where('organization_id', $organization->id)->whereKey($campaign)->firstOrFail();
        $creativeRecord = CampaignCreative::query()->where('organization_id', $organization->id)
            ->where('campaign_id', $campaignRecord->id)->whereKey($creative)->firstOrFail();

        return response()->json([
            'data' => $campaigns->approveCreative($organization, $campaignRecord, $creativeRecord, $request->user(), $validated['notes'] ?? null),
        ]);
    }

    public function approveCampaign(
        Request $request,
        Organization $organization,
        string $campaign,
        OrganizationAccess $access,
        CampaignService $campaigns,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'marketing.approve');
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        $record = Campaign::query()->where('organization_id', $organization->id)->whereKey($campaign)->firstOrFail();

        return response()->json([
            'data' => $campaigns->approveCampaign($organization, $record, $request->user(), $validated['notes'] ?? null),
        ]);
    }

    public function publishChannel(
        Request $request,
        Organization $organization,
        string $campaign,
        string $channel,
        OrganizationAccess $access,
        CampaignService $campaigns,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'marketing.manage');
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        $campaignRecord = Campaign::query()->where('organization_id', $organization->id)->whereKey($campaign)->firstOrFail();
        $channelRecord = CampaignChannel::query()->where('organization_id', $organization->id)
            ->where('campaign_id', $campaignRecord->id)->whereKey($channel)->firstOrFail();

        return response()->json([
            'data' => $campaigns->publishChannel($organization, $campaignRecord, $channelRecord, $request->user(), $validated['notes'] ?? null),
        ]);
    }

    public function recordMetrics(
        Request $request,
        Organization $organization,
        string $campaign,
        string $channel,
        OrganizationAccess $access,
        CampaignService $campaigns,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'marketing.manage');

        $validated = $request->validate([
            'metric_date' => ['required', 'date', 'before_or_equal:today'],
            'impressions' => ['sometimes', 'integer', 'min:0', 'max:9000000000000000'],
            'clicks' => ['sometimes', 'integer', 'min:0', 'max:9000000000000000'],
            'conversions' => ['sometimes', 'integer', 'min:0', 'max:9000000000000000'],
            'spend_minor' => ['sometimes', 'integer', 'min:0', 'max:9000000000000000'],
            'attributed_revenue_minor' => ['sometimes', 'integer', 'min:0', 'max:9000000000000000'],
            'source' => ['required', Rule::in(['manual', 'imported', 'estimated'])],
            'metadata' => ['nullable', 'array:source_reference,notes'],
            'metadata.source_reference' => ['nullable', 'string', 'max:180'],
            'metadata.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $campaignRecord = Campaign::query()->where('organization_id', $organization->id)->whereKey($campaign)->firstOrFail();
        $channelRecord = CampaignChannel::query()->where('organization_id', $organization->id)
            ->where('campaign_id', $campaignRecord->id)->whereKey($channel)->firstOrFail();

        $metric = $campaigns->recordDailyMetrics($organization, $campaignRecord, $channelRecord, $request->user(), $validated);

        return response()->json(['data' => $metric]);
    }

    public function metrics(
        Request $request,
        Organization $organization,
        string $campaign,
        OrganizationAccess $access,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'marketing.view');

        $record = Campaign::query()->where('organization_id', $organization->id)->whereKey($campaign)->firstOrFail();
        $daily = CampaignDailyMetric::query()
            ->where('organization_id', $organization->id)
            ->where('campaign_id', $record->id)
            ->selectRaw('metric_date, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(conversions) as conversions, SUM(spend_minor) as spend_minor, SUM(attributed_revenue_minor) as attributed_revenue_minor')
            ->groupBy('metric_date')
            ->orderBy('metric_date')
            ->get();

        return response()->json([
            'campaign_id' => $record->id,
            'currency' => $record->currency,
            'totals' => [
                'impressions' => (int) $daily->sum('impressions'),
                'clicks' => (int) $daily->sum('clicks'),
                'conversions' => (int) $daily->sum('conversions'),
                'spend_minor' => (int) $daily->sum('spend_minor'),
                'attributed_revenue_minor' => (int) $daily->sum('attributed_revenue_minor'),
            ],
            'data' => $daily,
            'metric_sources' => CampaignDailyMetric::query()
                ->where('organization_id', $organization->id)
                ->where('campaign_id', $record->id)
                ->distinct()
                ->pluck('source')
                ->values(),
        ]);
    }
}
