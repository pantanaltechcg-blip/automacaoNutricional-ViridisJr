<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class RecipeScreenController extends Controller
{
    public function create(): View
    {
        return view('rotulonutri.recipe');
    }
}
