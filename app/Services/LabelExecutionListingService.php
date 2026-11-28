<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeclaredNutritionCalculation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LabelExecutionListingService
{
    public function paginate(): LengthAwarePaginator
    {
        return DeclaredNutritionCalculation::query()
            ->with('causes')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25);
    }
}
