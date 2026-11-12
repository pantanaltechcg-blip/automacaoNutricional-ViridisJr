<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegulatoryRoundingRule extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function declarationProfile(): BelongsTo
    {
        return $this->belongsTo(RegulatoryDeclarationProfile::class, 'declaration_profile_id');
    }

    public function ranges(): HasMany
    {
        return $this->hasMany(RegulatoryRoundingRuleRange::class, 'rounding_rule_id')
            ->orderBy('range_order');
    }
}
