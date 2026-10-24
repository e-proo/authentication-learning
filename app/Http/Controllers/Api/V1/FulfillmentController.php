<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Organization;
use App\Services\Authorization\OrganizationAccess;
use App\Services\Logistics\FulfillmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FulfillmentController extends Controller
{
    public function createForOrder(
        Request $request,
        Organization $organization,
        string $order,
        OrganizationAccess $access,
        FulfillmentService $fulfillments,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'orders.manage');

        $record = Order::query()
            ->where('organization_id', $organization->id)
            ->whereKey($order)
            ->firstOrFail();

        $created = $fulfillments->createForOrder($organization, $record, $request->user());

        return response()->json(['data' => $created], 201);
    }

    public function pack(
        Request $request,
        Organization $organization,
        string $fulfillment,
        OrganizationAccess $access,
        FulfillmentService $fulfillments,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'shipments.manage');

        $record = Fulfillment::query()
            ->where('organization_id', $organization->id)
            ->whereKey($fulfillment)
            ->firstOrFail();

        return response()->json([
            'data' => $fulfillments->pack($organization, $record),
        ]);
    }
}
