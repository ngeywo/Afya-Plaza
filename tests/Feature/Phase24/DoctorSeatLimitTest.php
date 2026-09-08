<?php

namespace Tests\Feature\Phase24;

use App\Models\Doctor;
use App\Models\DoctorFacility;
use App\Models\Facility;
use App\Models\FacilitySubscription;
use App\Models\User;
use App\Services\EntitlementService;

class DoctorSeatLimitTest extends Phase24TestCase
{
    private function inviteViaApi(User $admin, Facility $facility, Doctor $doctor, int $expected = 201, ?string $code = null)
    {
        $this->actingAsUser($admin);
        $response = $this->postJson("/api/facility/doctors/{$doctor->id}/invite", ['facility_id' => $facility->id]);

        if ($expected === 201) {
            $response->assertStatus(201);
        } else {
            $response->assertStatus($expected);
            if ($code) {
                $response->assertJsonPath('code', $code);
            }
        }

        return $response;
    }

    private function acceptViaApi(User $doctorUser, int $relationshipId)
    {
        $this->actingAsUser($doctorUser);

        return $this->postJson("/api/doctor/relationships/{$relationshipId}/accept");
    }

    public function test_inviting_within_limit_is_allowed(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->subscribeFacility($facility, $this->facilityPlan('facility-starter'));

        [$doctor] = $this->makeDoctors(1);
        $this->inviteViaApi($admin, $facility, $doctor)->assertJsonPath('data.status', 'invited');
        $this->assertSame(1, $this->app->make(EntitlementService::class)->usage()->doctorSeatsUsed($facility));
    }

    public function test_inviting_at_limit_is_blocked_with_context(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->subscribeFacility($facility, $this->facilityPlan('facility-starter'));

        [$a, $b, $c] = $this->makeDoctors(3);
        $this->inviteViaApi($admin, $facility, $a);
        $this->inviteViaApi($admin, $facility, $b);

        $response = $this->inviteViaApi($admin, $facility, $c, 422, 'DOCTOR_LIMIT_REACHED');
        $this->assertSame(2, $response->json('context.used'));
        $this->assertSame(2, $response->json('context.max'));
        $this->assertSame(3, $response->json('context.required'));
        $this->assertSame(2, DoctorFacility::count());
    }

    public function test_pending_invites_reserve_slots_until_resolved(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->subscribeFacility($facility, $this->facilityPlan('facility-starter'));

        [$a, $b, $c] = $this->makeDoctors(3);
        $respA = $this->inviteViaApi($admin, $facility, $a);
        $respB = $this->inviteViaApi($admin, $facility, $b);
        $this->inviteViaApi($admin, $facility, $c, 422, 'DOCTOR_LIMIT_REACHED');

        // Both invited doctors accepting stays at 2/2 (their seats were reserved).
        $this->acceptViaApi($a->user, $respA->json('data.id'))->assertStatus(200);
        $this->assertSame('active', DoctorFacility::find($respA->json('data.id'))->status->value);
        $this->acceptViaApi($b->user, $respB->json('data.id'))->assertStatus(200);
        $this->assertSame('active', DoctorFacility::find($respB->json('data.id'))->status->value);
        $this->assertSame(2, $this->app->make(EntitlementService::class)->usage()->doctorSeatsUsed($facility));
    }

    public function test_declining_frees_the_reserved_slot(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->subscribeFacility($facility, $this->facilityPlan('facility-starter'));

        [$a, $b, $c] = $this->makeDoctors(3);
        $respA = $this->inviteViaApi($admin, $facility, $a);
        $respB = $this->inviteViaApi($admin, $facility, $b);
        $this->inviteViaApi($admin, $facility, $c, 422, 'DOCTOR_LIMIT_REACHED');

        // Doctor A declines via API → seat freed.
        $this->actingAsUser($a->user);
        $this->postJson("/api/doctor/relationships/{$respA->json('data.id')}/decline", ['reason' => 'Not interested'])->assertStatus(200);

        $this->inviteViaApi($admin, $facility, $c);
        $this->assertSame(2, $this->app->make(EntitlementService::class)->usage()->doctorSeatsUsed($facility));
    }

    public function test_ending_relationship_frees_the_seat(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->subscribeFacility($facility, $this->facilityPlan('facility-starter'));

        [$a, $b, $c] = $this->makeDoctors(3);
        $respA = $this->inviteViaApi($admin, $facility, $a);
        $this->acceptViaApi($a->user, $respA->json('data.id'))->assertStatus(200); // end requires ACTIVE
        $respB = $this->inviteViaApi($admin, $facility, $b);

        $this->actingAsUser($admin);
        $this->postJson("/api/facility/relationships/{$respA->json('data.id')}/end", ['reason' => 'Left the practice'])->assertStatus(200);

        $this->inviteViaApi($admin, $facility, $c);
        $this->assertSame('inactive', DoctorFacility::find($respA->json('data.id'))->status->value);
        $this->assertSame(2, $this->app->make(EntitlementService::class)->usage()->doctorSeatsUsed($facility));
    }

