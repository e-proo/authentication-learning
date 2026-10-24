<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Organization;
use App\Services\Authorization\OrganizationAccess;
use App\Services\Finance\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(
        Request $request,
        Organization $organization,
        OrganizationAccess $access,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'finance.view');

        return response()->json([
            'data' => Invoice::query()
                ->where('organization_id', $organization->id)
                ->withCount(['items', 'payments'])
                ->latest()
                ->paginate(25),
        ]);
    }

    public function issueFromOrder(
        Request $request,
        Organization $organization,
        string $order,
        OrganizationAccess $access,
        InvoiceService $invoices,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'finance.manage');

        $orderRecord = Order::query()
            ->where('organization_id', $organization->id)
            ->whereKey($order)
            ->firstOrFail();

        $result = $invoices->issueForOrder($organization, $orderRecord);

        return response()->json([
            'data' => $result['invoice'],
            'created' => $result['created'],
        ], $result['created'] ? 201 : 200);
    }

    public function show(
        Request $request,
        Organization $organization,
        string $invoice,
        OrganizationAccess $access,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'finance.view');

        $record = Invoice::query()
            ->where('organization_id', $organization->id)
            ->whereKey($invoice)
            ->with(['items', 'payments.events', 'order:id,organization_id,order_number,status,payment_status,total_minor'])
            ->firstOrFail();

        return response()->json(['data' => $record]);
    }
}
