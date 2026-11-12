<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RegulatoryDeclarationProfile extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    public function recalculationPolicy(): HasOne
    {
        return $this->hasOne(RegulatoryRecalculationPolicy::class, 'declaration_profile_id');
    }

    public function nonSignificantRules(): HasMany
    {
        return $this->hasMany(RegulatoryNonSignificantRule::class, 'declaration_profile_id');
    }

    public function roundingRules(): HasMany
    {
        return $this->hasMany(RegulatoryRoundingRule::class, 'declaration_profile_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(RegulatoryDeclarationRequirement::class, 'declaration_profile_id');
    }
}
