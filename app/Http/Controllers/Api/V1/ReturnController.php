<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Organization;
use App\Models\ReturnCase;
use App\Models\ReturnItem;
use App\Services\Authorization\OrganizationAccess;
use App\Services\Logistics\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReturnController extends Controller
{
    public function index(
        Request $request,
        Organization $organization,
        OrganizationAccess $access,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'returns.view');

        return response()->json([
            'data' => ReturnCase::query()
                ->where('organization_id', $organization->id)
                ->withCount('items')
                ->latest()
                ->paginate(25),
        ]);
    }

    public function requestForOrder(
        Request $request,
        Organization $organization,
        string $order,
        OrganizationAccess $access,
        ReturnService $returns,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'returns.manage');

        $validated = $request->validate([
            'reason' => [
                'required',
                Rule::in(['damaged', 'wrong_item', 'not_as_described', 'changed_mind', 'missing_parts', 'other']),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array:order_item_id,quantity,reason'],
            'items.*.order_item_id' => ['required', 'integer', 'distinct', 'exists:order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.reason' => ['nullable', 'string', 'max:500'],
        ]);

        $orderRecord = Order::query()
            ->where('organization_id', $organization->id)
            ->whereKey($order)
            ->firstOrFail();

        $returnCase = $returns->requestForOrder(
            $organization,
            $orderRecord,
            $request->user(),
            $validated,
        );

        return response()->json(['data' => $returnCase], 201);
    }

    public function show(
        Request $request,
        Organization $organization,
        string $returnCase,
        OrganizationAccess $access,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'returns.view');

        $record = ReturnCase::query()
            ->where('organization_id', $organization->id)
            ->whereKey($returnCase)
            ->with(['order:id,organization_id,order_number,status', 'items.orderItem', 'items.receipt.warehouse', 'statusHistory.actor'])
            ->firstOrFail();

        return response()->json(['data' => $record]);
    }

    public function approve(
        Request $request,
        Organization $organization,
        string $returnCase,
        OrganizationAccess $access,
        ReturnService $returns,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'returns.manage');

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $record = ReturnCase::query()
            ->where('organization_id', $organization->id)
            ->whereKey($returnCase)
            ->firstOrFail();

        return response()->json([
            'data' => $returns->approve($organization, $record, $request->user(), $validated['notes'] ?? null),
        ]);
    }

    public function reject(
        Request $request,
        Organization $organization,
        string $returnCase,
        OrganizationAccess $access,
        ReturnService $returns,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'returns.manage');

        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:2000'],
        ]);

        $record = ReturnCase::query()
            ->where('organization_id', $organization->id)
            ->whereKey($returnCase)
            ->firstOrFail();

        return response()->json([
            'data' => $returns->reject($organization, $record, $request->user(), $validated['notes']),
        ]);
    }

    public function receiveItem(
        Request $request,
        Organization $organization,
        string $returnCase,
        string $returnItem,
        OrganizationAccess $access,
        ReturnService $returns,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'returns.manage');

        $validated = $request->validate([
            'quantity_received' => ['required', 'integer', 'min:1', 'max:1000000'],
            'disposition' => ['required', Rule::in(['restock', 'damaged', 'discard'])],
            'warehouse_id' => [
                'nullable',
                'integer',
                'required_if:disposition,restock',
                'prohibited_unless:disposition,restock',
                Rule::exists('warehouses', 'id')->where('organization_id', $organization->id),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['disposition'] === 'restock') {
            $access->requirePermission($request->user(), $organization, 'inventory.adjust');
        }

        $caseRecord = ReturnCase::query()
            ->where('organization_id', $organization->id)
            ->whereKey($returnCase)
            ->firstOrFail();

        $itemRecord = ReturnItem::query()
            ->where('organization_id', $organization->id)
            ->where('return_case_id', $caseRecord->id)
            ->whereKey($returnItem)
            ->firstOrFail();

        $receipt = $returns->receiveItem(
            $organization,
            $caseRecord,
            $itemRecord,
            $request->user(),
            $validated,
        );

        return response()->json(['data' => $receipt], 201);
    }

    public function close(
        Request $request,
        Organization $organization,
        string $returnCase,
        OrganizationAccess $access,
        ReturnService $returns,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'returns.manage');

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $record = ReturnCase::query()
            ->where('organization_id', $organization->id)
            ->whereKey($returnCase)
            ->firstOrFail();

        return response()->json([
            'data' => $returns->close($organization, $record, $request->user(), $validated['notes'] ?? null),
        ]);
    }
}
