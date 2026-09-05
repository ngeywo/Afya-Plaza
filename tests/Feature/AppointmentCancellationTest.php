<?php

namespace Tests\Feature;

use App\Models\Appointment;
use Laravel\Sanctum\Sanctum;

/**
 * Phase 17: Cancellation authorization (Sections 11, 12, 46).
 * Historical records are preserved; slots are released; notifications fire once.
 */
class AppointmentCancellationTest extends AppointmentTestCase
{
    public function test_patient_can_cancel_own_appointment(): void
    {
        $aptId = $this->book($this->patient, '09:00')->json('data.id');

        Sanctum::actingAs($this->patient);
        $this->deleteJson("/api/appointments/{$aptId}", ['cancellation_reason' => 'Change of plans'])
            ->assertStatus(200);

        // Historical record preserved — cancelled, not deleted
        $apt = Appointment::find($aptId);
        $this->assertEquals('cancelled', $apt->status);
        $this->assertNotNull($apt->cancelled_at);
        $this->assertEquals($this->patient->id, $apt->cancelled_by);
        $this->assertEquals('Change of plans', $apt->cancellation_reason);

        // Slot released
        $this->assertEquals(0, $this->session->fresh()->booked_appointments);

        // Audit trail recorded
        $this->assertDatabaseHas('audit_logs', [
            'resource_type' => Appointment::class,
            'resource_id' => $aptId,
            'action' => 'appointment.cancelled',
        ]);
    }

    public function test_patient_b_cannot_cancel_patient_a_appointment(): void
    {
        $aptId = $this->book($this->patient, '09:00')->json('data.id');

        Sanctum::actingAs($this->otherPatient);
        $this->deleteJson("/api/appointments/{$aptId}")
            ->assertStatus(403);

        $this->assertEquals('confirmed', Appointment::find($aptId)->status);
    }

    public function test_doctor_cannot_cancel_unrelated_doctor_appointment(): void
    {
        $aptId = $this->book($this->patient, '09:00')->json('data.id');

        $otherDoctorUser = $this->makeUser('doctor');
        \App\Models\Doctor::create([
            'user_id' => $otherDoctorUser->id,
            'display_name' => 'Dr. Other',
            'slug' => 'dr-other',
            'is_verified' => true,
            'is_active' => true,
        ]);

        Sanctum::actingAs($otherDoctorUser);
        $this->postJson("/api/appointments/{$aptId}/doctor-cancel", ['cancellation_reason' => 'x'])
            ->assertStatus(403);

        $this->assertEquals('confirmed', Appointment::find($aptId)->status);
    }

    public function test_doctor_can_cancel_own_appointment_and_patient_is_notified(): void
    {
        $aptId = $this->book($this->patient, '09:00')->json('data.id');

        Sanctum::actingAs($this->doctorUser);
        $this->postJson("/api/appointments/{$aptId}/doctor-cancel", ['cancellation_reason' => 'Emergency'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $apt = Appointment::find($aptId);
        $this->assertEquals($this->doctorUser->id, $apt->cancelled_by);

        // Patient received the cancellation notification (single notification path)
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->patient->id,
        ]);
    }

    public function test_facility_a_staff_cannot_cancel_facility_b_appointment(): void
    {
        $aptId = $this->book($this->patient, '09:00')->json('data.id');

        // Staff member of Facility B only
        $staffB = \App\Models\User::factory()->create();
        $this->actingFacilityStaff($staffB, $this->facilityB);

        Sanctum::actingAs($staffB);
        $this->postJson("/api/appointments/{$aptId}/facility-cancel", ['cancellation_reason' => 'x'])
            ->assertStatus(403);

        $this->assertEquals('confirmed', Appointment::find($aptId)->status);
    }

    public function test_facility_a_staff_can_cancel_facility_a_appointment(): void
    {
        $aptId = $this->book($this->patient, '09:00')->json('data.id');

        $staffA = \App\Models\User::factory()->create();
        $this->actingFacilityStaff($staffA, $this->facilityA);

        Sanctum::actingAs($staffA);
        $this->postJson("/api/appointments/{$aptId}/facility-cancel", ['cancellation_reason' => 'Doctor unavailable'])
            ->assertStatus(200);

        $apt = Appointment::find($aptId);
        $this->assertEquals('cancelled', $apt->status);
        $this->assertEquals($staffA->id, $apt->cancelled_by);
    }
}