<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyViewer;
use App\Notifications\MagicLinkNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        return Inertia::render('Portal/Auth/Login');
    }

    public function sendMagicLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'company_code' => 'nullable|string',
        ]);

        $email = strtolower(trim($request->email));
        $companyCode = $request->company_code;

        // Find the company
        $company = $this->resolveCompany($email, $companyCode);

        if (! $company) {
            return back()->withErrors([
                'email' => 'No se encontró una empresa asociada a este correo.',
            ]);
        }

        if (! $company->isActive()) {
            return back()->withErrors([
                'email' => 'La empresa no está activa. Contacte al administrador.',
            ]);
        }

        // Validate the email is allowed
        if (! $company->isEmailAllowed($email)) {
            return back()->withErrors([
                'email' => 'Este correo no está autorizado para acceder al portal.',
            ]);
        }

        // Create or find the viewer
        $viewer = CompanyViewer::firstOrCreate(
            ['company_id' => $company->id, 'email' => $email],
            ['name' => null]
        );

        // Generate signed URL (15 minutes)
        $signedUrl = URL::temporarySignedRoute(
            'portal.magic-link.verify',
            now()->addMinutes(15),
            ['viewer' => $viewer->id]
        );

        // Send notification (email)
        $viewer->notify(new MagicLinkNotification($signedUrl));

        return redirect()->route('portal.magic-link.sent')
            ->with('email', $email)
            ->with('magic_link_url', $signedUrl);
    }

    public function magicLinkSent(Request $request)
    {
        return Inertia::render('Portal/Auth/MagicLinkSent', [
            'email' => session('email', ''),
            'magic_link_url' => session('magic_link_url', null),
        ]);
    }

    public function verifyMagicLink(Request $request, CompanyViewer $viewer)
    {
        // Signed middleware already validates signature + expiration
        // Additional check: ensure the company is still active
        if (! $viewer->company || ! $viewer->company->isActive()) {
            return redirect()->route('portal.login')->withErrors([
                'email' => 'La empresa no está activa.',
            ]);
        }

        // Update last login
        $viewer->update(['last_login_at' => now()]);

        // Login the viewer via the portal guard
        Auth::guard('portal')->login($viewer);
        $request->session()->regenerate();

        return redirect()->route('portal.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('portal')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }

    /**
     * Resolve the company from email or company code.
     */
    protected function resolveCompany(string $email, ?string $companyCode): ?Company
    {
        if ($companyCode) {
            $company = Company::where('public_code', $companyCode)->first();

            if ($company && $company->isEmailAllowed($email)) {
                return $company;
            }

            return null;
        }

        // Try to find company by contact_email
        $company = Company::whereRaw('LOWER(contact_email) = ?', [$email])->first();
        if ($company) {
            return $company;
        }

        // Try by allowed_emails (jsonb contains)
        $companies = Company::whereNotNull('allowed_emails')->get();
        foreach ($companies as $company) {
            if ($company->isEmailAllowed($email)) {
                return $company;
            }
        }

        // Try by domain
        $domain = strtolower(substr($email, strpos($email, '@') + 1));
        $companies = Company::whereNotNull('allowed_domains')->get();
        foreach ($companies as $company) {
            if ($company->allowed_domains && in_array($domain, array_map('strtolower', $company->allowed_domains))) {
                return $company;
            }
        }

        return null;
    }
}
