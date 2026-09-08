<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Settlement;
use App\Services\SettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettlementController extends Controller
{
    public function __construct(private SettlementService $settlementService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Settlement::with(['facility:id,name,slug', 'doctor:id,name', 'appointment:id,appointment_number']);

        if ($request->filled('facility_id')) {
            $query->forFacility($request->facility_id);
        }
        if ($request->filled('doctor_id')) {
            $query->forDoctor($request->doctor_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $settlements = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($settlements);
    }

    public function show(int $id): JsonResponse
    {
        $settlement = Settlement::with(['facility', 'doctor', 'appointment', 'payment', 'approvedByUser'])
            ->findOrFail($id);

        return response()->json($settlement);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $settlement = Settlement::findOrFail($id);
        $this->settlementService->approve($settlement, $request->user()->id);

        return response()->json(['message' => 'Settlement approved', 'data' => $settlement->fresh()]);
    }

    public function markPaid(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'payment_method' => 'required|string',
            'payment_reference' => 'nullable|string',
        ]);

        $settlement = Settlement::findOrFail($id);
        $this->settlementService->markPaid($settlement, $request->payment_method, $request->payment_reference);

        return response()->json(['message' => 'Settlement marked as paid', 'data' => $settlement->fresh()]);
    }

    public function summary(Request $request): JsonResponse
    {
        $facilityId = $request->filled('facility_id') ? $request->facility_id : null;

        $data = [
            'today' => $facilityId ? $this->settlementService->getFacilitySummary($facilityId, 'today') : null,
            'month' => $facilityId ? $this->settlementService->getFacilitySummary($facilityId, 'month') : null,
        ];

        return response()->json($data);
    }

    public function doctorBreakdown(Request $request): JsonResponse
    {
        $request->validate(['facility_id' => 'required|exists:facilities,id']);

        $breakdown = $this->settlementService->getDoctorBreakdown(
            $request->facility_id,
            $request->period
        );

        return response()->json($breakdown);
    }
}
