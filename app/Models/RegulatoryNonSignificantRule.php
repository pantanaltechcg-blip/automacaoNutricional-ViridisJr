<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegulatoryNonSignificantRule extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function declarationProfile(): BelongsTo
    {
        return $this->belongsTo(RegulatoryDeclarationProfile::class, 'declaration_profile_id');
    }
}
