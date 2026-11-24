<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AddedSugarsFormulationIngredient extends Model
{
    public const string TYPE_SIMPLE = 'SIMPLE';

    public const string TYPE_COMPOUND_INDUSTRIALIZED = 'COMPOUND_INDUSTRIALIZED';

    /** @var list<string> */
    public const array TYPES = [
        self::TYPE_SIMPLE,
        self::TYPE_COMPOUND_INDUSTRIALIZED,
    ];

    public const string CLASSIFICATION_INCLUDED = 'INCLUDED';

    public const string CLASSIFICATION_EXCLUDED_NORMATIVE = 'EXCLUDED_NORMATIVE';

    public const string CLASSIFICATION_MISSING = 'MISSING';

    public const string CLASSIFICATION_CONFLICTING = 'CONFLICTING';

    /** @var list<string> */
    public const array CLASSIFICATIONS = [
        self::CLASSIFICATION_INCLUDED,
        self::CLASSIFICATION_EXCLUDED_NORMATIVE,
        self::CLASSIFICATION_MISSING,
        self::CLASSIFICATION_CONFLICTING,
    ];

    public const string BASIS_PER_100G = 'PER_100G';

    public const string BASIS_DIRECT_BATCH_GRAMS = 'DIRECT_BATCH_GRAMS';

    /** @var list<string> */
    public const array AMOUNT_BASES = [
        self::BASIS_PER_100G,
        self::BASIS_DIRECT_BATCH_GRAMS,
    ];

    /** @var list<string> */
    protected $fillable = [
        'formulation_version_id',
        'food_id',
        'position',
        'ingredient_type',
        'classification',
        'amount_basis',
        'amount_value',
        'amount_unit',
        'exclusion_justification',
        'conflict_notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(AddedSugarsFormulationVersion::class, 'formulation_version_id');
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(AddedSugarsFormulationEvidence::class, 'formulation_ingredient_id')
            ->orderBy('id');
    }
}
