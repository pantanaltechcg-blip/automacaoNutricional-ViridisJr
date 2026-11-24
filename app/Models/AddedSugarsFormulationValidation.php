<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AddedSugarsFormulationValidation extends Model
{
    public const string STATUS_AUTHORIZED = 'AUTHORIZED';

    public const string STATUS_REJECTED = 'REJECTED';

    public const string STATUS_REVOKED = 'REVOKED';

    /** @var list<string> */
    public const array DECISION_STATUSES = [
        self::STATUS_AUTHORIZED,
        self::STATUS_REJECTED,
        self::STATUS_REVOKED,
    ];

    public const string EVIDENCE_DOCUMENT = 'DOCUMENT';

    public const string EVIDENCE_TECHNICAL_REVIEW = 'TECHNICAL_REVIEW';

    public const string EVIDENCE_TEST_FIXTURE = 'TEST_FIXTURE';

    /** @var list<string> */
    public const array EVIDENCE_TYPES = [
        self::EVIDENCE_DOCUMENT,
        self::EVIDENCE_TECHNICAL_REVIEW,
        self::EVIDENCE_TEST_FIXTURE,
    ];

    /** @var list<string> */
    protected $fillable = [
        'formulation_version_id',
        'decision_status',
        'normative_source',
        'normative_version',
        'evidence_type',
        'evidence_reference',
        'evidence_source',
        'evidence_version',
        'evidence_scope',
        'validated_by',
        'validated_on',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'validated_on' => 'date:Y-m-d',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(AddedSugarsFormulationVersion::class, 'formulation_version_id');
    }
}
