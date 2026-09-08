<?php

namespace App\Http\Controllers\Api;

use App\Enums\VerificationRequestStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\VerificationRequest;
use App\Services\AccountStateMachine;
use App\Services\ProfileCompletenessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Phase 23: Provider-initiated verification submissions.
 *
 * Doctors and facility admins submit authoritative evidence (registry number,
 * source, reference notes). The request then enters the admin review queue.
 */
class ProviderVerificationController extends Controller
{
    public const TYPE_DOCTOR = 'doctor';

    public const TYPE_FACILITY = 'facility';

    public function __construct(protected AccountStateMachine $stateMachine) {}

    // ─── Doctor ─────────────────────────────────────────────────────────────────

    public function doctorStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('doctor')) {
            return response()->json(['message' => 'Forbidden. Doctor access required.'], 403);
        }
        $doctor = $user->doctor;

        if (! $doctor) {
            return response()->json(['data' => ['has_profile' => false]]);
        }

        $latest = VerificationRequest::query()
            ->where('verifiable_type', Doctor::class)
            ->where('verifiable_id', $doctor->id)
            ->latest()
            ->first();

        return response()->json([
            'data' => [
                'has_profile' => true,
                'verification_status' => $doctor->verification_status?->value,
                'is_verified' => (bool) $doctor->is_verified,
                'rejection_reason' => $doctor->rejection_reason,
                'completeness' => ProfileCompletenessService::doctorCompleteness($doctor),
                'latest_request' => $latest ? $this->serializeRequest($latest) : null,
                'history' => $doctor->verificationRequests()->latest()->limit(10)->get()->map(fn ($v) => $this->serializeRequest($v))->values(),
            ],
        ]);
    }

    public function submitDoctor(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('doctor')) {
            return response()->json(['message' => 'Forbidden. Doctor access required.'], 403);
        }
        $doctor = $user->doctor;

        if (! $doctor) {
            return response()->json(['message' => 'Create your doctor profile first.'], 409);
        }

        return $this->submit($request, $doctor, self::TYPE_DOCTOR);
    }

    // ─── Facility ───────────────────────────────────────────────────────────────

    public function facilityStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasAnyRole(['facility-admin', 'facility-staff'])) {
            return response()->json(['message' => 'Forbidden. Facility access required.'], 403);
        }
        $facility = $user->facilities()->first();

        if (! $facility) {
            return response()->json(['data' => ['has_facility' => false]]);
        }

        $latest = VerificationRequest::query()
            ->where('verifiable_type', Facility::class)
            ->where('verifiable_id', $facility->id)
            ->latest()
            ->first();

        return response()->json([
            'data' => [
                'has_facility' => true,
                'verification_status' => $facility->verification_status?->value,
                'is_verified' => (bool) $facility->is_verified,
                'rejection_reason' => $facility->rejection_reason,
                'completeness' => ProfileCompletenessService::facilityCompleteness($facility),
                'latest_request' => $latest ? $this->serializeRequest($latest) : null,
                'history' => $facility->verificationRequests()->latest()->limit(10)->get()->map(fn ($v) => $this->serializeRequest($v))->values(),
            ],
        ]);
    }

    public function submitFacility(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasAnyRole(['facility-admin', 'facility-staff'])) {
            return response()->json(['message' => 'Forbidden. Facility access required.'], 403);
        }
        $facility = $user->facilities()->first();

        if (! $facility) {
            return response()->json(['message' => 'Create your facility first.'], 409);
        }

        return $this->submit($request, $facility, self::TYPE_FACILITY);
    }

    // ─── Shared ─────────────────────────────────────────────────────────────────

    protected function submit(Request $request, $subject, string $type): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'registry_number' => 'nullable|string|max:191',
            'qualifications' => 'nullable|string|max:1000',
            'evidence' => 'nullable|array|max:10',
            'evidence.*' => 'string|max:500',
            'verification_source' => 'nullable|string|max:191',
            'notes' => 'nullable|string|max:1000',
        ]);

        $registryNumber = array_key_exists('registry_number', $validated)
            ? $validated['registry_number']
            : ($subject->registry_number ?? $subject->license_number ?? null);

        if (blank($registryNumber)) {
            throw ValidationException::withMessages([
                'registry_number' => [strtoupper($type) === 'DOCTOR' ? 'A professional registry number is required for verification.' : 'A facility registration number is required for verification.'],
            ]);
        }

        // Duplicate registry detection — a registry number can only belong to
        // one identity. Privacy-safe.
        if ($type === self::TYPE_DOCTOR) {
            $dupe = Doctor::where('registry_number', $registryNumber)
                ->where('id', '!=', $subject->id)
                ->first();
        } else {
            $dupe = Facility::where('registry_number', $registryNumber)
                ->where('id', '!=', $subject->id)
                ->first();
        }

        if ($dupe) {
            throw ValidationException::withMessages([
                'registry_number' => ['This registration number is already associated with a verified profile.'],
            ]);
        }

        // Persist the registry identifier on the subject.
        $subject->registry_number = $registryNumber;
        if ($type === self::TYPE_DOCTOR && filled($validated['qualifications'] ?? null)) {
            $subject->qualifications = $validated['qualifications'] ?? null;
        }
        $subject->save();

        $requestRecord = VerificationRequest::create([
            'verifiable_type' => $subject::class,
            'verifiable_id' => $subject->id,
            'user_id' => $user->id,
            'type' => $type,
            'status' => VerificationRequestStatus::PENDING,
            'registry_number' => $registryNumber,
            'submitted_data' => [
                'registry_number' => $registryNumber,
                'qualifications' => $subject->qualifications ?? null,
                'display_name' => $subject->display_name ?? $subject->name ?? null,
            ],
            'evidence' => $validated['evidence'] ?? null,
            'verification_source' => $validated['verification_source'] ?? 'self_submitted',
            'reviewer_notes' => $validated['notes'] ?? null,
            'submitted_at' => now(),
        ]);

        // Notify reviewers via admin? For now, the request queue is visible in
        // the admin workspace.

        AuditLog::record(
            $user->id,
            'verification.submitted',
            $subject::class,
            $subject->id,
            $subject->display_name ?? $subject->name,
            ['verification_status' => $subject->verification_status?->value],
            ['verification_status' => VerificationStatus::PENDING->value, 'request_id' => $requestRecord->id],
            'Applicant submitted verification evidence.',
            $request->ip(),
            $request->userAgent(),
        );

        $this->stateMachine->derive($user);

        return response()->json([
            'message' => 'Verification submitted for review.',
            'data' => $this->serializeRequest($requestRecord),
        ], 201);
    }

    private function serializeRequest(VerificationRequest $request): array
    {
        return [
            'id' => $request->id,
            'type' => $request->type,
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'registry_number' => $request->registry_number,
            'submitted_data' => $request->submitted_data,
            'evidence' => $request->evidence,
            'verification_source' => $request->verification_source,
            'reviewer_notes' => $request->reviewer_notes,
            'rejection_reason' => $request->rejection_reason,
            'requested_changes' => $request->requested_changes,
            'reviewer' => $request->reviewer ? ['id' => $request->reviewer->id, 'name' => $request->reviewer->name] : null,
            'submitted_at' => $request->submitted_at?->toIso8601String(),
            'reviewed_at' => $request->reviewed_at?->toIso8601String(),
            'expires_at' => $request->expires_at?->toIso8601String(),
        ];
    }
}