    public function test_suspended_relationship_consumes_a_seat_until_ended(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->subscribeFacility($facility, $this->facilityPlan('facility-starter'));

        [$a, $b] = $this->makeDoctors(2);
        $respA = $this->inviteViaApi($admin, $facility, $a);
        $this->acceptViaApi($a->user, $respA->json('data.id'))->assertStatus(200); // suspend requires ACTIVE
        $this->inviteViaApi($admin, $facility, $b);

        // Suspend A — still counts (blocks suspend→add→reactivate bypass).
        $this->actingAsUser($admin);
        $this->postJson("/api/facility/relationships/{$respA->json('data.id')}/suspend", ['reason' => 'Conduct review'])->assertStatus(200);
        $this->assertSame(2, $this->app->make(EntitlementService::class)->usage()->doctorSeatsUsed($facility));

        [$c] = $this->makeDoctors(1);
        $this->inviteViaApi($admin, $facility, $c, 422, 'DOCTOR_LIMIT_REACHED');

        // Ending the suspended relationship finally frees its slot.
        $this->actingAsUser($admin);
        $this->postJson("/api/facility/relationships/{$respA->json('data.id')}/end", ['reason' => 'Resolved'])->assertStatus(200);
        $this->inviteViaApi($admin, $facility, $c);
    }

