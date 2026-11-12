<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegulatoryRecalculationPolicy extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function declarationProfile(): BelongsTo
    {
        return $this->belongsTo(RegulatoryDeclarationProfile::class, 'declaration_profile_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'requires_complete_energy_inputs' => 'boolean',
        ];
    }
}
