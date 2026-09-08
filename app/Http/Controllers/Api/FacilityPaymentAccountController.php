<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EntitlementException;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\FacilityPaymentAccount;
use App\Services\EntitlementService;
use App\Services\FacilityAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 23 (Facility Payments): facility-admin management of the payment
 * accounts shown to patients. Never stores secrets — provider credentials stay
 * with the payment gateway.
 */
class FacilityPaymentAccountController extends Controller
{
    public function __construct(private FacilityAccessService $facilityAccess) {}

    private function scopedQuery(Request $request, ?int $facilityId = null)
    {
        $ids = $this->facilityAccess->managedIds($request->user());
        $query = FacilityPaymentAccount::query();

        if ($ids === null) {
            if ($facilityId) {
                return $query->where('facility_id', $facilityId);
            }

            return $query;
        }

        if ($facilityId && ! in_array($facilityId, $ids)) {
            return null;
        }

        return $query->whereIn('facility_id', $ids);
    }

    /** GET /api/facility/payment-accounts */
    public function index(Request $request): JsonResponse
    {
        $query = $this->scopedQuery($request, $request->filled('facility_id') ? (int) $request->facility_id : null);
        if ($query === null) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json(['data' => $query->orderBy('facility_id')->orderBy('is_primary', 'desc')->get()->map(fn ($a) => $this->format($a))]);
    }

    /** POST /api/facility/payment-accounts */
    public function store(Request $request, EntitlementService $entitlements): JsonResponse
    {
        $user = $request->user();
        if (! ($user->isSuperAdmin() || $user->hasRole('platform-admin') || $user->hasRole('facility-admin'))) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $facilityId = (int) $request->input('facility_id');
        if (! $this->facilityAccess->resolveExplicit($request, $facilityId)) {
            return response()->json(['error' => 'You are not authorized to manage this facility.'], 403);
        }

        try {
            $facility = Facility::findOrFail($facilityId);
            if (! $entitlements->feature($facility, 'facility_payments')) {
                throw EntitlementException::featureDisabled('facility_payments');
            }
        } catch (EntitlementException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => $e->businessCode()], $e->httpStatus());
        }

        $validated = $request->validate([
            'facility_id' => 'required|integer|exists:facilities,id',
            'provider' => 'required|string|max:50',
            'account_type' => 'required|string|in:paybill,till_number,bank,mpesa_express,cash',
            'account_name' => 'required|string|max:191',
            'account_number' => 'required|string|max:191',
            'is_primary' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        $account = FacilityPaymentAccount::create([
            ...$validated,
            'is_primary' => $validated['is_primary'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if ($account->is_primary) {
            FacilityPaymentAccount::where('facility_id', $account->facility_id)
                ->where('id', '!=', $account->id)
                ->update(['is_primary' => false]);
        }

        AuditLog::record($user->id, 'facility_payment_account.created', FacilityPaymentAccount::class, $account->id, $account->account_name.' @ facility '.$account->facility_id);

        return response()->json(['data' => $this->format($account), 'message' => 'Payment account saved.'], 201);
    }

    /** PUT /api/facility/payment-accounts/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $query = $this->scopedQuery($request);
        if ($query === null) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $account = $query->findOrFail($id);

        $validated = $request->validate([
            'provider' => 'sometimes|string|max:50',
            'account_type' => 'sometimes|string|in:paybill,till_number,bank,mpesa_express,cash',
            'account_name' => 'sometimes|string|max:191',
            'account_number' => 'sometimes|string|max:191',
            'is_primary' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'notes' => 'sometimes|nullable|string|max:1000',
        ]);

        $account->update($validated);

        if (! empty($validated['is_primary'])) {
            FacilityPaymentAccount::where('facility_id', $account->facility_id)
                ->where('id', '!=', $account->id)
                ->update(['is_primary' => false]);
        }

        AuditLog::record($request->user()->id, 'facility_payment_account.updated', FacilityPaymentAccount::class, $account->id, $account->account_name);

        return response()->json(['data' => $this->format($account->fresh()), 'message' => 'Payment account updated.']);
    }

    /** DELETE /api/facility/payment-accounts/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $query = $this->scopedQuery($request);
        if ($query === null) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $account = $query->findOrFail($id);
        if ($account->payments()->exists()) {
            return response()->json(['error' => 'This account has payments attached and cannot be deleted. Deactivate it instead.'], 422);
        }

        AuditLog::record($request->user()->id, 'facility_payment_account.deleted', FacilityPaymentAccount::class, $account->id, $account->account_name);
        $account->delete();

        return response()->json(['message' => 'Payment account removed.']);
    }

    private function format(FacilityPaymentAccount $a): array
    {
        return [
            'id' => $a->id,
            'facility_id' => $a->facility_id,
            'provider' => $a->provider,
            'account_type' => $a->account_type,
            'account_name' => $a->account_name,
            'account_number_masked' => $a->maskedNumber(),
            'is_primary' => $a->is_primary,
            'is_active' => $a->is_active,
            'notes' => $a->notes,
        ];
    }
}
