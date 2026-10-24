<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'fulfillment_id', 'order_item_id', 'quantity',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
