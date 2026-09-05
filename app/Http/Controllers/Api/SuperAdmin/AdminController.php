<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lists every administrator (doctor + facility admin) in the system
 * so the Super Admin can find and inspect any workspace owner.
 */
class AdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:200',
            'role' => 'nullable|in:doctor,facility-admin,all',
            'status' => 'nullable|in:active,inactive,verified,unverified,all',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $search = $request->input('search');
        $role = $request->input('role', 'all');
        $status = $request->input('status', 'all');
        $perPage = (int) $request->input('per_page', 20);
        $page = max(1, (int) $request->input('page', 1));

        $items = collect();

        if ($role === 'all' || $role === 'doctor') {
            $doctorRows = Doctor::query()
                ->with(['user:id,name,email,phone,is_active,is_verified', 'specialties:id,name'])
                ->when($search, fn($q) => $q->where('display_name', 'like', "%{$search}%"))
                ->when(in_array($status, ['active', 'inactive']), function ($q) use ($status) {
                    $q->where('doctors.is_active', $status === 'active');
                })
                ->when(in_array($status, ['verified', 'unverified']), function ($q) use ($status) {
                    $q->where('doctors.is_verified', $status === 'verified');
                })
                ->orderByDesc('doctors.created_at')
                ->get()
                ->map(function ($d) {
                    return [
                        'id' => $d->id,
                        'type' => 'doctor',
                        'name' => $d->display_name,
                        'email' => $d->user?->email,
                        'phone' => $d->user?->phone,
                        'is_active' => (bool) $d->is_active,
                        'is_verified' => (bool) $d->is_verified,
                        'consultation_fee' => $d->consultation_fee,
                        'qualifications' => $d->qualifications,
                        'specialty_names' => $d->specialties->pluck('name')->all(),
                        'workspace_type' => 'doctor',
                        'workspace_url' => "/admin/inspect/doctor/{$d->id}",
                        'detail_url' => "/admin/inspect/doctor/{$d->id}",
                    ];
                });
            $items = $items->merge($doctorRows);
        }

        if ($role === 'all' || $role === 'facility-admin') {
            $adminRows = User::query()
                ->whereHas('roles', fn($q) => $q->where('slug', 'facility-admin'))
                ->with(['roles:roles.id,roles.slug', 'facilities' => function ($q) {
                    $q->select('facilities.id', 'facilities.name', 'facilities.city', 'facilities.slug');
                }])
                ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
                ->when(in_array($status, ['active', 'inactive']), function ($q) use ($status) {
                    $q->where('users.is_active', $status === 'active');
                })
                ->orderByDesc('users.created_at')
                ->get()
                ->map(function ($u) {
                    $primary = $u->facilities->firstWhere('pivot.is_primary', true);
                    $first = $primary ?? $u->facilities->first();
                    $facility = $first;
                    return [
                        'id' => $u->id,
                        'type' => 'facility-admin',
                        'name' => $u->name,
                        'email' => $u->email,
                        'phone' => $u->phone,
                        'is_active' => (bool) $u->is_active,
                        'is_verified' => (bool) $u->is_verified,
                        'workspace_type' => 'facility',
                        'facility_id' => $facility?->id,
                        'facility_name' => $facility?->name,
                        'facility_city' => $facility?->city,
                        'facility_count' => $u->facilities->count(),
                        'workspace_url' => $facility ? "/admin/inspect/facility/{$facility->id}" : null,
                        'detail_url' => $facility ? "/admin/inspect/facility/{$facility->id}" : null,
                    ];
                });
            $items = $items->merge($adminRows);
        }

        if (in_array($status, ['verified', 'unverified'])) {
            $items = $items->filter(fn($a) => $a['is_verified'] === ($status === 'verified'));
        }

        $total = $items->count();
        $paginated = $items->forPage($page, $perPage)->values();

        return response()->json([
            'data' => $paginated,
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }
}
