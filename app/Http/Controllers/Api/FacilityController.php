<?php

namespace App\Http\Controllers\Api;

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
}
