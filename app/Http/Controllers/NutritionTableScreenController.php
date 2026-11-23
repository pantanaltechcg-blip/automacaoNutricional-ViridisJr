<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Recipe;
use Illuminate\Contracts\View\View;

class NutritionTableScreenController extends Controller
{
    public function show(Recipe $recipe): View
    {
        return view('rotulonutri.table', [
            'recipe' => $recipe,
        ]);
    }
}
