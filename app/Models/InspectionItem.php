<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionItem extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'inspection_part_id',
        'serial_number',
        'lot_date',
        'good_qty',
        'defects_qty',
    ];

    protected function casts(): array
    {
        return [
            'good_qty' => 'integer',
            'defects_qty' => 'integer',
        ];
    }

    /* ---- Relationships ---- */

    public function inspectionPart(): BelongsTo
    {
        return $this->belongsTo(InspectionPart::class);
    }

    /* ---- Computed ---- */

    public function getTotalQtyAttribute(): int
    {
        return $this->good_qty + $this->defects_qty;
    }
}
