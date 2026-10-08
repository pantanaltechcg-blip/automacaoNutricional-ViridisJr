<?php

use App\Http\Controllers\FoodController;
use App\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;

Route::get('/foods', [FoodController::class, 'search']);
Route::post('/recipes', [RecipeController::class, 'store']);
Route::put('/recipes/{recipe}', [RecipeController::class, 'update']);
