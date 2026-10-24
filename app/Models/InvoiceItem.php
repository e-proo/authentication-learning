<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'invoice_id', 'order_item_id', 'description_snapshot',
        'sku_snapshot', 'currency', 'unit_price_minor', 'quantity', 'discount_minor',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
