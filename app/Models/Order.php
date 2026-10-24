<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'store_id', 'price_list_id', 'customer_user_id',
        'buyer_organization_id', 'placed_by', 'order_number', 'status',
        'payment_status', 'currency', 'subtotal_minor', 'discount_minor',
        'shipping_minor', 'tax_minor', 'total_minor', 'shipping_address_snapshot',
        'billing_address_snapshot', 'idempotency_key', 'request_fingerprint',
        'customer_note', 'placed_at', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'shipping_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'shipping_address_snapshot' => 'array',
            'billing_address_snapshot' => 'array',
            'placed_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    public function fulfillments(): HasMany
    {
        return $this->hasMany(Fulfillment::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function returnCases(): HasMany
    {
        return $this->hasMany(ReturnCase::class);
    }
}
