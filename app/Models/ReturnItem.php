<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'return_case_id', 'order_item_id',
        'requested_quantity', 'reason',
    ];

    protected function casts(): array
    {
        return ['requested_quantity' => 'integer'];
    }

    public function returnCase(): BelongsTo
    {
        return $this->belongsTo(ReturnCase::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(ReturnReceipt::class);
    }
}
