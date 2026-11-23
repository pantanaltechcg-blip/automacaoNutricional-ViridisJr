<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NutritionCalculation;
use Illuminate\Contracts\View\View;

class NutritionCalculationScreenController extends Controller
{
    public function show(NutritionCalculation $calculation): View
    {
        return view('rotulonutri.calculation', [
            'calculation' => $calculation,
        ]);
    }
}
