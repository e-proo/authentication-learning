<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Fulfillment;
use App\Models\Organization;
use App\Models\Shipment;
use App\Services\Authorization\OrganizationAccess;
use App\Services\Logistics\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShipmentController extends Controller
{
    public function store(
        Request $request,
        Organization $organization,
        string $fulfillment,
        OrganizationAccess $access,
        ShipmentService $shipments,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'shipments.manage');

        $validated = $request->validate([
            'carrier_type' => ['sometimes', Rule::in(['internal', 'external'])],
            'carrier_name' => ['nullable', 'string', 'max:180'],
            'tracking_number' => ['nullable', 'string', 'max:180'],
        ]);

        $record = Fulfillment::query()
            ->where('organization_id', $organization->id)
            ->whereKey($fulfillment)
            ->firstOrFail();

        $shipment = $shipments->createForFulfillment(
            $organization,
            $record,
            $request->user(),
            $validated['carrier_type'] ?? 'internal',
            $validated['carrier_name'] ?? null,
            $validated['tracking_number'] ?? null,
        );

        return response()->json(['data' => $shipment], 201);
    }

    public function updateStatus(
        Request $request,
        Organization $organization,
        string $shipment,
        OrganizationAccess $access,
        ShipmentService $shipments,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'shipments.manage');

        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in(['label_created', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'returned']),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        $record = Shipment::query()
            ->where('organization_id', $organization->id)
            ->whereKey($shipment)
            ->firstOrFail();

        return response()->json([
            'data' => $shipments->transition(
                $organization,
                $record,
                $request->user(),
                $validated['status'],
                $validated['notes'] ?? null,
                $validated['location'] ?? null,
                $validated['metadata'] ?? [],
            ),
        ]);
    }

    public function show(
        Request $request,
        Organization $organization,
        string $shipment,
        OrganizationAccess $access,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'shipments.manage');

        $record = Shipment::query()
            ->where('organization_id', $organization->id)
            ->whereKey($shipment)
            ->with(['items.orderItem', 'events.actor', 'fulfillment.warehouse', 'order'])
            ->firstOrFail();

        return response()->json(['data' => $record]);
    }
}
