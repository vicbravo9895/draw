<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyViewer;
use App\Models\Inspection;
use App\Models\InspectionItem;
use App\Models\InspectionPart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;

    protected Company $companyB;

    protected CompanyViewer $viewerA;

    protected CompanyViewer $viewerB;

    protected Inspection $inspectionA;

    protected Inspection $inspectionB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create([
            'name' => 'Company A',
            'public_code' => 'COMP-A',
            'status' => 'active',
            'contact_email' => 'admin@comp-a.com',
            'allowed_emails' => ['viewer@comp-a.com'],
        ]);

        $this->companyB = Company::create([
            'name' => 'Company B',
            'public_code' => 'COMP-B',
            'status' => 'active',
            'contact_email' => 'admin@comp-b.com',
            'allowed_emails' => ['viewer@comp-b.com'],
        ]);

        $userA = User::factory()->create(['company_id' => $this->companyA->id, 'email_verified_at' => now()]);
        $userB = User::factory()->create(['company_id' => $this->companyB->id, 'email_verified_at' => now()]);

        $this->viewerA = CompanyViewer::create(['company_id' => $this->companyA->id, 'email' => 'viewer@comp-a.com']);
        $this->viewerB = CompanyViewer::create(['company_id' => $this->companyB->id, 'email' => 'viewer@comp-b.com']);

        $this->inspectionA = Inspection::create([
            'company_id' => $this->companyA->id,
            'date' => now(),
            'scheduled_by' => $userA->id,
            'status' => 'completed',
            'reference_code' => 'INS-A-001',
        ]);

        $partA = InspectionPart::create([
            'company_id' => $this->companyA->id,
            'inspection_id' => $this->inspectionA->id,
            'part_number' => 'PA-001',
            'order' => 1,
        ]);

        InspectionItem::create([
            'company_id' => $this->companyA->id,
            'inspection_part_id' => $partA->id,
            'good_qty' => 100,
            'defects_qty' => 5,
        ]);

        $this->inspectionB = Inspection::create([
            'company_id' => $this->companyB->id,
            'date' => now(),
            'scheduled_by' => $userB->id,
            'status' => 'completed',
            'reference_code' => 'INS-B-001',
        ]);
    }

    public function test_viewer_a_can_see_own_inspections(): void
    {
        $response = $this->actingAs($this->viewerA, 'portal')
            ->get('/portal/inspections');

        $response->assertStatus(200);
    }

    public function test_viewer_a_can_see_own_inspection_detail(): void
    {
        $response = $this->actingAs($this->viewerA, 'portal')
            ->get("/portal/inspections/{$this->inspectionA->id}");

        $response->assertStatus(200);
    }

    public function test_viewer_a_cannot_see_company_b_inspection(): void
    {
        $response = $this->actingAs($this->viewerA, 'portal')
            ->get("/portal/inspections/{$this->inspectionB->id}");

        $response->assertStatus(403);
    }

    public function test_viewer_b_cannot_see_company_a_inspection(): void
    {
        $response = $this->actingAs($this->viewerB, 'portal')
            ->get("/portal/inspections/{$this->inspectionA->id}");

        $response->assertStatus(403);
    }

    public function test_viewer_cannot_export_pdf_of_other_company(): void
    {
        $response = $this->actingAs($this->viewerA, 'portal')
            ->get("/portal/inspections/{$this->inspectionB->id}/export-pdf");

        $response->assertStatus(403);
    }

    public function test_unauthenticated_portal_redirects_to_login(): void
    {
        $response = $this->get('/portal/dashboard');

        $response->assertRedirect('/portal/login');
    }
}
