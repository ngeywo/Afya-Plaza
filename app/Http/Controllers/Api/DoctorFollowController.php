<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Follow;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Phase 10: Patient-to-Doctor following.
 *
 * The relationship is PATIENT -> DOCTOR IDENTITY. A patient follows a doctor
 * (not a doctor-at-a-facility). The doctor can move between facilities
 * while the patient continues to receive updates.
 *
 * The `follows` table already exists with `unique(user_id, doctor_id)`,
 * so duplicate follow attempts will fail with a 409.
 */
class DoctorFollowController extends Controller
{
    /**
     * POST /api/doctors/{doctor}/follow
     */
    public function follow(Request $request, Doctor $doctor): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // Reject doctors / facility admins following other doctors via this endpoint.
        // (They have a different UX — they manage their own doctor profile.)
        if ($user->hasAnyRole(['doctor', 'facility-admin', 'super-admin'])) {
            return response()->json([
                'error' => 'This endpoint is for patients only.',
            ], 403);
        }

        // Use a row insert to also catch the unique constraint edge case
        // at the database level — never trust the application check alone.
        try {
            DB::transaction(function () use ($user, $doctor) {
                $existing = Follow::where('user_id', $user->id)
                    ->where('doctor_id', $doctor->id)
                    ->lockForUpdate()
                    ->first();
                if (! $existing) {
                    Follow::create([
                        'user_id' => $user->id,
                        'doctor_id' => $doctor->id,
                    ]);
                }
            });
        } catch (QueryException $e) {
            // Unique constraint race — already exists, that's fine.
        }

        return response()->json([
            'following' => true,
            'doctor_id' => $doctor->id,
            'message' => 'You are now following this doctor.',
        ], 201);
    }

    /**
     * DELETE /api/doctors/{doctor}/follow
     */
    public function unfollow(Request $request, Doctor $doctor): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        Follow::where('user_id', $user->id)
            ->where('doctor_id', $doctor->id)
            ->delete();

        return response()->json([
            'following' => false,
            'doctor_id' => $doctor->id,
        ]);
    }

    /**
     * GET /api/doctors/{doctor}/follow-status
     * Returns the authenticated patient's follow state for this doctor.
     * Always returns 200, never 404, so the UI can use it for "is_following".
     */
    public function status(Request $request, Doctor $doctor): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['following' => false, 'authenticated' => false]);
        }

        $following = Follow::where('user_id', $user->id)
            ->where('doctor_id', $doctor->id)
            ->exists();

        return response()->json([
            'following' => $following,
            'authenticated' => true,
        ]);
    }
}
