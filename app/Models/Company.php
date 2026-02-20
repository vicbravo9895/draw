<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'public_code',
        'status',
        'timezone',
        'contact_email',
        'allowed_domains',
        'allowed_emails',
        'logo_path',
        'notes',
        'allow_exports',
    ];

    protected function casts(): array
    {
        return [
            'allowed_domains' => 'array',
            'allowed_emails' => 'array',
            'allow_exports' => 'boolean',
        ];
    }

    /* ---- Relationships ---- */

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function companyViewers(): HasMany
    {
        return $this->hasMany(CompanyViewer::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    public function defectTags(): HasMany
    {
        return $this->hasMany(DefectTag::class);
    }

    /* ---- Helpers ---- */

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if an email is allowed to access the portal for this company.
     */
    public function isEmailAllowed(string $email): bool
    {
        $email = strtolower(trim($email));

        // Check contact_email
        if ($this->contact_email && strtolower($this->contact_email) === $email) {
            return true;
        }

        // Check allowed_emails list
        if ($this->allowed_emails && in_array($email, array_map('strtolower', $this->allowed_emails))) {
            return true;
        }

        // Check allowed_domains
        if ($this->allowed_domains) {
            $domain = substr($email, strpos($email, '@') + 1);
            if (in_array(strtolower($domain), array_map('strtolower', $this->allowed_domains))) {
                return true;
            }
        }

        return false;
    }
}
