<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PaymentService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /**
     * Record a payment attempt. A record in pending state is not proof of payment.
     *
     * @return array{payment: Payment, created: bool}
     */
    public function record(
        Organization $organization,
        User $actor,
        array $data,
        string $idempotencyKey,
    ): array {
        $fingerprint = hash('sha256', json_encode([
            'organization_id' => $organization->id,
            'invoice_id' => (int) $data['invoice_id'],
            'payment_method' => $data['payment_method'],
            'amount_minor' => (int) $data['amount_minor'],
            'provider' => $data['provider'] ?? null,
            'reference' => $data['reference'] ?? null,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use (
            $organization,
            $actor,
            $data,
            $idempotencyKey,
            $fingerprint,
        ): array {
            $existing = Payment::query()
                ->where('organization_id', $organization->id)
                ->where('idempotency_key', $idempotencyKey)
                ->with(['invoice', 'events'])
                ->first();

            if ($existing) {
                if (! hash_equals((string) $existing->request_fingerprint, $fingerprint)) {
                    throw new ConflictHttpException(
                        'This Idempotency-Key was already used for a different payment request.'
                    );
                }

                return ['payment' => $existing, 'created' => false];
            }

            $invoice = Invoice::query()
                ->where('organization_id', $organization->id)
                ->whereKey($data['invoice_id'])
                ->lockForUpdate()
                ->firstOrFail();

            // Re-check after acquiring the invoice lock to resolve concurrent retries.
            $concurrentRetry = Payment::query()
                ->where('organization_id', $organization->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($concurrentRetry) {
                if (! hash_equals((string) $concurrentRetry->request_fingerprint, $fingerprint)) {
                    throw new ConflictHttpException(
                        'This Idempotency-Key was already used for a different payment request.'
                    );
                }

                return ['payment' => $concurrentRetry->load(['invoice', 'events']), 'created' => false];
            }

            if (in_array($invoice->status, ['void', 'paid'], true)) {
                throw new ConflictHttpException('A payment cannot be added to a void or fully paid invoice.');
            }

            $order = Order::query()
                ->where('organization_id', $organization->id)
                ->whereKey($invoice->order_id)
                ->firstOrFail();

            if ($order->status === 'cancelled') {
                throw new ConflictHttpException('A cancelled order cannot receive a payment.');
            }

            $allocated = (int) Payment::query()
                ->where('organization_id', $organization->id)
                ->where('invoice_id', $invoice->id)
                ->whereIn('status', ['pending', 'paid'])
                ->sum('amount_minor');

            $remaining = $invoice->total_minor - $allocated;
            $amount = (int) $data['amount_minor'];

            if ($amount > $remaining) {
                throw ValidationException::withMessages([
                    'amount_minor' => ['The amount exceeds the remaining invoice balance.'],
                ]);
            }

            $payment = Payment::query()->create([
                'organization_id' => $organization->id,
                'invoice_id' => $invoice->id,
                'order_id' => $order->id,
                'payment_method' => $data['payment_method'],
                'provider' => $data['provider'] ?? null,
                'reference' => $data['reference'] ?? null,
                'status' => 'pending',
                'amount_minor' => $amount,
                'currency' => $invoice->currency,
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $fingerprint,
                'recorded_by' => $actor->id,
                'metadata' => $data['metadata'] ?? null,
            ]);

            PaymentEvent::query()->create([
                'organization_id' => $organization->id,
                'payment_id' => $payment->id,
                'event_type' => 'payment_recorded',
                'previous_status' => null,
                'status' => 'pending',
                'actor_user_id' => $actor->id,
                'notes' => 'Payment attempt recorded; collection is not yet verified.',
                'created_at' => now(),
            ]);

            $this->audit->record($actor, $organization, 'payment.recorded', $payment, [
                'payment_method' => $payment->payment_method,
                'amount_minor' => $payment->amount_minor,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'invoice_id' => $invoice->id,
            ]);

            return ['payment' => $payment->load(['invoice', 'events']), 'created' => true];
        }, attempts: 3);
    }

    /**
     * Manually confirm only offline methods. Online methods require a verified provider webhook.
     */
    public function confirmOffline(
        Organization $organization,
        Payment $payment,
        User $actor,
        ?string $reference = null,
        ?string $notes = null,
    ): Payment {
        return DB::transaction(function () use ($organization, $payment, $actor, $reference, $notes): Payment {
            $initial = Payment::query()
                ->where('organization_id', $organization->id)
                ->whereKey($payment->id)
                ->firstOrFail();

            $invoice = Invoice::query()
                ->where('organization_id', $organization->id)
                ->whereKey($initial->invoice_id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked = Payment::query()
                ->where('organization_id', $organization->id)
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'paid') {
                return $locked->load(['invoice', 'events']);
            }

            if ($locked->status !== 'pending') {
                throw new ConflictHttpException('Only pending payments can be confirmed.');
            }

            if ($locked->payment_method === 'online') {
                throw new ConflictHttpException(
                    'Online payment confirmation requires a verified provider webhook.'
                );
            }

            if (! in_array($locked->payment_method, ['cash', 'cash_on_delivery', 'bank_transfer'], true)) {
                throw new ConflictHttpException('This payment method cannot be manually confirmed.');
            }

            $order = Order::query()
                ->where('organization_id', $organization->id)
                ->whereKey($locked->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->payment_method === 'cash_on_delivery' && $order->status !== 'delivered') {
                throw new ConflictHttpException('Cash-on-delivery can only be collected after delivery is recorded.');
            }

            $confirmedReference = trim((string) ($reference ?? $locked->reference));
            if ($locked->payment_method === 'bank_transfer' && $confirmedReference === '') {
                throw ValidationException::withMessages([
                    'reference' => ['A bank transfer reference is required before reconciliation.'],
                ]);
            }

            $previous = $locked->status;
            $locked->status = 'paid';
            $locked->reference = $confirmedReference !== '' ? $confirmedReference : $locked->reference;
            $locked->verified_by = $actor->id;
            $locked->paid_at = now();
            $locked->save();

            PaymentEvent::query()->create([
                'organization_id' => $organization->id,
                'payment_id' => $locked->id,
                'event_type' => 'offline_payment_confirmed',
                'previous_status' => $previous,
                'status' => 'paid',
                'actor_user_id' => $actor->id,
                'notes' => $notes,
                'metadata' => ['reference' => $locked->reference],
                'created_at' => now(),
            ]);

            $paidTotal = (int) Payment::query()
                ->where('organization_id', $organization->id)
                ->where('invoice_id', $invoice->id)
                ->where('status', 'paid')
                ->sum('amount_minor');

            $invoice->paid_total_minor = $paidTotal;
            if ($paidTotal >= $invoice->total_minor) {
                $invoice->status = 'paid';
                $invoice->paid_at = now();
            } else {
                $invoice->status = 'partially_paid';
                $invoice->paid_at = null;
            }
            $invoice->save();

            $order->payment_status = $paidTotal >= $order->total_minor
                ? 'paid'
                : ($paidTotal > 0 ? 'partial' : 'pending');
            $order->save();

            $this->audit->record($actor, $organization, 'payment.confirmed_offline', $locked, [
                'payment_method' => $locked->payment_method,
                'amount_minor' => $locked->amount_minor,
                'currency' => $locked->currency,
                'invoice_id' => $invoice->id,
                'invoice_paid_total_minor' => $paidTotal,
            ]);

            return $locked->load(['invoice', 'events']);
        }, attempts: 3);
    }
}
