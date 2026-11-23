<?php

use App\Http\Controllers\IngredientScreenController;
use App\Http\Controllers\LabelAssemblyScreenController;
use App\Http\Controllers\NutritionCalculationScreenController;
use App\Http\Controllers\NutritionTableScreenController;
use App\Http\Controllers\RecipeScreenController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/receitas/nova', [RecipeScreenController::class, 'create'])->name('recipes.create');
Route::get('/receitas/{recipe}', [RecipeScreenController::class, 'edit'])->whereNumber('recipe')->name('recipes.edit');
Route::get('/receitas/{recipe}/tabela', [NutritionTableScreenController::class, 'show'])->whereNumber('recipe')->name('recipes.table');
Route::get('/calculos/{calculation}', [NutritionCalculationScreenController::class, 'show'])->whereNumber('calculation')->name('calculations.show');
Route::get('/rotulos/{declaredNutritionCalculation}/montagem', [LabelAssemblyScreenController::class, 'show'])->whereNumber('declaredNutritionCalculation')->name('labels.assembly');
Route::get('/ingredientes/novo', [IngredientScreenController::class, 'create'])->name('ingredients.create');
Route::get('/ingredientes/{food}', [IngredientScreenController::class, 'edit'])->whereNumber('food')->name('ingredients.edit');
