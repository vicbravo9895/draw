<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BackofficePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $inspector;

    protected User $auditor;

    protected User $admin;

    protected Inspection $inspection;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $permissions = [
            'inspections.view', 'inspections.create', 'inspections.edit',
            'inspections.complete', 'inspections.delete',
            'companies.view', 'companies.manage',
            'users.view', 'users.manage',
            'defect_tags.view', 'defect_tags.manage',
            'exports.pdf', 'exports.csv',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $inspectorRole = Role::firstOrCreate(['name' => 'inspector', 'guard_name' => 'web']);
        $inspectorRole->givePermissionTo(['inspections.view', 'inspections.create', 'inspections.edit']);

        $auditorRole = Role::firstOrCreate(['name' => 'auditor_interno', 'guard_name' => 'web']);
        $auditorRole->givePermissionTo(['inspections.view', 'exports.pdf', 'exports.csv']);

        $adminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo(Permission::all());

        $this->company = Company::create([
            'name' => 'Test Co',
            'public_code' => 'TEST-001',
            'status' => 'active',
        ]);

        $this->inspector = User::factory()->create(['company_id' => $this->company->id, 'email_verified_at' => now()]);
        $this->inspector->assignRole('inspector');

        $this->auditor = User::factory()->create(['company_id' => $this->company->id, 'email_verified_at' => now()]);
        $this->auditor->assignRole('auditor_interno');

        $this->admin = User::factory()->create(['company_id' => null, 'email_verified_at' => now()]);
        $this->admin->assignRole('super_admin');

        $this->inspection = Inspection::create([
            'company_id' => $this->company->id,
            'date' => now(),
            'scheduled_by' => $this->inspector->id,
            'assigned_inspector_id' => $this->inspector->id,
            'status' => 'in_progress',
            'reference_code' => 'INS-TEST-001',
        ]);
    }

    public function test_inspector_can_view_inspections(): void
    {
        $response = $this->actingAs($this->inspector)
            ->get('/app/inspections');

        $response->assertStatus(200);
    }

    public function test_inspector_can_create_inspection(): void
    {
        $response = $this->actingAs($this->inspector)
            ->get('/app/inspections/create');

        $response->assertStatus(200);
    }

    public function test_auditor_cannot_create_inspection(): void
    {
        $response = $this->actingAs($this->auditor)
            ->get('/app/inspections/create');

        $response->assertStatus(403);
    }

    public function test_auditor_can_view_inspections(): void
    {
        $response = $this->actingAs($this->auditor)
            ->get('/app/inspections');

        $response->assertStatus(200);
    }

    public function test_super_admin_can_access_everything(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/app/inspections');

        $response->assertStatus(200);

        $response = $this->actingAs($this->admin)
            ->get('/app/inspections/create');

        $response->assertStatus(200);
    }

    public function test_auditor_cannot_edit_inspection(): void
    {
        $response = $this->actingAs($this->auditor)
            ->get("/app/inspections/{$this->inspection->id}/edit");

        $response->assertStatus(403);
    }

    public function test_inspector_can_edit_own_inspection(): void
    {
        $response = $this->actingAs($this->inspector)
            ->get("/app/inspections/{$this->inspection->id}/edit");

        $response->assertStatus(200);
    }
}
