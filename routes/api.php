<?php

use App\Http\Controllers\FoodController;
use App\Http\Controllers\NutritionCalculationController;
use App\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;

Route::get('/foods', [FoodController::class, 'search']);
Route::post('/recipes', [RecipeController::class, 'store']);
Route::get('/recipes/{recipe}', [RecipeController::class, 'show']);
Route::put('/recipes/{recipe}', [RecipeController::class, 'update']);
Route::post('/recipes/{recipe}/nutrition-calculations', [NutritionCalculationController::class, 'store']);
Route::get('/nutrition-calculations/{calculation}', [NutritionCalculationController::class, 'show']);
