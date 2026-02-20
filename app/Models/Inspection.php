<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inspection extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'date',
        'shift',
        'project',
        'area_line',
        'scheduled_by',
        'assigned_inspector_id',
        'start_time',
        'end_time',
        'comment_general',
        'status',
        'reference_code',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /* ---- Relationships ---- */

    public function scheduledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    public function assignedInspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_inspector_id');
    }

    /** Multiple inspectors assigned to this inspection (including the lead). */
    public function inspectors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'inspection_inspector')->withTimestamps();
    }

    public function parts(): HasMany
    {
        return $this->hasMany(InspectionPart::class)->orderBy('order');
    }

    /* ---- Computed ---- */

    public function getTotalGoodAttribute(): int
    {
        return $this->parts->sum(fn ($part) => $part->items->sum('good_qty'));
    }

    public function getTotalDefectsAttribute(): int
    {
        return $this->parts->sum(fn ($part) => $part->items->sum('defects_qty'));
    }

    public function getTotalAttribute(): int
    {
        return $this->total_good + $this->total_defects;
    }

    public function getDefectRateAttribute(): float
    {
        if ($this->total === 0) {
            return 0;
        }

        return round(($this->total_defects / $this->total) * 100, 2);
    }

    /* ---- Helpers ---- */

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Generate a unique reference code for this company.
     */
    public static function generateReferenceCode(int $companyId): string
    {
        $prefix = 'INS';
        $date = now()->format('Ymd');
        $count = static::where('company_id', $companyId)
            ->whereDate('created_at', today())
            ->withTrashed()
            ->count() + 1;

        return sprintf('%s-%s-%04d', $prefix, $date, $count);
    }
}
