<?php

namespace App\Services;

use App\Models\CheckItem;
use App\Models\Ingredient;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Deduct ingredients from stock for a served check item.
     *
     * Deducts both the base recipe (MenuItem → RecipeItems) and the ingredient
     * lines defined on each selected modifier option. All deductions are scaled
     * by the item's quantity (e.g., 2 burgers → 2× everything).
     *
     * Returns ingredients that hit low stock or went negative after deduction.
     * Each entry: ['name', 'quantity_on_hand', 'minimum_stock', 'unit', 'negative']
     */
    public function deductForItem(CheckItem $item): array
    {
        $item->loadMissing([
            'menuItem.recipeItems.ingredient',
            'modifiers.option.ingredientLines.ingredient',
        ]);

        $recipe    = $item->menuItem?->recipeItems ?? collect();
        $modifiers = $item->modifiers ?? collect();

        $lowStock = [];

        DB::transaction(function () use ($item, $recipe, $modifiers, &$lowStock) {
            foreach ($recipe as $line) {
                $this->deductIngredient(
                    $line->ingredient,
                    $line->quantity_used * $item->quantity,
                    $lowStock,
                );
            }

            foreach ($modifiers as $modifier) {
                foreach ($modifier->option?->ingredientLines ?? [] as $line) {
                    $this->deductIngredient(
                        $line->ingredient,
                        $line->quantity_used * $item->quantity,
                        $lowStock,
                    );
                }
            }
        });

        return $lowStock;
    }

    private function deductIngredient(Ingredient $ingredient, float $deduction, array &$lowStock): void
    {
        $ingredient->decrement('quantity_on_hand', $deduction);
        $ingredient->refresh();

        if ($ingredient->quantity_on_hand <= $ingredient->minimum_stock) {
            $lowStock[] = [
                'name'             => $ingredient->name,
                'quantity_on_hand' => (float) $ingredient->quantity_on_hand,
                'minimum_stock'    => (float) $ingredient->minimum_stock,
                'unit'             => $ingredient->unit,
                'negative'         => $ingredient->quantity_on_hand < 0,
            ];
        }
    }
}
