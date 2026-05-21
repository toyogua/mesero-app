<?php

namespace App\Services;

use App\Models\CheckItem;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Deduct recipe ingredients from stock for a served check item.
     * Multiplies each recipe quantity by the item's quantity (e.g., 2 burgers → 2× ingredients).
     * Skips silently if the menu item has no recipe (no-BOM items still serve normally).
     */
    public function deductForItem(CheckItem $item): void
    {
        $item->loadMissing('menuItem.recipeItems.ingredient');

        $recipe = $item->menuItem?->recipeItems ?? collect();

        if ($recipe->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($item, $recipe) {
            foreach ($recipe as $line) {
                $deduction = $line->quantity_used * $item->quantity;
                $line->ingredient->decrement('quantity_on_hand', $deduction);
            }
        });
    }
}
