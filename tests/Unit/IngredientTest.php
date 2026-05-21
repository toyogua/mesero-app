<?php

namespace Tests\Unit;

use App\Models\Ingredient;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IngredientTest extends TestCase
{
    #[Test]
    public function is_low_stock_when_at_or_below_minimum(): void
    {
        $ing = new Ingredient([
            'quantity_on_hand' => 1.5,
            'minimum_stock' => 2.0,
        ]);
        $this->assertTrue($ing->isLowStock());

        $ing->quantity_on_hand = 2.0;
        $this->assertTrue($ing->isLowStock()); // at the limit = still low

        $ing->quantity_on_hand = 2.0001;
        $this->assertFalse($ing->isLowStock());
    }
}
