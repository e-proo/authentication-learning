<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Payment;
use App\Services\Authorization\OrganizationAccess;
use App\Services\Finance\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(
        Request $request,
        Organization $organization,
        OrganizationAccess $access,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'finance.view');

        return response()->json([
            'data' => Payment::query()
                ->where('organization_id', $organization->id)
                ->with(['invoice:id,organization_id,invoice_number,status,total_minor,currency', 'order:id,organization_id,order_number,status'])
                ->latest()
                ->paginate(25),
        ]);
    }

    public function store(
        Request $request,
        Organization $organization,
        OrganizationAccess $access,
        PaymentService $payments,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'finance.manage');

        $validated = $request->validate([
            'invoice_id' => [
                'required', 'integer',
                Rule::exists('invoices', 'id')->where('organization_id', $organization->id),
            ],
            'payment_method' => ['required', Rule::in(['cash', 'cash_on_delivery', 'bank_transfer', 'online'])],
            'amount_minor' => ['required', 'integer', 'min:1', 'max:9000000000000000'],
            'provider' => ['nullable', 'string', 'max:120'],
            'reference' => ['nullable', 'string', 'max:180'],
            'metadata' => ['nullable', 'array:source,external_reference,receipt_id'],
            'metadata.source' => ['nullable', 'string', 'max:80'],
            'metadata.external_reference' => ['nullable', 'string', 'max:180'],
            'metadata.receipt_id' => ['nullable', 'string', 'max:180'],
        ]);

        $key = $request->header('Idempotency-Key');
        if ($key === null || strlen($key) < 8 || strlen($key) > 128) {
            return response()->json([
                'message' => 'Idempotency-Key is required and must contain 8–128 characters.',
            ], 422);
        }

        $result = $payments->record($organization, $request->user(), $validated, $key);

        return response()->json([
            'data' => $result['payment'],
            'created' => $result['created'],
        ], $result['created'] ? 201 : 200);
    }

    public function confirm(
        Request $request,
        Organization $organization,
        string $payment,
        OrganizationAccess $access,
        PaymentService $payments,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'finance.manage');

        $validated = $request->validate([
            'reference' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $record = Payment::query()
            ->where('organization_id', $organization->id)
            ->whereKey($payment)
            ->firstOrFail();

        return response()->json([
            'data' => $payments->confirmOffline(
                $organization,
                $record,
                $request->user(),
                $validated['reference'] ?? null,
                $validated['notes'] ?? null,
            ),
        ]);
    }
}
