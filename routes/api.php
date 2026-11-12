<?php

use App\Http\Controllers\DeclaredNutritionCalculationController;
use App\Http\Controllers\FoodController;
use App\Http\Controllers\NutritionCalculationController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ServingDailyValueCalculationController;
use Illuminate\Support\Facades\Route;

Route::get('/foods', [FoodController::class, 'search']);
Route::post('/recipes', [RecipeController::class, 'store']);
Route::get('/recipes/{recipe}', [RecipeController::class, 'show']);
Route::put('/recipes/{recipe}', [RecipeController::class, 'update']);
Route::post('/recipes/{recipe}/nutrition-calculations', [NutritionCalculationController::class, 'store']);
Route::get('/nutrition-calculations/{calculation}', [NutritionCalculationController::class, 'show']);
Route::post('/nutrition-calculations/{nutritionCalculation}/serving-daily-value-calculations', [ServingDailyValueCalculationController::class, 'store']);
Route::get('/serving-daily-value-calculations/{servingDailyValueCalculation}', [ServingDailyValueCalculationController::class, 'show']);
Route::post('/serving-daily-value-calculations/{servingDailyValueCalculation}/declared-nutrition-calculations', [DeclaredNutritionCalculationController::class, 'store']);
Route::get('/declared-nutrition-calculations/{declaredNutritionCalculation}', [DeclaredNutritionCalculationController::class, 'show']);
