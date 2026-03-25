<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Http\Request;

trait LogsAudit
{
    private function logAudit(
        Request $request,
        string $action,
        ?string $resource,
        ?string $propertyName,
        string $category,
        string $result = 'success',
        array $metadata = [],
        ?int $propertyId = null,
    ): void {
        $actor = $request->user();

        AuditLog::create([
            'user_id'       => $actor?->id,
            'user_name'     => $actor?->name ?? 'System',
            'action'        => $action,
            'resource'      => $resource,
            'property_name' => $propertyName,
            'property_id'   => $propertyId,
            'ip_address'    => $request->ip(),
            'result'        => $result,
            'category'      => $category,
            'metadata'      => empty($metadata) ? null : $metadata,
        ]);
    }
}
