<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id',
        'employee_number',
        'username',
        'phone',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /* ---- Relationships ---- */

    /** @deprecated Employees are not "of" a company; use companies() for assigned companies. */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Companies this employee is assigned to work with (backoffice scope). */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'user_companies')->withTimestamps();
    }

    public function hasAccessToCompany(int $companyId): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        return $this->companies()->where('companies.id', $companyId)->exists();
    }

    /** Company IDs this employee can access (empty = none). Super_admin is handled in queries by not filtering. */
    public function companyIds(): array
    {
        if ($this->hasRole('super_admin')) {
            return [];
        }

        return $this->companies()->pluck('companies.id')->all();
    }

    public function scheduledInspections(): HasMany
    {
        return $this->hasMany(Inspection::class, 'scheduled_by');
    }

    public function assignedInspections(): HasMany
    {
        return $this->hasMany(Inspection::class, 'assigned_inspector_id');
    }

    /** Inspections where this user is one of the assigned inspectors (pivot). */
    public function inspectionsAsInspector(): BelongsToMany
    {
        return $this->belongsToMany(Inspection::class, 'inspection_inspector')->withTimestamps();
    }

    /* ---- Helpers ---- */

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
