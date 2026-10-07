<?php

use App\Http\Controllers\FoodController;
use Illuminate\Support\Facades\Route;

Route::get('/foods', [FoodController::class, 'search']);
