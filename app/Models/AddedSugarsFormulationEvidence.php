<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AddedSugarsFormulationEvidence extends Model
{
    protected $table = 'added_sugars_formulation_evidences';

    public const string SOURCE_MANUFACTURER_SPEC = 'MANUFACTURER_SPEC';

    public const string SOURCE_LAB_REPORT = 'LAB_REPORT';

    public const string SOURCE_OPERATIONAL_FORM = 'OPERATIONAL_FORM';

    public const string SOURCE_NORMATIVE_REFERENCE = 'NORMATIVE_REFERENCE';

    public const string SOURCE_TECHNICAL_TABLE = 'TECHNICAL_TABLE';

    public const string SOURCE_TEST_FIXTURE = 'TEST_FIXTURE';

    /** @var list<string> */
    public const array SOURCE_TYPES = [
        self::SOURCE_MANUFACTURER_SPEC,
        self::SOURCE_LAB_REPORT,
        self::SOURCE_OPERATIONAL_FORM,
        self::SOURCE_NORMATIVE_REFERENCE,
        self::SOURCE_TECHNICAL_TABLE,
        self::SOURCE_TEST_FIXTURE,
    ];

    /** @var list<string> */
    protected $fillable = [
        'formulation_ingredient_id',
        'source_type',
        'source_name',
        'document_reference',
        'reported_amount_basis',
        'reported_amount_value',
        'reported_amount_unit',
        'only_total_sugars_reported',
        'recorded_by',
        'recorded_on',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'only_total_sugars_reported' => 'boolean',
            'recorded_on' => 'date:Y-m-d',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(AddedSugarsFormulationIngredient::class, 'formulation_ingredient_id');
    }
}
