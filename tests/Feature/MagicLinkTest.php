<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyViewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use App\Notifications\MagicLinkNotification;
use Tests\TestCase;

class MagicLinkTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Test Company',
            'public_code' => 'TEST-001',
            'status' => 'active',
            'contact_email' => 'admin@test.com',
            'allowed_emails' => ['viewer@test.com', 'quality@test.com'],
            'allowed_domains' => ['test.com'],
        ]);
    }

    public function test_magic_link_sent_for_allowed_email(): void
    {
        Notification::fake();

        $response = $this->post('/portal/magic-link', [
            'email' => 'viewer@test.com',
        ]);

        $response->assertRedirect('/portal/magic-link/sent');

        $viewer = CompanyViewer::where('email', 'viewer@test.com')->first();
        $this->assertNotNull($viewer);

        Notification::assertSentTo($viewer, MagicLinkNotification::class);
    }

    public function test_magic_link_rejected_for_unknown_email(): void
    {
        $response = $this->post('/portal/magic-link', [
            'email' => 'unknown@other.com',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_magic_link_works_with_company_code(): void
    {
        Notification::fake();

        $response = $this->post('/portal/magic-link', [
            'email' => 'viewer@test.com',
            'company_code' => 'TEST-001',
        ]);

        $response->assertRedirect('/portal/magic-link/sent');
    }

    public function test_magic_link_rejects_wrong_company_code(): void
    {
        $response = $this->post('/portal/magic-link', [
            'email' => 'viewer@test.com',
            'company_code' => 'WRONG-CODE',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_valid_signed_link_logs_in_viewer(): void
    {
        $viewer = CompanyViewer::create([
            'company_id' => $this->company->id,
            'email' => 'viewer@test.com',
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'portal.magic-link.verify',
            now()->addMinutes(15),
            ['viewer' => $viewer->id]
        );

        $response = $this->get($signedUrl);

        $response->assertRedirect('/portal/dashboard');
        $this->assertAuthenticatedAs($viewer, 'portal');
    }

    public function test_expired_signed_link_returns_403(): void
    {
        $viewer = CompanyViewer::create([
            'company_id' => $this->company->id,
            'email' => 'viewer@test.com',
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'portal.magic-link.verify',
            now()->subMinutes(1),  // Already expired
            ['viewer' => $viewer->id]
        );

        $response = $this->get($signedUrl);

        $response->assertStatus(403);
    }

    public function test_inactive_company_rejects_magic_link(): void
    {
        $this->company->update(['status' => 'inactive']);

        $response = $this->post('/portal/magic-link', [
            'email' => 'viewer@test.com',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
