<?php

use App\Http\Controllers\IngredientScreenController;
use App\Http\Controllers\LabelAssemblyScreenController;
use App\Http\Controllers\NutritionTableScreenController;
use App\Http\Controllers\RecipeScreenController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/receitas/nova', [RecipeScreenController::class, 'create'])->name('recipes.create');
Route::get('/receitas/{recipe}/tabela', [NutritionTableScreenController::class, 'show'])->name('recipes.table');
Route::get('/rotulos/{declaredNutritionCalculation}/montagem', [LabelAssemblyScreenController::class, 'show'])->name('labels.assembly');
Route::get('/ingredientes/novo', [IngredientScreenController::class, 'create'])->name('ingredients.create');
