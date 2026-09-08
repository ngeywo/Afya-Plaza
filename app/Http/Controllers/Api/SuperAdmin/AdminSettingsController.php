<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Platform-wide configuration. Super-admin only; every change is audited.
 */
class AdminSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => PlatformSetting::pluck('value', 'key')->toArray()]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable|max:500',
        ]);

        $allowed = PlatformSetting::allowedKeys();
        $data = array_intersect_key($request->input('settings') ?? [], array_flip($allowed));

        foreach ($data as $key => $value) {
            PlatformSetting::updateOrCreate(
                ['key' => $key],
                ['value' => is_scalar($value) ? (string) $value : null]
            );
        }

        AuditLog::record(
            $request->user()->id,
            'platform.settings_updated',
            'platform_settings',
            null,
            'Platform settings',
            null,
            ['keys' => array_keys($data)],
            $request->input('reason'),
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json([
            'message' => 'Platform settings saved.',
            'data' => PlatformSetting::pluck('value', 'key')->toArray(),
        ]);
    }
}
