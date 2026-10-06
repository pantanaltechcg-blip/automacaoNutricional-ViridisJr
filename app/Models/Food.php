<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'category', 'source', 'source_code'])]
class Food extends Model
{
    protected $table = 'foods';

    public function foodNutrients(): HasMany
    {
        return $this->hasMany(FoodNutrient::class);
    }
}