    public function test_reactivating_ended_relationship_rechecks_capacity(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->subscribeFacility($facility, $this->facilityPlan('facility-starter'));

        [$a, $b, $c] = $this->makeDoctors(3);
        $respA = $this->inviteViaApi($admin, $facility, $a);
        $this->acceptViaApi($a->user, $respA->json('data.id'))->assertStatus(200); // end requires ACTIVE

        $this->actingAsUser($admin);
        $this->postJson("/api/facility/relationships/{$respA->json('data.id')}/end", ['reason' => 'Paused'])->assertStatus(200);

        $respB = $this->inviteViaApi($admin, $facility, $b);
        $respC = $this->inviteViaApi($admin, $facility, $c);
        $this->acceptViaApi($c->user, $respC->json('data.id'))->assertStatus(200); // end requires ACTIVE

        // Facility now at 2/2 with B+C. Reactivating A would make it 3/2 → blocked.
        $this->actingAsUser($admin);
        $this->postJson("/api/facility/relationships/{$respA->json('data.id')}/reactivate", ['reason' => 'Come back'])->assertStatus(422)
            ->assertJsonPath('code', 'DOCTOR_LIMIT_REACHED');

        // Ending C frees a slot → A can reactivate.
        $this->postJson("/api/facility/relationships/{$respC->json('data.id')}/end", ['reason' => 'Exit'])->assertStatus(200);
        $this->postJson("/api/facility/relationships/{$respA->json('data.id')}/reactivate", ['reason' => 'Come back'])->assertStatus(200)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_reactivating_suspended_relationship_needs_no_new_seat(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->subscribeFacility($facility, $this->facilityPlan('facility-starter'));

        [$a, $b] = $this->makeDoctors(2);
        $respA = $this->inviteViaApi($admin, $facility, $a);
        $this->acceptViaApi($a->user, $respA->json('data.id'))->assertStatus(200); // suspend requires ACTIVE
        $respB = $this->inviteViaApi($admin, $facility, $b);

        $this->actingAsUser($admin);
        $this->postJson("/api/facility/relationships/{$respA->json('data.id')}/suspend", ['reason' => 'Review'])->assertStatus(200);
        $this->postJson("/api/facility/relationships/{$respA->json('data.id')}/reactivate", ['reason' => 'Cleared'])->assertStatus(200)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_doctor_seats_are_counted_per_facility(): void
    {
        $adminA = $this->makeVerifiedUser('facility-admin');
        $adminB = $this->makeVerifiedUser('facility-admin');
        $facilityA = $this->makeCompleteFacility($adminA);
        $facilityB = $this->makeCompleteFacility($adminB);
        $this->subscribeFacility($facilityA, $this->facilityPlan('facility-starter')); // 2 seats
        $this->subscribeFacility($facilityB, $this->facilityPlan('facility-growth'));  // 5 seats

        [$shared, $extraA, $extraB] = $this->makeDoctors(3);

        // One doctor identity works at both facilities — counted once per facility.
        $respA = $this->inviteViaApi($adminA, $facilityA, $shared);
        $this->acceptViaApi($shared->user, $respA->json('data.id'))->assertStatus(200);
        $respB = $this->inviteViaApi($adminB, $facilityB, $shared);
        $this->acceptViaApi($shared->user, $respB->json('data.id'))->assertStatus(200);

        $usage = $this->app->make(EntitlementService::class)->usage();
        $this->assertSame(1, $usage->doctorSeatsUsed($facilityA));
        $this->assertSame(1, $usage->doctorSeatsUsed($facilityB));

        // Fill A to its ceiling; B stays independent and can still grow.
        $this->inviteViaApi($adminA, $facilityA, $extraA);
        $this->inviteViaApi($adminA, $facilityA, $extraB, 422);
        $this->inviteViaApi($adminB, $facilityB, $extraA)->assertStatus(201);
        $this->assertSame(2, $usage->doctorSeatsUsed($facilityA));
        $this->assertSame(2, $usage->doctorSeatsUsed($facilityB));
    }

    public function test_facility_without_subscription_uses_default_plan_capacity(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->assertNull($this->app->make(EntitlementService::class)->subscriptionFor($facility));

        // Default facility plan (starter) ceiling = 2 doctors.
        [$a, $b] = $this->makeDoctors(2);
        $this->inviteViaApi($admin, $facility, $a);
        $this->inviteViaApi($admin, $facility, $b);
        [$c] = $this->makeDoctors(1);
        $this->inviteViaApi($admin, $facility, $c, 422, 'DOCTOR_LIMIT_REACHED');
    }

    public function test_request_to_join_at_limit_is_blocked_for_doctor(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->subscribeFacility($facility, $this->facilityPlan('facility-starter'));

        [$a, $b] = $this->makeDoctors(2);
        $respA = $this->inviteViaApi($admin, $facility, $a);
        $this->acceptViaApi($a->user, $respA->json('data.id'))->assertStatus(200); // end requires ACTIVE
        $this->inviteViaApi($admin, $facility, $b);

        [$c] = $this->makeDoctors(1);
        $this->actingAsUser($c->user);
        $response = $this->postJson('/api/doctor/relationships/join', ['facility_id' => $facility->id]);
        $response->assertStatus(422)->assertJsonPath('code', 'DOCTOR_LIMIT_REACHED');

        // Free a seat → the request now succeeds and the facility can approve it.
        $this->actingAsUser($admin);
        $aRel = DoctorFacility::where('facility_id', $facility->id)->where('doctor_id', $a->id)->first();
        $this->postJson("/api/facility/relationships/{$aRel->id}/end", ['reason' => 'Exit'])->assertStatus(200);

        $this->actingAsUser($c->user);
        $join = $this->postJson('/api/doctor/relationships/join', ['facility_id' => $facility->id])->assertStatus(201);
        $rel = DoctorFacility::find($join->json('data.id'));

        $this->actingAsUser($admin);
        $this->postJson("/api/facility/relationships/{$rel->id}/approve")->assertStatus(200);
        $this->assertSame('active', $rel->fresh()->status->value);
    }

    public function test_upgrade_relieves_the_ceiling_without_losing_history(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->subscribeFacility($facility, $this->facilityPlan('facility-starter'));

        [$a, $b, $c, $d] = $this->makeDoctors(4);
        $this->inviteViaApi($admin, $facility, $a);
        $this->inviteViaApi($admin, $facility, $b);

        $this->actingAsUser($admin);
        $this->postJson('/api/facility/subscription/upgrade', ['plan_slug' => 'facility-professional'])->assertStatus(200);

        $previousSnapshot = FacilitySubscription::first()->snapshot();
        $this->inviteViaApi($admin, $facility, $c);
        $this->inviteViaApi($admin, $facility, $d);
        $usage = $this->app->make(EntitlementService::class)->usage()->doctorSeatsUsed($facility);
        $this->assertSame(4, $usage);

        // The prepared snapshot is preserved against a later plan edit.
        $plan = $this->facilityPlan('facility-professional');
        $this->actingAsUser($this->makeVerifiedUser('super-admin'));
        $this->putJson("/api/admin/plans/{$plan->id}", ['max_doctors' => 3])->assertStatus(200);
        $this->assertSame(15, $previousSnapshot['max_doctors']);
        $this->assertSame(3, $this->facilityPlan('facility-professional')->fresh()->max_doctors);
        $this->assertSame(15, FacilitySubscription::first()->snapshot()['max_doctors']);
    }

    public function test_service_level_usage_is_authoritative_not_client_supplied(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->subscribeFacility($facility, $this->facilityPlan('facility-starter'));

        // A malicious payload cannot reset usage counts — server recomputes.
        $this->actingAsUser($admin);
        $this->postJson('/api/facility/subscription/checkout', ['plan_slug' => 'facility-starter'])->assertStatus(409);
        $this->getJson('/api/facility/subscription')->assertJsonPath('data.usage.doctors.used', 0);
    }
}
