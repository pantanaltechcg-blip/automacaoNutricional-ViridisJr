<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AddedSugarsFormulation extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'recipe_id',
        'current_version_number',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'current_version_number' => 'integer',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AddedSugarsFormulationVersion::class, 'formulation_id')
            ->orderBy('version_number');
    }
}
