<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DeclaredNutritionCalculation;
use Illuminate\Contracts\View\View;

class LabelAssemblyScreenController extends Controller
{
    public function show(DeclaredNutritionCalculation $declaredNutritionCalculation): View
    {
        $declaredNutritionCalculation->loadMissing('servingDailyValueCalculation');
        $recipeName = $declaredNutritionCalculation->servingDailyValueCalculation?->nutritionCalculation?->recipe?->name
            ?? '[Nome do produto]';

        return view('rotulonutri.label', [
            'declaredCalculation' => $declaredNutritionCalculation,
            'productName' => $recipeName,
        ]);
    }
}
