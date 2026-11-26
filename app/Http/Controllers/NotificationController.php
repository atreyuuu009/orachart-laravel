<?php

namespace App\Http\Controllers;

use App\Services\PatientNotifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request, PatientNotifications $notifications): JsonResponse
    {
        return response()->json($notifications->forPatient($request->user()));
    }

    public function seen(Request $request, PatientNotifications $notifications): JsonResponse
    {
        $notifications->markSeen($request->user());

        return response()->json(['ok' => true]);
    }
}
