<?php

namespace App\Http\Controllers\Api;

use App\Enums\VerificationRequestStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\ClinicSession;
use App\Models\Doctor;
use App\Models\DoctorFacility;
use App\Models\VerificationRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 5: Doctor Workspace
 * Provides authenticated doctors with their operational dashboard,
 * clinic sessions, appointments, schedule, and profile management.
 */
class DoctorWorkspaceController extends Controller
{
    /**
     * GET /api/doctor/dashboard
     * Section 4: "Where am I today?" — the doctor's primary screen.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('doctor')) {
            return response()->json(['error' => 'Forbidden — doctor access only'], 403);
        }
        $doctor = $user->doctor;
        if (! $doctor) {
            return response()->json(['error' => 'Doctor profile not found'], 404);
        }

        $today = today();
        $todayEnd = $today->copy()->addDays(14);

        $todaySessions = ClinicSession::with(['facility'])
            ->where('doctor_id', $doctor->id)
            ->where('session_date', $today->toDateString())
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('start_time')
            ->get();

        $todayAppointments = Appointment::where('doctor_id', $doctor->id)
            ->where('appointment_date', $today->toDateString())
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();

        $totalUpcoming = ClinicSession::where('doctor_id', $doctor->id)
            ->whereBetween('session_date', [$today->toDateString(), $todayEnd->toDateString()])
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();

        $pendingConfirmation = ClinicSession::where('doctor_id', $doctor->id)
            ->where('session_date', '>=', $today->toDateString())
            ->where('doctor_confirmation', 'pending')
            ->where('status', 'pending')
            ->count();

        $upcomingSessions = ClinicSession::with(['facility'])
            ->where('doctor_id', $doctor->id)
            ->whereBetween('session_date', [$today->copy()->addDay()->toDateString(), $todayEnd->toDateString()])
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->limit(6)
            ->get();

        $todayApptList = [];
        if ($todaySessions->isNotEmpty()) {
            $todayApptList = Appointment::with(['user', 'facility'])
                ->where('doctor_id', $doctor->id)
                ->where('appointment_date', $today->toDateString())
                ->whereIn('status', ['pending', 'confirmed'])
                ->orderBy('start_time')
                ->limit(10)
                ->get()
                ->map(fn ($a) => $this->formatAppointmentForDoctor($a))
                ->values();
        }

        return response()->json([
            'data' => [
                'doctor' => [
                    'id' => $doctor->id,
                    'name' => $doctor->display_name,
                    'avatar' => $doctor->avatar,
                    'is_verified' => $doctor->is_verified,
                    'verified_at' => $doctor->verified_at?->toIso8601String(),
                ],
                'today' => [
                    'date' => $today->format('Y-m-d'),
                    'day' => $today->format('l, F j'),
                    'sessions' => $todaySessions->map(fn ($s) => $this->formatSessionForDoctor($s))->values(),
                    'appointments' => $todayApptList,
                ],
                'metrics' => [
                    'today_clinics' => $todaySessions->count(),
                    'today_appointments' => $todayAppointments,
                    'upcoming_clinics' => $totalUpcoming,
                    'pending_confirmation' => $pendingConfirmation,
                ],
                'upcoming' => $upcomingSessions->map(fn ($s) => $this->formatSessionForDoctor($s))->values(),
            ],
        ]);
    }

    /**
     * GET /api/doctor/clinics
     * Section 14: Clinic session management — doctor's own sessions.
     */
    public function clinics(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('doctor')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $doctor = $user->doctor;

        $from = $request->filled('from') ? Carbon::parse($request->from) : today();
        $to = $request->filled('to') ? Carbon::parse($request->to) : $from->copy()->addDays(30);
        $status = $request->input('status');

        $q = ClinicSession::with(['facility'])
            ->where('doctor_id', $doctor->id)
            ->whereBetween('session_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('session_date')
            ->orderBy('start_time');

        if ($status && in_array($status, ['pending', 'confirmed', 'cancelled', 'completed'])) {
            $q->where('status', $status);
        }

        $sessions = $q->get();

        return response()->json([
            'data' => $sessions->map(fn ($s) => $this->formatSessionForDoctor($s, true))->values(),
            'meta' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'total' => $sessions->count(),
            ],
        ]);
    }

