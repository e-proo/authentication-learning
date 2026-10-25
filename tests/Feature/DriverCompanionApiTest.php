<?php

namespace Tests\Feature;

use App\Models\DeliveryTask;
use App\Models\DriverShift;
use App\Models\FleetDriver;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverCompanionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_only_list_and_open_tasks_assigned_to_a_linked_driver_profile(): void
    {
        $organization = $this->makeOrganization();
        $driverUser = $this->makeUser('driver@example.test');
        $otherUser = $this->makeUser('other-driver@example.test');
        $driver = $this->makeDriver($organization, $driverUser, 'DRV-001');
        $otherDriver = $this->makeDriver($organization, $otherUser, 'DRV-002');

        $ownTask = $this->makeTask($organization, $driver, 'TASK-OWN-001');
        $privateTask = $this->makeTask($organization, $otherDriver, 'TASK-OTHER-001');

        $this->actingAs($driverUser, 'sanctum')
            ->getJson('/api/v1/driver/me')
            ->assertOk()
            ->assertJsonPath('data.driver_profiles.0.driver_code', 'DRV-001')
            ->assertJsonPath('data.driver_profiles.0.status', 'active');

        $this->getJson('/api/v1/driver/tasks')
            ->assertOk()
            ->assertJsonFragment(['task_number' => 'TASK-OWN-001'])
            ->assertJsonMissing(['task_number' => 'TASK-OTHER-001']);

        $this->getJson('/api/v1/driver/tasks/'.$ownTask->id)
            ->assertOk()
            ->assertJsonPath('data.task_number', 'TASK-OWN-001');

        $this->getJson('/api/v1/driver/tasks/'.$privateTask->id)
            ->assertNotFound();
    }

    public function test_driver_task_updates_follow_the_task_state_machine_and_require_custody_evidence(): void
    {
        $organization = $this->makeOrganization();
        $driverUser = $this->makeUser('state-driver@example.test');
        $driver = $this->makeDriver($organization, $driverUser, 'DRV-STATE');
        $task = $this->makeTask($organization, $driver, 'TASK-STATE-001');

        $this->actingAs($driverUser, 'sanctum')
            ->postJson('/api/v1/driver/tasks/'.$task->id.'/events', [
                'status' => 'en_route',
                'metadata' => [
                    'source' => 'driver_app',
                    'client_event_id' => '4a21b9c3-fb0c-4755-bf4b-a6a25f0db229',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'en_route');

        $this->postJson('/api/v1/driver/tasks/'.$task->id.'/events', [
            'status' => 'arrived',
        ])->assertOk()->assertJsonPath('data.status', 'arrived');

        $this->postJson('/api/v1/driver/tasks/'.$task->id.'/events', [
            'status' => 'completed',
        ])->assertUnprocessable()->assertJsonValidationErrors(['proof_reference']);

        $this->postJson('/api/v1/driver/tasks/'.$task->id.'/events', [
            'status' => 'completed',
            'proof_reference' => 'scan-receipt-001',
            'notes' => 'Parcel handed to authorized receiver.',
        ])->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('delivery_task_events', [
            'organization_id' => $organization->id,
            'delivery_task_id' => $task->id,
            'status' => 'completed',
        ]);
    }

    public function test_driver_can_start_and_end_a_shift_without_a_fleet_vehicle_when_the_role_allows_it(): void
    {
        $organization = $this->makeOrganization();
        $driverUser = $this->makeUser('shift-driver@example.test');
        $driver = $this->makeDriver($organization, $driverUser, 'DRV-SHIFT');

        $started = $this->actingAs($driverUser, 'sanctum')->postJson('/api/v1/driver/shifts/start', [
            'driver_profile_id' => $driver->id,
            'location' => 'branch:SANAA-01',
            'device_id' => 'test-device-01',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'active');

        $shiftId = $started->json('data.id');

        $this->getJson('/api/v1/driver/shifts/current?driver_profile_id='.$driver->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $shiftId)
            ->assertJsonPath('data.0.driver.id', $driver->id);

        $this->postJson('/api/v1/driver/shifts/'.$shiftId.'/end', [
            'location' => 'branch:SANAA-01',
            'device_id' => 'test-device-01',
            'notes' => 'Shift ended without a company vehicle.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'ended');

        $this->assertDatabaseHas('driver_shifts', [
            'id' => $shiftId,
            'driver_id' => $driver->id,
            'status' => 'ended',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'driver_shift.started',
            'subject_id' => (string) $shiftId,
        ]);
    }

    public function test_driver_cannot_start_a_second_active_shift(): void
    {
        $organization = $this->makeOrganization();
        $driverUser = $this->makeUser('one-shift-driver@example.test');
        $driver = $this->makeDriver($organization, $driverUser, 'DRV-ONE-SHIFT');

        $this->actingAs($driverUser, 'sanctum')->postJson('/api/v1/driver/shifts/start', [
            'driver_profile_id' => $driver->id,
        ])->assertCreated();

        $this->postJson('/api/v1/driver/shifts/start', [
            'driver_profile_id' => $driver->id,
        ])->assertConflict();

        $this->assertSame(
            1,
            DriverShift::query()->where('driver_id', $driver->id)->where('status', 'active')->count(),
        );
    }

    private function makeOrganization(): Organization
    {
        static $index = 0;
        $index++;

        return Organization::query()->create([
            'name' => 'Driver Test Organization '.$index,
            'slug' => 'driver-test-organization-'.$index,
            'kind' => 'business',
            'commerce_mode' => 'marketplace',
            'is_active' => true,
        ]);
    }

    private function makeUser(string $email): User
    {
        return User::query()->create([
            'name' => 'Driver Test User',
            'email' => $email,
            'password' => 'testing-password-123',
            'is_active' => true,
        ]);
    }

    private function makeDriver(Organization $organization, User $user, string $code): FleetDriver
    {
        return FleetDriver::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'user_id' => $user->id,
            'driver_code' => $code,
            'display_name' => $user->name,
            'phone' => null,
            'driver_type' => 'employee',
            'status' => 'active',
            'license_number' => 'LIC-'.$code,
            'license_expires_at' => today()->addYear()->toDateString(),
        ]);
    }

    private function makeTask(Organization $organization, FleetDriver $driver, string $taskNumber): DeliveryTask
    {
        return DeliveryTask::query()->create([
            'organization_id' => $organization->id,
            'shipment_id' => null,
            'return_case_id' => null,
            'transport_trip_id' => null,
            'driver_id' => $driver->id,
            'task_number' => $taskNumber,
            'task_type' => 'collection',
            'status' => 'assigned',
            'sequence_number' => 1,
            'location_snapshot' => [
                'address' => 'Test address',
                'city' => 'Sanaa',
                'landmark' => 'Test landmark',
            ],
            'recipient_name' => 'Test recipient',
            'recipient_phone' => '+967700000000',
            'attempt_number' => 1,
            'assigned_at' => now(),
            'notes' => null,
            'metadata' => null,
        ]);
    }
}
