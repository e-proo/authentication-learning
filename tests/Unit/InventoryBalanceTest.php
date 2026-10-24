<?php

namespace Tests\Unit;

use App\Models\InventoryBalance;
use PHPUnit\Framework\TestCase;

class InventoryBalanceTest extends TestCase
{
    public function test_available_quantity_excludes_reserved_stock(): void
    {
        $balance = new InventoryBalance([
            'on_hand' => 12,
            'reserved' => 5,
        ]);

        $this->assertSame(7, $balance->available);
    }

    public function test_available_quantity_never_returns_negative(): void
    {
        $balance = new InventoryBalance([
            'on_hand' => 4,
            'reserved' => 8,
        ]);

        $this->assertSame(0, $balance->available);
    }
}
