<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Enums\VerificationRequestStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\VerificationRequest;
use App\Services\AccountStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 23: Administrative verification review.
 *
 * The admin queue is evidence/request-driven (not just a status boolean):
 * every decision records the reviewer, timestamp, reason and notes against the
 * verification request, and the subject's verification_status is updated to
 * match the approved/rejected/suspended outcome.
 */
class AdminVerificationController extends Controller
{
    public function __construct(protected AccountStateMachine $stateMachine) {}

    /**
     * GET /api/admin/verification-requests
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|in:doctor,facility,profile_change',
            'status' => 'nullable|in:'.implode(',', array_map(fn ($s) => $s->value, VerificationRequestStatus::cases())),
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 25);

        $query = VerificationRequest::query()
            ->with(['user:id,name,email', 'reviewer:id,name'])
            ->when($validated['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status));

        $paginated = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => collect($paginated->items())->map(fn ($v) => $this->serialize($v))->values(),
            'meta' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/admin/verification-requests/{request}
     */
    public function show(Request $request, VerificationRequest $verificationRequest): JsonResponse
    {
        $verificationRequest->load(['user:id,name,email,phone', 'reviewer:id,name', 'verifiable']);

        return response()->json(['data' => $this->serialize($verificationRequest, true)]);
    }

    /**
     * POST /api/admin/verification-requests/{request}/review
     *
     * Concurrency-safe: the request must still be open (pending | under_review |
     * more_info), and the row is locked before updating, so two reviewers cannot
     * both approve the same application.
     */
    public function review(Request $request, VerificationRequest $verificationRequest): JsonResponse
    {
        $validated = $request->validate([
            'decision' => 'required|in:approve,reject,request_more_info,suspend',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if (in_array($validated['decision'], ['reject', 'suspend'], true) && blank($validated['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => ['A reason is required for '.$validated['decision'].'.']]);
        }

        $reviewer = $request->user();

        $outcome = DB::transaction(function () use ($verificationRequest, $reviewer, $validated, $request) {
            // Lock the row so two reviewers can't both act on it.
            $record = VerificationRequest::whereKey($verificationRequest->id)
                ->lockForUpdate()
                ->first();

            if (! $record->isOpen()) {
                return ['conflict' => true];
            }

            $status = match ($validated['decision']) {
                'approve' => VerificationRequestStatus::APPROVED,
                'reject' => VerificationRequestStatus::REJECTED,
                'request_more_info' => VerificationRequestStatus::MORE_INFO,
                'suspend' => VerificationRequestStatus::SUSPENDED,
                default => VerificationRequestStatus::PENDING,
            };

            $record->update([
                'status' => $status,
                'reviewer_id' => $reviewer->id,
                'reviewed_at' => now(),
                'reviewer_notes' => $validated['notes'] ?? $record->reviewer_notes,
                'rejection_reason' => in_array($validated['decision'], ['reject', 'suspend'], true) ? ($validated['reason'] ?? null) : $record->rejection_reason,
                'requested_changes' => $validated['decision'] === 'request_more_info' ? ($validated['reason'] ?? null) : $record->requested_changes,
                'expires_at' => null,
            ]);

            $subject = $record->verifiable;
            $beforeStatus = $subject->verification_status?->value;

            match ($validated['decision']) {
                'approve' => $subject->update([
                    'verification_status' => VerificationStatus::VERIFIED,
                    'is_verified' => true,
                    'verified_at' => now(),
                    'verified_by' => $reviewer->id,
                    'rejection_reason' => null,
                    'rejection_notes' => null,
                    'suspended_at' => null,
                    'suspended_by' => null,
                    'suspension_reason' => null,
                ]),
                'reject' => $subject->update([
                    'verification_status' => VerificationStatus::REJECTED,
                    'is_verified' => false,
                    'rejection_reason' => $validated['reason'] ?? null,
                    'rejection_notes' => $validated['notes'] ?? null,
                ]),
                'suspend' => $subject->update([
                    'verification_status' => VerificationStatus::SUSPENDED,
                    'suspended_at' => now(),
                    'suspended_by' => $reviewer->id,
                    'suspension_reason' => $validated['reason'] ?? null,
                    'is_verified' => false,
                ]),
                'request_more_info' => $subject->update([
                    // Subject stays pending; the review round continues.
                    'verification_status' => $subject->verification_status === VerificationStatus::REJECTED
                        ? VerificationStatus::REJECTED
                        : VerificationStatus::PENDING,
                ]),
                default => null,
            };

            $action = 'verification.'.match ($validated['decision']) {
                'approve' => 'approved',
                'reject' => 'rejected',
                'request_more_info' => 'more_info_requested',
                'suspend' => 'suspended',
                default => 'reviewed',
            };

            if ($subject->user ?? ($record->user ?? null)) {
                $this->stateMachine->derive($subject->user ?? $record->user);
            }

            AuditLog::record(
                $reviewer->id,
                $action,
                $subject::class,
                $subject->id,
                $subject->display_name ?? $subject->name,
                ['verification_status' => $beforeStatus],
                ['request' => $record->id, 'verification_status' => $subject->verification_status?->value],
                $validated['reason'] ?? null,
                $request->ip(),
                $request->userAgent(),
            );

            return ['record' => $record->fresh()->load('reviewer', 'user')];
        });

        if (($outcome['conflict'] ?? false) === true) {
            return response()->json([
                'message' => 'This verification request has already been reviewed by someone else.',
            ], 409);
        }

        return response()->json([
            'message' => 'Verification decision recorded.',
            'data' => $this->serialize($outcome['record'], true),
        ]);
    }

    private function serialize(VerificationRequest $v, bool $detailed = false): array
    {
        $base = [
            'id' => $v->id,
            'type' => $v->type,
            'status' => $v->status->value,
            'status_label' => $v->status->label(),
            'registry_number' => $v->registry_number,
            'verification_source' => $v->verification_source,
            'reviewer_notes' => $v->reviewer_notes,
            'rejection_reason' => $v->rejection_reason,
            'requested_changes' => $v->requested_changes,
            'submitted_at' => $v->submitted_at?->toIso8601String(),
            'reviewed_at' => $v->reviewed_at?->toIso8601String(),
            'expires_at' => $v->expires_at?->toIso8601String(),
            'applicant' => $v->user ? ['id' => $v->user->id, 'name' => $v->user->name, 'email' => $v->user->email] : null,
            'reviewer' => $v->reviewer ? ['id' => $v->reviewer->id, 'name' => $v->reviewer->name] : null,
        ];

        if ($detailed) {
            $subject = $v->verifiable;
            $base['subject'] = $subject ? [
                'id' => $subject->id,
                'display_name' => $subject->display_name ?? $subject->name,
                'verification_status' => $subject->verification_status?->value,
                'is_verified' => (bool) $subject->is_verified,
                'rejection_reason' => $subject->rejection_reason ?? null,
                'created_at' => $subject->created_at?->toIso8601String(),
            ] : null;
            $base['submitted_data'] = $v->submitted_data;
            $base['evidence'] = $v->evidence;
        }

        return $base;
    }
}
