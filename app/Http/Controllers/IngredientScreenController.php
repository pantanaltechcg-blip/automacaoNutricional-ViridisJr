<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
}
