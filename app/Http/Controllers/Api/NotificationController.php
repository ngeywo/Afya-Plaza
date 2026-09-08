<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicSession;
use App\Models\Doctor;
use App\Models\Follow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $perPage = min((int) $request->get('per_page', 15), 50);
        $unreadOnly = $request->boolean('unread_only');

        $query = $user->notifications()->orderBy('created_at', 'desc');
        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn ($n) => $this->format($n)),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['count' => 0, 'authenticated' => false]);
        }

        return response()->json([
            'count' => $user->unreadNotifications()->count(),
            'authenticated' => true,
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }
        $notification = $user->notifications()->where('id', $id)->first();
        if (! $notification) {
            return response()->json(['error' => 'Notification not found.'], 404);
        }
        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        return response()->json(['data' => $this->format($notification->fresh())]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }
        $count = $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read.',
            'marked' => $count,
        ]);
    }

    public function followingDoctors(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $doctorIds = Follow::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->pluck('doctor_id');

        $doctors = Doctor::with(['specialties'])
            ->whereIn('id', $doctorIds)
            ->where('is_active', true)
            ->get();

        $result = $doctors->map(function (Doctor $doctor) {
            $nextSession = ClinicSession::with(['facility', 'facilityLocation'])
                ->where('doctor_id', $doctor->id)
                ->where('status', 'confirmed')
                ->where('session_date', '>=', today()->toDateString())
                ->orderBy('session_date')
                ->orderBy('start_time')
                ->first();

            $primary = $doctor->specialties->first(fn ($s) => $s->pivot?->is_primary)
                ?? $doctor->specialties->first();

            return [
                'id' => $doctor->id,
                'slug' => $doctor->slug,
                'name' => $doctor->display_name,
                'avatar' => $doctor->avatar,
                'is_verified' => $doctor->is_verified,
                'specialty' => $primary ? [
                    'id' => $primary->id,
                    'name' => $primary->name,
                    'icon' => $primary->icon,
                ] : null,
                'next_clinic' => $nextSession ? [
                    'id' => $nextSession->id,
                    'date' => $nextSession->session_date->format('Y-m-d'),
                    'day' => $nextSession->session_date->format('l, M j, Y'),
                    'start_time' => substr($nextSession->start_time, 0, 5),
                    'end_time' => substr($nextSession->end_time, 0, 5),
                    'facility_name' => $nextSession->facility?->name,
                    'facility_city' => $nextSession->facility?->city,
                    'available_slots' => $nextSession->available_slots,
                    'max_appointments' => $nextSession->max_appointments,
                ] : null,
            ];
        });

        return response()->json([
            'data' => $result->values(),
            'meta' => ['total' => $result->count()],
        ]);
    }

    private function format(DatabaseNotification $n): array
    {
        $data = $n->data ?? [];

        return [
            'id' => $n->id,
            'type' => $n->type,
            'category' => $data['category'] ?? null,
            'title' => $data['title'] ?? '',
            'message' => $data['message'] ?? '',
            'data' => $data,
            'read_at' => $n->read_at?->toIso8601String(),
            'is_unread' => is_null($n->read_at),
            'created_at' => $n->created_at?->toIso8601String(),
            'created_human' => $n->created_at?->diffForHumans(),
        ];
    }
}
