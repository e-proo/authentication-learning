<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'return_item_id', 'warehouse_id', 'quantity_received',
        'disposition', 'notes', 'received_by', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_received' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function returnItem(): BelongsTo
    {
        return $this->belongsTo(ReturnItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
