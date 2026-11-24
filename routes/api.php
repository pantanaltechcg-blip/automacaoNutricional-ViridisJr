<?php

use App\Http\Controllers\AddedSugarsEvaluationController;
use App\Http\Controllers\AddedSugarsFormulationController;
use App\Http\Controllers\DeclaredNutritionCalculationController;
use App\Http\Controllers\FoodController;
use App\Http\Controllers\ManualFoodRegistrationController;
use App\Http\Controllers\NutritionCalculationController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ServingDailyValueCalculationController;
use Illuminate\Support\Facades\Route;

Route::get('/foods', [FoodController::class, 'search']);
Route::post('/manual-foods', [ManualFoodRegistrationController::class, 'store']);
Route::get('/manual-foods/{food}', [ManualFoodRegistrationController::class, 'show'])->whereNumber('food');
Route::put('/manual-foods/{food}', [ManualFoodRegistrationController::class, 'update'])->whereNumber('food');
Route::patch('/manual-foods/{food}/status', [ManualFoodRegistrationController::class, 'updateStatus'])->whereNumber('food');
Route::post('/recipes', [RecipeController::class, 'store']);
Route::get('/recipes/{recipe}', [RecipeController::class, 'show']);
Route::put('/recipes/{recipe}', [RecipeController::class, 'update']);
Route::post('/recipes/{recipe}/nutrition-calculations', [NutritionCalculationController::class, 'store']);
Route::get('/nutrition-calculations/{calculation}', [NutritionCalculationController::class, 'show']);
Route::post('/nutrition-calculations/{nutritionCalculation}/serving-daily-value-calculations', [ServingDailyValueCalculationController::class, 'store']);
Route::get('/serving-daily-value-calculations/{servingDailyValueCalculation}', [ServingDailyValueCalculationController::class, 'show']);
Route::post('/serving-daily-value-calculations/{servingDailyValueCalculation}/declared-nutrition-calculations', [DeclaredNutritionCalculationController::class, 'store']);
Route::get('/declared-nutrition-calculations/{declaredNutritionCalculation}', [DeclaredNutritionCalculationController::class, 'show']);
Route::post('/recipes/{recipe}/added-sugars-formulations', [AddedSugarsFormulationController::class, 'store']);
Route::get('/recipes/{recipe}/added-sugars-formulations', [AddedSugarsFormulationController::class, 'show']);
Route::post('/added-sugars-formulation-versions/{version}/validations', [AddedSugarsFormulationController::class, 'storeValidation']);
Route::post('/nutrition-calculations/{nutritionCalculation}/added-sugars-evaluations', [AddedSugarsEvaluationController::class, 'store']);
Route::get('/added-sugars-evaluations/{addedSugarsEvaluation}', [AddedSugarsEvaluationController::class, 'show']);