    /**
     * GET /api/doctor/schedule
     * Section 10 & 11: Recurring schedule per facility + exceptions.
     */
    public function schedule(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('doctor')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $doctor = $user->doctor;
        $today = today();
        $twoWeeks = $today->copy()->addDays(14);
        // Sessions are scheduled by facilities and may be set weeks ahead; keep
        // unconfirmed sessions confirmable early by showing a wider window.
        $sessionWindow = $today->copy()->addDays(45);

        $doctorFacilities = DoctorFacility::with(['facility', 'schedules', 'exceptions.targetFacility'])
            ->where('doctor_id', $doctor->id)
            ->where('is_active', true)
            ->get();

        $scheduleData = $doctorFacilities->map(function ($df) use ($today, $twoWeeks) {
            $schedules = $df->schedules()
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $today->toDateString()))
                ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>=', $today->toDateString()))
                ->orderBy('day_of_week')
                ->get();

            $exceptions = $df->exceptions()
                ->whereBetween('date', [$today->toDateString(), $twoWeeks->toDateString()])
                ->get();

            return [
                'facility' => [
                    'id' => $df->facility->id,
                    'name' => $df->facility->name,
                    'city' => $df->facility->city,
                    'address' => $df->facility->address,
                ],
                'consultation_fee' => $df->consultation_fee,
                'accepts_appointments' => $df->accepts_appointments,
                'schedules' => $schedules->map(fn ($s) => [
                    'id' => $s->id,
                    'day_of_week' => $s->day_of_week,
                    'day_name' => $this->dayName($s->day_of_week),
                    'start_time' => substr($s->start_time, 0, 5),
                    'end_time' => substr($s->end_time, 0, 5),
                    'slot_duration_minutes' => $s->slot_duration_minutes,
                    'max_appointments' => $s->max_appointments,
                ])->values(),
                'exceptions' => $exceptions->map(fn ($e) => [
                    'id' => $e->id,
                    'date' => $e->date->format('Y-m-d'),
                    'day' => $e->date->format('l, M j'),
                    'type' => $e->type,
                    'reason' => $e->reason,
                    'target_facility' => $e->targetFacility ? [
                        'id' => $e->targetFacility->id,
                        'name' => $e->targetFacility->name,
                    ] : null,
                    'new_start_time' => $e->new_start_time ? substr($e->new_start_time, 0, 5) : null,
                    'new_end_time' => $e->new_end_time ? substr($e->new_end_time, 0, 5) : null,
                ])->values(),
            ];
        });

        $sessions = ClinicSession::with(['facility'])
            ->where('doctor_id', $doctor->id)
            ->whereBetween('session_date', [$today->toDateString(), $sessionWindow->toDateString()])
            ->orderBy('session_date')
            ->get();

        return response()->json([
            'data' => [
                'recurring' => $scheduleData->values(),
                'upcoming_sessions' => $sessions->map(fn ($s) => [
                    'id' => $s->id,
                    'session_date' => $s->session_date->format('Y-m-d'),
                    'day' => $s->session_date->format('l, M j'),
                    'start_time' => substr($s->start_time, 0, 5),
                    'end_time' => substr($s->end_time, 0, 5),
                    'status' => $s->status,
                    'is_confirmed' => $s->is_confirmed,
                    'facility' => [
                        'id' => $s->facility->id,
                        'name' => $s->facility->name,
                        'city' => $s->facility->city,
                    ],
                ])->values(),
            ],
        ]);
    }

    /**
     * GET /api/doctor/appointments
     * Section 7: Today's appointments scoped to the authenticated doctor.
     */
    public function appointments(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('doctor')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $doctor = $user->doctor;
        $date = $request->filled('date') ? Carbon::parse($request->date) : today();
        $sessionId = $request->input('session_id');

        $q = Appointment::with(['user', 'facility', 'clinicSession.facility'])
            ->where('doctor_id', $doctor->id)
            ->orderBy('appointment_date', 'desc')
            ->orderBy('start_time');

        if ($request->boolean('upcoming')) {
            $q->where('appointment_date', '>=', today()->toDateString());
        } elseif ($request->filled('date')) {
            $q->where('appointment_date', $date->toDateString());
        }
        if ($sessionId) {
            $q->where('clinic_session_id', $sessionId);
        }
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        // Phase 17: grouped view — the doctor's mobile practice grouped by
        // clinic session / facility / date (Section 22). Never flattened.
        if ($request->boolean('grouped')) {
            $appointments = $q->limit(300)->get();
            $data = $appointments
                ->groupBy(fn ($a) => $a->clinic_session_id ?? 'unassigned')
                ->map(function ($group) {
                    $first = $group->first();
                    $session = $first->clinicSession;

                    return [
                        'clinic_session' => $session ? [
                            'id' => $session->id,
                            'session_date' => $session->session_date->format('Y-m-d'),
                            'day' => $session->session_date->format('l, M j'),
                            'start_time' => substr($session->start_time, 0, 5),
                            'end_time' => substr($session->end_time, 0, 5),
                            'status' => $session->status,
                            'is_confirmed' => $session->is_confirmed,
                            'facility' => $session->facility ? [
                                'id' => $session->facility->id,
                                'name' => $session->facility->name,
                                'city' => $session->facility->city,
                            ] : null,
                        ] : null,
                        'appointment_count' => $group->count(),
                        'appointments' => $group->sortBy('start_time')->values()
                            ->map(fn ($a) => $this->formatAppointmentForDoctor($a)),
                    ];
                })->values();

            return response()->json([
                'data' => $data,
                'meta' => [
                    'grouped' => true,
                    'date' => $date->toDateString(),
                    'total' => $appointments->count(),
                ],
            ]);
        }

        $appointments = $q->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $appointments->map(fn ($a) => $this->formatAppointmentForDoctor($a))->values(),
            'meta' => [
                'date' => $date->toDateString(),
                'session_id' => $sessionId,
                'total' => $appointments->total(),
                'per_page' => $appointments->perPage(),
                'current_page' => $appointments->currentPage(),
                'last_page' => $appointments->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/doctor/appointments/{id}
     * Section 8: Single appointment detail. IDOR protected.
     */
    public function showAppointment(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('doctor')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $doctor = $user->doctor;

        $appointment = Appointment::with(['user', 'facility', 'clinicSession.doctor'])
            ->where('doctor_id', $doctor->id)
            ->where('id', $id)
            ->first();

        if (! $appointment) {
            return response()->json(['error' => 'Appointment not found'], 404);
        }

        return response()->json(['data' => $this->formatAppointmentForDoctor($appointment)]);
    }

    /**
     * GET /api/doctor/profile
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('doctor')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $doctor = $user->doctor->load(['specialties', 'facilities.county']);

        return response()->json(['data' => [
            'id' => $doctor->id, 'display_name' => $doctor->display_name, 'slug' => $doctor->slug,
            'biography' => $doctor->biography, 'qualifications' => $doctor->qualifications,
            'license_number' => $doctor->license_number, 'consultation_fee' => $doctor->consultation_fee,
            'avatar' => $doctor->avatar, 'gender' => $doctor->gender,
            'years_of_experience' => $doctor->years_of_experience,
            'is_verified' => $doctor->is_verified, 'verified_at' => $doctor->verified_at?->toIso8601String(),
            'is_active' => $doctor->is_active, 'is_featured' => $doctor->is_featured,
            'specialties' => $doctor->specialties->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name, 'icon' => $s->icon,
                'is_primary' => (bool) $s->pivot->is_primary,
            ])->values(),
            'facilities' => $doctor->facilities->map(fn ($f) => [
                'id' => $f->id, 'name' => $f->name, 'city' => $f->city,
                'county' => $f->county?->name, 'type' => $f->type, 'is_verified' => $f->is_verified,
                'consultation_fee' => $f->pivot->consultation_fee,
                'accepts_appointments' => $f->pivot->accepts_appointments,
                'is_active' => $f->pivot->is_active,
            ])->values(),
        ]]);
    }

    /**
     * PUT /api/doctor/profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('doctor')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $doctor = $user->doctor;
        $validated = $request->validate([
            'display_name' => 'sometimes|string|max:255',
            'biography' => 'sometimes|nullable|string|max:2000',
            'qualifications' => 'sometimes|nullable|string|max:1000',
            'consultation_fee' => 'sometimes|nullable|numeric|min:0',
            'avatar' => 'sometimes|nullable|string|max:500',
            'gender' => 'sometimes|nullable|string|in:male,female,other',
            'years_of_experience' => 'sometimes|nullable|integer|min:0|max:70',
            'registry_number' => 'sometimes|nullable|string|max:191',
            'languages' => 'sometimes|nullable|array',
            'languages.*' => 'string|max:80',
            'areas_of_practice' => 'sometimes|nullable|array',
            'areas_of_practice.*' => 'string|max:120',
        ]);
        $editableFields = ['display_name', 'biography', 'qualifications', 'consultation_fee', 'avatar', 'gender', 'years_of_experience', 'registry_number', 'languages', 'areas_of_practice'];
        $originalProfessional = [
            'display_name' => $doctor->getOriginal('display_name'),
            'qualifications' => $doctor->getOriginal('qualifications'),
            'registry_number' => $doctor->getOriginal('registry_number'),
        ];
        $doctor->update(array_intersect_key($validated, array_flip($editableFields)));

        // Phase 23: professional information changes on a verified doctor
        // trigger re-verification (Section 10).
        $this->triggerReverificationIfProfessionalInfoChanged($user, $doctor, $validated, $originalProfessional, $request);

        return response()->json(['data' => [
            'id' => $doctor->id, 'display_name' => $doctor->display_name,
            'biography' => $doctor->biography, 'qualifications' => $doctor->qualifications,
            'consultation_fee' => $doctor->consultation_fee, 'avatar' => $doctor->avatar,
            'gender' => $doctor->gender, 'years_of_experience' => $doctor->years_of_experience,
            'registry_number' => $doctor->registry_number,
            'verification_status' => $doctor->verification_status?->value,
            'message' => 'Profile updated successfully.',
        ]]);
    }

    /**
     * Phase 23: If a verified doctor changes professional registration/credential
     * information, drop back to pending and open a re-review request.
     */
    private function triggerReverificationIfProfessionalInfoChanged($user, $doctor, array $validated, array $original, Request $request): void
    {
        $changed = collect(['registry_number', 'qualifications', 'display_name'])
            ->filter(fn ($f) => array_key_exists($f, $validated) && (string) ($validated[$f] ?? '') !== (string) ($original[$f] ?? ''));

        if ($doctor->is_verified && $changed->isNotEmpty()) {
            $doctor->update([
                'verification_status' => VerificationStatus::PENDING->value,
                'is_verified' => false,
            ]);

            VerificationRequest::create([
                'verifiable_type' => Doctor::class,
                'verifiable_id' => $doctor->id,
                'user_id' => $user->id,
                'type' => 'profile_change',
                'status' => VerificationRequestStatus::PENDING,
                'registry_number' => $doctor->registry_number,
                'submitted_data' => ['changed_fields' => $changed->values()],
                'verification_source' => 'self_change',
                'submitted_at' => now(),
            ]);

            AuditLog::record(
                $user->id,
                'verification.reverification_triggered',
                Doctor::class,
                $doctor->id,
                $doctor->display_name,
                ['verification_status' => 'verified'],
                ['verification_status' => 'pending', 'changed_fields' => $changed->values()],
                'Professional information changed on a verified profile.',
                $request->ip(),
                $request->userAgent(),
            );
        }
    }

    /**
     * GET /api/doctor/facilities
     */
    public function facilities(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('doctor')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $doctor = $user->doctor;
        $facilities = DoctorFacility::with(['facility.county', 'schedules'])
            ->where('doctor_id', $doctor->id)->get()
            ->map(fn ($df) => [
                'id' => $df->facility->id, 'name' => $df->facility->name,
                'city' => $df->facility->city, 'county' => $df->facility->county?->name,
                'address' => $df->facility->address, 'type' => $df->facility->type,
                'logo' => $df->facility->logo, 'is_verified' => $df->facility->is_verified,
                'consultation_fee' => $df->consultation_fee,
                'accepts_appointments' => $df->accepts_appointments,
                'is_active' => $df->is_active,
                'started_at' => $df->started_at?->toDateString(),
                'ended_at' => $df->ended_at?->toDateString(),
                'schedule_count' => $df->schedules->count(),
            ])->values();

        return response()->json(['data' => $facilities]);
    }

    // ─── Private helpers ────────────────────────────────────────────────────

    private function formatSessionForDoctor($s, bool $verbose = false): array
    {
        $base = [
            'id' => $s->id,
            'session_date' => $s->session_date instanceof Carbon ? $s->session_date->format('Y-m-d') : $s->session_date,
            'day' => $s->session_date instanceof Carbon ? $s->session_date->format('l, M j') : '',
            'start_time' => substr($s->start_time, 0, 5),
            'end_time' => substr($s->end_time, 0, 5),
            'status' => $s->status,
            'doctor_confirmation' => $s->doctor_confirmation,
            'facility_confirmation' => $s->facility_confirmation,
            'is_confirmed' => $s->is_confirmed,
            'facility' => $s->facility ? [
                'id' => $s->facility->id, 'name' => $s->facility->name,
                'city' => $s->facility->city, 'address' => $s->facility->address, 'type' => $s->facility->type,
            ] : null,
            'max_appointments' => $s->max_appointments,
            'booked_appointments' => $s->booked_appointments,
            'available_slots' => $s->available_slots,
            'consultation_fee' => $s->consultation_fee,
        ];
        if ($verbose) {
            $base['slot_duration_minutes'] = $s->slot_duration_minutes;
            $base['cancellation_reason'] = $s->cancellation_reason;
            $base['notes'] = $s->notes;
        }

        return $base;
    }

    private function formatAppointmentForDoctor($a): array
    {
        return [
            'id' => $a->id, 'appointment_number' => $a->appointment_number, 'status' => $a->status,
            // Phase 17: actions the doctor may perform now (backend authoritative)
            'allowed_actions' => $a->allowedDoctorActions(),
            'appointment_date' => $a->appointment_date instanceof Carbon ? $a->appointment_date->format('Y-m-d') : $a->appointment_date,
            'day' => $a->appointment_date instanceof Carbon ? $a->appointment_date->format('l, M j') : '',
            'start_time' => substr($a->start_time, 0, 5), 'end_time' => substr($a->end_time, 0, 5),
            'reason' => $a->reason, 'notes' => $a->notes,
            'amount_paid' => $a->amount_paid, 'payment_status' => $a->payment_status,
            'confirmed_at' => $a->confirmed_at?->toIso8601String(),
            'cancelled_at' => $a->cancelled_at?->toIso8601String(),
            'checked_in_at' => $a->checked_in_at?->toIso8601String(),
            'consultation_started_at' => $a->consultation_started_at?->toIso8601String(),
            'no_show_at' => $a->no_show_at?->toIso8601String(),
            'created_at' => $a->created_at?->toIso8601String(),
            'patient' => $a->user ? [
                'id' => $a->user->id, 'name' => $a->user->name,
                'email' => $a->user->email, 'phone' => $a->user->phone, 'avatar' => $a->user->avatar,
            ] : null,
            'facility' => $a->facility ? [
                'id' => $a->facility->id, 'name' => $a->facility->name,
                'city' => $a->facility->city, 'type' => $a->facility->type, 'address' => $a->facility->address,
            ] : null,
            'clinic_session' => $a->clinicSession ? [
                'id' => $a->clinicSession->id,
                'session_date' => $a->clinicSession->session_date instanceof Carbon ? $a->clinicSession->session_date->format('Y-m-d') : $a->clinicSession->session_date,
                'consultation_fee' => $a->clinicSession->consultation_fee,
            ] : null,
        ];
    }

    private function dayName(int $dayOfWeek): string
    {
        return match ($dayOfWeek) {
            0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
            4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', default => 'Unknown',
        };
    }
}

// ─── Private helpers ────────────────────────────────────────────────────
