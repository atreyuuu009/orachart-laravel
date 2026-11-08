<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->load('role');
        $base = fn () => Appointment::visibleTo($user);

        $stats = [
            'total' => $base()->count(),
            'pending' => $base()->where('status', 'pending')->count(),
            'confirmed' => $base()->where('status', 'confirmed')->count(),
            'completed' => $base()->where('status', 'completed')->count(),
            'cancelled' => $base()->where('status', 'cancelled')->count(),
        ];

        $with = ['patient', 'service', 'dentist.user'];
        $recent = $base()->with($with)->orderByDesc('appointment_id')->limit(6)->get();
        $upcoming = $base()->with($with)->open()->where('start_at', '>=', now())->orderBy('start_at')->limit(6)->get();
        $today = $user->hasRole('patient') ? collect()
            : $base()->with($with)->open()->whereDate('start_at', today())->orderBy('start_at')->get();
        $needsReschedule = $user->hasRole('patient') ? 0 : $base()->open()->where('needs_reschedule', 1)->count();
        $patientCount = $user->hasRole('admin', 'clinic_staff') ? Patient::count() : null;

        $categories = $user->hasRole('patient')
            ? ServiceCategory::with(['services' => fn ($q) => $q->listed()->with('packageTerm')])->orderBy('category_id')->get()->filter(fn ($c) => $c->services->isNotEmpty())
            : collect();

        return view('dashboard', compact('user', 'stats', 'recent', 'upcoming', 'today', 'needsReschedule', 'patientCount', 'categories'));
    }
}
