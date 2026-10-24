<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'order_id', 'product_variant_id', 'warehouse_id',
        'product_name_snapshot', 'sku_snapshot', 'variant_name_snapshot',
        'currency', 'unit_price_minor', 'quantity', 'discount_minor',
        'tax_minor', 'line_total_minor',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_minor' => 'integer',
            'quantity' => 'integer',
            'discount_minor' => 'integer',
            'tax_minor' => 'integer',
            'line_total_minor' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function reservation(): HasOne
    {
        return $this->hasOne(StockReservation::class);
    }

    public function fulfillmentItem(): HasOne
    {
        return $this->hasOne(FulfillmentItem::class);
    }
}
