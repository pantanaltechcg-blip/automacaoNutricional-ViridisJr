<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AddedSugarsFormulationVersion extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'formulation_id',
        'recipe_id',
        'version_number',
        'normative_source',
        'normative_version',
        'registered_by',
        'registered_on',
        'change_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'registered_on' => 'date:Y-m-d',
        ];
    }

    public function formulation(): BelongsTo
    {
        return $this->belongsTo(AddedSugarsFormulation::class, 'formulation_id');
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(AddedSugarsFormulationIngredient::class, 'formulation_version_id')
            ->orderBy('position');
    }

    public function validations(): HasMany
    {
        return $this->hasMany(AddedSugarsFormulationValidation::class, 'formulation_version_id')
            ->orderBy('id');
    }
}
