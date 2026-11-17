<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegulatoryRoundingRuleRange extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function roundingRule(): BelongsTo
    {
        return $this->belongsTo(RegulatoryRoundingRule::class, 'rounding_rule_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'range_order' => 'integer',
            'min_inclusive' => 'boolean',
            'max_inclusive' => 'boolean',
            'decimal_places' => 'integer',
        ];
    }
}
