<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['food_id', 'is_active', 'current_version_number', 'reference_unit'])]
class ManualFoodRegistration extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'reference_unit' => 'g',
    ];

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ManualFoodVersion::class)->orderBy('version_number');
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(ManualFoodVersion::class)->ofMany('version_number', 'max');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'current_version_number' => 'integer',
        ];
    }
}
