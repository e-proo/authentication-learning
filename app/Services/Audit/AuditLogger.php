<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * Write a minimal audit event. Call inside the business transaction when
     * the event must commit or roll back with the state change.
     *
     * Metadata must describe the action, not duplicate passwords, tokens,
     * payment credentials, full addresses, or other secrets.
     */
    public function record(
        ?User $actor,
        ?Organization $organization,
        string $action,
        ?Model $subject = null,
        array $metadata = [],
    ): AuditLog {
        $request = app()->bound('request') ? request() : null;
        $requestId = $request?->attributes->get('audit_request_id');

        if (! is_string($requestId) || ! Str::isUuid($requestId)) {
            $providedId = $request?->header('X-Request-Id');
            $requestId = is_string($providedId) && Str::isUuid($providedId)
                ? $providedId
                : (string) Str::uuid();

            $request?->attributes->set('audit_request_id', $requestId);
        }

        return AuditLog::query()->create([
            'user_id' => $actor?->id,
            'organization_id' => $organization?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey() !== null ? (string) $subject->getKey() : null,
            'request_id' => $requestId,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $this->sanitize($metadata),
            'created_at' => now(),
        ]);
    }

    private function sanitize(array $metadata): array
    {
        $safe = [];

        foreach ($metadata as $key => $value) {
            $key = (string) $key;

            if (preg_match('/password|token|secret|authorization|cookie|credential|card.?number/i', $key)) {
                continue;
            }

            if (is_array($value)) {
                $safe[$key] = $this->sanitize($value);
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }
}
