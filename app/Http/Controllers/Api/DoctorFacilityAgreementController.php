<?php

namespace App\Http\Controllers\Api;

use App\Enums\DoctorFacilityAgreementType;
use App\Http\Controllers\Controller;
use App\Models\DoctorFacility;
use App\Models\DoctorFacilityAgreement;
use App\Services\DoctorFacilityAgreementService;
use App\Services\FacilityAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class DoctorFacilityAgreementController extends Controller
{
    public function __construct(
        private DoctorFacilityAgreementService $agreementService,
        private FacilityAccessService $facilityAccess,
    ) {}

    private function scopedRelationship(Request $request, int $doctorFacilityId): ?DoctorFacility
    {
        $facility = $this->facilityAccess->authorizedFacility($request, ['facility-admin']);
        if (! $facility) {
            return null;
        }

        return DoctorFacility::where('id', $doctorFacilityId)
            ->where('facility_id', $facility->id)
            ->first();
    }

    public function index(Request $request): JsonResponse
    {
        $facility = $this->facilityAccess->authorizedFacility($request, ['facility-admin']);
        if (! $facility) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $doctorId = $request->filled('doctor_id') ? $request->doctor_id : null;

        $query = DoctorFacility::where('facility_id', $facility->id)
            ->where('is_active', true);

        if ($doctorId) {
            $query->where('doctor_id', $doctorId);
        }

        $relationships = $query->with(['doctor:id,name', 'agreements' => fn ($q) => $q->orderByDesc('effective_from')])
            ->get();

        $data = $relationships->map(fn ($df) => [
            'doctor_facility_id' => $df->id,
            'doctor' => $df->doctor ? ['id' => $df->doctor->id, 'name' => $df->doctor->name] : null,
            'current_agreement' => $df->agreements->first()?->toArray(),
            'agreement_history' => $df->agreements->values(),
        ]);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'doctor_facility_id' => 'required|exists:doctor_facilities,id',
            'agreement_type' => 'required|in:revenue_share,fixed,salaried,custom',
            'doctor_share_percentage' => 'nullable|numeric|min:0|max:100',
            'facility_share_percentage' => 'nullable|numeric|min:0|max:100',
            'fixed_doctor_amount' => 'nullable|numeric|min:0',
            'fixed_facility_amount' => 'nullable|numeric|min:0',
            'effective_from' => 'required|date',
            'effective_until' => 'nullable|date|after:effective_from',
            'notes' => 'nullable|string',
        ]);

        $df = $this->scopedRelationship($request, (int) $request->doctor_facility_id);
        if (! $df) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $type = DoctorFacilityAgreementType::from($request->agreement_type);

        try {
            $agreement = $this->agreementService->create(
                $df,
                $type,
                $this->withDateFields($request->only([
                    'doctor_share_percentage', 'facility_share_percentage',
                    'fixed_doctor_amount', 'fixed_facility_amount',
                    'effective_from', 'effective_until', 'notes',
                ])),
                $request->user()->id,
            );

            return response()->json(['message' => 'Agreement created', 'data' => $agreement], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $agreement = DoctorFacilityAgreement::with(['doctorFacility.doctor', 'doctorFacility.facility'])->find($id);
        if (! $agreement) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $facility = $this->facilityAccess->authorizedFacility($request, ['facility-admin']);
        if (! $facility || $agreement->doctorFacility->facility_id !== $facility->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json($agreement);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $agreement = DoctorFacilityAgreement::find($id);
        if (! $agreement) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $facility = $this->facilityAccess->authorizedFacility($request, ['facility-admin']);
        if (! $facility || $agreement->doctorFacility->facility_id !== $facility->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $request->validate([
            'doctor_share_percentage' => 'nullable|numeric|min:0|max:100',
            'facility_share_percentage' => 'nullable|numeric|min:0|max:100',
            'fixed_doctor_amount' => 'nullable|numeric|min:0',
            'fixed_facility_amount' => 'nullable|numeric|min:0',
            'effective_from' => 'nullable|date',
            'effective_until' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        try {
            $agreement = $this->agreementService->update($agreement, $this->withDateFields($request->all()));

            return response()->json(['message' => 'Agreement updated', 'data' => $agreement]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    private function withDateFields(array $data): array
    {
        if (isset($data['effective_from'])) {
            $data['effective_from'] = Carbon::parse($data['effective_from']);
        }
        if (isset($data['effective_until']) && $data['effective_until'] !== null) {
            $data['effective_until'] = Carbon::parse($data['effective_until']);
        }

        return $data;
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $agreement = DoctorFacilityAgreement::find($id);
        if (! $agreement) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $facility = $this->facilityAccess->authorizedFacility($request, ['facility-admin']);
        if (! $facility || $agreement->doctorFacility->facility_id !== $facility->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $this->agreementService->deactivate($agreement);

        return response()->json(['message' => 'Agreement deactivated']);
    }
}
