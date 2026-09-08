<?php

namespace App\Http\Controllers\Api;

use App\Enums\DoctorRelationshipStatus;
use App\Http\Controllers\Controller;
use App\Models\Facility;
use Illuminate\Http\JsonResponse;

class FacilityController extends Controller
{
    public function index(): JsonResponse
    {
        $facilities = Facility::with('county')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'slug' => $f->slug,
                'city' => $f->city,
                'county' => $f->county?->name,
                'address' => $f->address,
                'phone' => $f->phone,
                'is_verified' => $f->is_verified,
                'type' => $f->type,
                'doctor_count' => $f->doctors()->wherePivot('is_active', true)->count(),
            ]);

        return response()->json(['data' => $facilities]);
    }

    public function show(string $slug): JsonResponse
    {
        $facility = Facility::where('slug', $slug)->with('county')->firstOrFail();

        return response()->json(['data' => [
            'id' => $facility->id,
            'name' => $facility->name,
            'slug' => $facility->slug,
            'description' => $facility->description,
            'address' => $facility->address,
            'city' => $facility->city,
            'county' => $facility->county?->name,
            'phone' => $facility->phone,
            'email' => $facility->email,
            'is_verified' => $facility->is_verified,
            'type' => $facility->type,
            'doctors' => $facility->doctors()
                ->wherePivot('is_active', true)
                ->with('specialties')
                ->get()
                ->map(fn ($d) => [
                    'id' => $d->id, 'name' => $d->display_name, 'slug' => $d->slug,
                    'avatar' => $d->avatar, 'is_verified' => $d->is_verified,
                    'consultation_fee' => $d->pivot->consultation_fee,
                    'specialties' => $d->specialties->map(fn ($s) => $s->name),
                ]),
        ]]);
    }

    /**
     * Phase 23: GET /api/facilities/{slug}/doctors — relationship-driven list of
     * doctors licensed (ACTIVE) at this facility, each with their facility services.
     */
    public function doctors(string $slug): JsonResponse
    {
        $facility = Facility::where('slug', $slug)->firstOrFail();

        $relationships = $facility->doctorFacilities()
            ->where('status', DoctorRelationshipStatus::ACTIVE)
            ->with(['doctor.specialties', 'services'])
            ->get();

        return response()->json(['data' => $relationships->map(fn ($df) => [
            'id' => $df->doctor_id,
            'name' => $df->doctor?->display_name,
            'slug' => $df->doctor?->slug,
            'avatar' => $df->doctor?->avatar,
            'is_verified' => $df->doctor?->is_verified,
            'consultation_fee' => $df->consultation_fee,
            'accepts_appointments' => $df->accepts_appointments,
            'specialties' => $df->doctor?->specialties->map(fn ($s) => $s->name) ?? [],
            'services' => $df->services->filter(fn ($s) => $s->is_active)->map(fn ($s) => [
                'name' => $s->service_name,
                'price' => $s->price,
                'duration_minutes' => $s->duration_minutes,
            ])->values(),
        ])]);
    }
}
