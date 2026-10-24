<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceListItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'price_list_id', 'product_variant_id', 'unit_price_minor',
        'compare_at_price_minor', 'minimum_quantity',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_minor' => 'integer',
            'compare_at_price_minor' => 'integer',
            'minimum_quantity' => 'integer',
        ];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
