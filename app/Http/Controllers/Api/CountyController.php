<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\County;
use Illuminate\Http\JsonResponse;

/**
 * Phase 3: County listing for location-based search filters.
 * Section 7: Location hierarchy support.
 */
class CountyController extends Controller
{
    /**
     * GET /api/counties
     *
     * Returns counties with facility count for location filter dropdown.
     * Only counties that have active facilities are returned.
     */
    public function index(): JsonResponse
    {
        $counties = County::where('is_active', true)
            ->withCount(['facilities' => fn ($q) => $q->where('is_active', true)])
            ->having('facilities_count', '>', 0)
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'facility_count' => $c->facilities_count,
            ]);

        return response()->json(['data' => $counties]);
    }
}
