<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class AccountingEvent extends Model
{
    protected $fillable = [
        'property_id',
        'event_type',
        'entity_type',
        'entity_id',
        'reference',
        'description',
        'data',
        'posted_entries',
        'status',
        'error_message',
        'created_by',
    ];

    protected $casts = [
        'data' => 'array',
        'posted_entries' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Log a successful accounting event
     */
    public static function logSuccess(
        int $propertyId,
        string $eventType,
        string $entityType,
        int $entityId,
        ?string $reference,
        ?string $description,
        array $data,
        ?array $postedEntries = null,
        ?int $userId = null,
        ?string $ipAddress = null
    ): self {
        return self::create([
            'property_id' => $propertyId,
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'reference' => $reference,
            'description' => $description,
            'data' => $data,
            'posted_entries' => $postedEntries,
            'status' => 'success',
            'created_by' => $userId ?? Auth::id(),
        ]);
    }

    /**
     * Log a failed accounting event
     */
    public static function logFailure(
        int $propertyId,
        string $eventType,
        string $entityType,
        int $entityId,
        ?string $reference,
        ?string $description,
        array $data,
        string $errorMessage,
        ?int $userId = null
    ): self {
        return self::create([
            'property_id' => $propertyId,
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'reference' => $reference,
            'description' => $description,
            'data' => $data,
            'status' => 'failed',
            'error_message' => $errorMessage,
            'created_by' => $userId ?? Auth::id(),
        ]);
    }

    /**
     * Log a reversal event
     */
    public static function logReversal(
        int $propertyId,
        string $eventType,
        string $entityType,
        int $entityId,
        ?string $reference,
        ?string $description,
        array $data,
        ?array $reversalEntries = null,
        ?int $userId = null
    ): self {
        return self::create([
            'property_id' => $propertyId,
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'reference' => $reference,
            'description' => $description,
            'data' => $data,
            'posted_entries' => $reversalEntries,
            'status' => 'reversed',
            'created_by' => $userId ?? Auth::id(),
        ]);
    }

    /**
     * Get audit trail for an entity (all events for INV-X, PAY-Y, etc.)
     */
    public static function auditTrail(string $reference): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('reference', $reference)
            ->with(['property', 'createdBy'])
            ->orderBy('created_at', 'asc')
            ->get();
    }
}
