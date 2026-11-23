<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AddedSugarsEvaluationCause extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'evaluation_id',
        'evaluation_item_id',
        'cause_type',
        'cause_code',
        'detail',
    ];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(AddedSugarsEvaluation::class, 'evaluation_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AddedSugarsEvaluationItem::class, 'evaluation_item_id');
    }
}
