<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Settlement;
use App\Services\SettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorSettlementController extends Controller
{
    public function __construct(private SettlementService $settlementService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->doctor) {
            return response()->json(['error' => 'Doctor profile not found'], 404);
        }

        $settlements = Settlement::forDoctor($user->doctor->id)
            ->with(['facility:id,name', 'appointment:id,appointment_number'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($settlements);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->doctor) {
            return response()->json(['error' => 'Doctor profile not found'], 404);
        }

        $data = $this->settlementService->getDoctorSummary($user->doctor->id);
        $data['currency'] = 'KES';

        return response()->json($data);
    }
}
