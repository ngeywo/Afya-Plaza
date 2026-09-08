<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\Settlement;
use App\Services\FacilityAccessService;
use App\Services\SettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FacilitySettlementController extends Controller
{
    public function __construct(
        private SettlementService $settlementService,
        private FacilityAccessService $facilityAccess,
    ) {}

    private function managedFacility(Request $request): Facility
    {
        $facility = $this->facilityAccess->authorizedFacility($request);
        if (! $facility) {
            abort(403, 'You are not authorized to manage this facility.');
        }

        return $facility;
    }

    public function index(Request $request): JsonResponse
    {
        $facility = $this->managedFacility($request);

        $query = Settlement::forFacility($facility->id)
            ->with(['doctor:id,name', 'appointment:id,appointment_number']);

        if ($request->filled('doctor_id')) {
            $query->forDoctor($request->doctor_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $settlements = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($settlements);
    }

    public function summary(Request $request): JsonResponse
    {
        $facility = $this->managedFacility($request);

        $data = [
            'today' => $this->settlementService->getFacilitySummary($facility->id, 'today'),
            'month' => $this->settlementService->getFacilitySummary($facility->id, 'month'),
        ];

        return response()->json($data);
    }

    public function doctorBreakdown(Request $request): JsonResponse
    {
        $facility = $this->managedFacility($request);

        $breakdown = $this->settlementService->getDoctorBreakdown($facility->id, $request->period);

        return response()->json($breakdown);
    }
}
