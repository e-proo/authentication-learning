<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'warehouse_id', 'product_variant_id', 'movement_type', 'quantity_delta',
        'reference_type', 'reference_id', 'idempotency_key', 'metadata', 'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
