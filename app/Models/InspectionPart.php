<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionPart extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'inspection_id',
        'part_number',
        'comment_part',
        'order',
    ];

    /* ---- Relationships ---- */

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InspectionItem::class);
    }

    /* ---- Computed ---- */

    public function getPartTotalGoodAttribute(): int
    {
        return $this->items->sum('good_qty');
    }

    public function getPartTotalDefectsAttribute(): int
    {
        return $this->items->sum('defects_qty');
    }

    public function getPartTotalAttribute(): int
    {
        return $this->part_total_good + $this->part_total_defects;
    }

    public function getPartDefectRateAttribute(): float
    {
        $total = $this->part_total;

        if ($total === 0) {
            return 0;
        }

        return round(($this->part_total_defects / $total) * 100, 2);
    }
}
