<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Food;
use App\Services\IngredientFormNutrientMapper;
use Illuminate\Contracts\View\View;

class IngredientScreenController extends Controller
{
    public function create(IngredientFormNutrientMapper $mapper): View
    {
        return view('rotulonutri.ingredient', [
            'nutrientFields' => $mapper->mapFields(),
        ]);
    }

    public function edit(Food $food, IngredientFormNutrientMapper $mapper): View
    {
        abort_if(! $food->manualRegistration()->exists(), 404);

        return view('rotulonutri.ingredient-edit', [
            'food' => $food,
            'nutrientFields' => $mapper->mapFields(),
        ]);
    }
}
