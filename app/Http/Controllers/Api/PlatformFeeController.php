<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformFee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 23 (Facility Payments): super-admin configuration of the platform fee
 * applied to patient->facility payments. The persisted active fee is
 * authoritative at payment time (config is only a fallback). The fee is never
 * hard-coded.
 */
class PlatformFeeController extends Controller
{
    /** GET /api/admin/platform-fees */
    public function index(): JsonResponse
    {
        $fees = PlatformFee::with('creator:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($fee) => $this->format($fee));

        return response()->json(['data' => $fees]);
    }

    /** POST /api/admin/platform-fees */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'fee_type' => 'required|integer|in:1,2',
            'rate' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'effective_from' => 'nullable|date',
            'effective_until' => 'nullable|date|after_or_equal:effective_from',
        ]);

        $fee = PlatformFee::create([
            ...$validated,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $user->id,
        ]);

        AuditLog::record($user->id, 'platform_fee.created', PlatformFee::class, $fee->id, $fee->name);

        return response()->json(['data' => $this->format($fee), 'message' => 'Platform fee saved.'], 201);
    }

    /** PUT /api/admin/platform-fees/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $fee = PlatformFee::findOrFail($id);
        $user = $request->user();
        $validated = $request->validate([
            'name' => 'sometimes|string|max:120',
            'fee_type' => 'sometimes|integer|in:1,2',
            'rate' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'effective_from' => 'sometimes|nullable|date',
            'effective_until' => 'sometimes|nullable|date|after_or_equal:effective_from',
        ]);

        $fee->update($validated);

        AuditLog::record($user->id, 'platform_fee.updated', PlatformFee::class, $fee->id, $fee->name);

        return response()->json(['data' => $this->format($fee->fresh()), 'message' => 'Platform fee updated.']);
    }

    /** GET /api/admin/platform-fees/active */
    public function active(): JsonResponse
    {
        $fee = PlatformFee::query()->active()->orderByDesc('id')->first();

        return response()->json(['data' => $fee ? $this->format($fee) : null]);
    }

    private function format(PlatformFee $fee): array
    {
        return [
            'id' => $fee->id,
            'name' => $fee->name,
            'fee_type' => (int) $fee->fee_type,
            'rate' => $fee->rate,
            'is_active' => $fee->is_active,
            'effective_from' => $fee->effective_from?->toDateString(),
            'effective_until' => $fee->effective_until?->toDateString(),
            'created_by' => $fee->creator?->name,
        ];
    }
}
