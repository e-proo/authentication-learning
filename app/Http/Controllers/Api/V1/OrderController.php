<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use App\Services\Authorization\OrganizationAccess;
use App\Services\Orders\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(
        Request $request,
        Organization $organization,
        OrganizationAccess $access,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'orders.view');

        $orders = Order::query()
            ->where('organization_id', $organization->id)
            ->withCount('items')
            ->latest()
            ->paginate(25);

        return response()->json($orders);
    }

    public function store(
        Request $request,
        Organization $organization,
        OrganizationAccess $access,
        OrderService $orders,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'orders.manage');

        $validated = $request->validate([
            'store_id' => [
                'required', 'integer',
                Rule::exists('stores', 'id')->where('organization_id', $organization->id),
            ],
            'price_list_id' => [
                'required', 'integer',
                Rule::exists('price_lists', 'id')->where('organization_id', $organization->id),
            ],
            'customer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:product_variant_id,warehouse_id,quantity'],
            'items.*.product_variant_id' => ['required', 'integer', 'distinct', 'exists:product_variants,id'],
            'items.*.warehouse_id' => [
                'required', 'integer',
                Rule::exists('warehouses', 'id')->where('organization_id', $organization->id),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'shipping_address' => ['required', 'array:name,phone,country_code,city,address_line1,address_line2,postal_code,instructions'],
            'shipping_address.name' => ['required', 'string', 'max:180'],
            'shipping_address.phone' => ['required', 'string', 'max:40'],
            'shipping_address.country_code' => ['required', 'string', 'size:2'],
            'shipping_address.city' => ['required', 'string', 'max:120'],
            'shipping_address.address_line1' => ['required', 'string', 'max:500'],
            'shipping_address.address_line2' => ['nullable', 'string', 'max:500'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:30'],
            'shipping_address.instructions' => ['nullable', 'string', 'max:1000'],
            'billing_address' => ['sometimes', 'nullable', 'array:name,phone,country_code,city,address_line1,address_line2,postal_code'],
            'billing_address.name' => ['required_with:billing_address', 'string', 'max:180'],
            'billing_address.phone' => ['required_with:billing_address', 'string', 'max:40'],
            'billing_address.country_code' => ['required_with:billing_address', 'string', 'size:2'],
            'billing_address.city' => ['required_with:billing_address', 'string', 'max:120'],
            'billing_address.address_line1' => ['required_with:billing_address', 'string', 'max:500'],
            'billing_address.address_line2' => ['nullable', 'string', 'max:500'],
            'billing_address.postal_code' => ['nullable', 'string', 'max:30'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $key = $request->header('Idempotency-Key');
        if ($key !== null && (strlen($key) < 8 || strlen($key) > 128)) {
            return response()->json([
                'message' => 'Idempotency-Key must contain between 8 and 128 characters.',
            ], 422);
        }

        /** @var User $actor */
        $actor = $request->user();
        $result = $orders->place($organization, $actor, $validated, $key);

        return response()->json([
            'data' => $result['order'],
            'created' => $result['created'],
        ], $result['created'] ? 201 : 200);
    }

    public function show(
        Request $request,
        Organization $organization,
        string $order,
        OrganizationAccess $access,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'orders.view');

        $record = Order::query()
            ->where('organization_id', $organization->id)
            ->whereKey($order)
            ->with(['items', 'statusHistory', 'fulfillments.shipment', 'shipments.events'])
            ->firstOrFail();

        return response()->json(['data' => $record]);
    }

    public function cancel(
        Request $request,
        Organization $organization,
        string $order,
        OrganizationAccess $access,
        OrderService $orders,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'orders.manage');

        $record = Order::query()
            ->where('organization_id', $organization->id)
            ->whereKey($order)
            ->firstOrFail();

        return response()->json([
            'data' => $orders->cancel($organization, $record, $request->user()),
        ]);
    }
}
