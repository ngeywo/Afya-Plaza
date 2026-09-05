<?php

namespace Tests\Feature;

use App\Models\Appointment;
use Laravel\Sanctum\Sanctum;

/**
 * Phase 17: Booking safety (Sections 9, 10, 45).
 */
class AppointmentBookingSafetyTest extends AppointmentTestCase
{
    public function test_second_patient_on_same_slot_receives_conflict(): void
    {
        $this->book($this->patient, '09:00')->assertStatus(201);

        // Second patient for the SAME slot must be rejected with 409 SLOT_TAKEN
        $response = $this->book($this->otherPatient, '09:00');
        $response->assertStatus(409);
        $response->assertJsonPath('code', 'SLOT_TAKEN');

        // A different slot still works
        $this->book($this->otherPatient, '09:30')->assertStatus(201);

        $this->assertEquals(2, $this->session->fresh()->booked_appointments);
    }

    public function test_capacity_is_respected(): void
    {
        $session = $this->makeSession($this->doctor, $this->facilityA, [
            'session_date' => now()->addDays(4)->toDateString(),
            'start_time' => '14:00:00',
            'end_time' => '14:30:00',
            'max_appointments' => 1,
            'consultation_fee' => 500,
        ]);

        Sanctum::actingAs($this->patient);
        $this->postJson('/api/appointments', ['clinic_session_id' => $session->id, 'start_time' => '14:00'])
            ->assertStatus(201);

        // Capacity 1: second booking (different slot) must fail with NO_CAPACITY
        $this->postJson('/api/appointments', ['clinic_session_id' => $session->id, 'start_time' => '14:30'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'NO_CAPACITY');
    }

    public function test_idempotency_key_returns_existing_booking(): void
    {
        $key = 'retry-key-123';
        $first = $this->book($this->patient, '09:00', ['idempotency_key' => $key])->assertStatus(201);

        // Same request replayed (double-click / retry) returns the SAME appointment
        $replay = $this->book($this->patient, '09:00', ['idempotency_key' => $key]);
        $replay->assertStatus(200);
        $replay->assertJsonPath('data.id', $first->json('data.id'));
        $replay->assertJsonPath('existing', true);

        // Only ONE appointment was created
        $this->assertEquals(1, Appointment::where('user_id', $this->patient->id)->count());
        $this->assertEquals(1, $this->session->fresh()->booked_appointments);
    }
}