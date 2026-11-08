<?php

namespace App\Http\Controllers;

use App\Models\ClinicHour;
use App\Models\Dentist;
use App\Models\ServiceCategory;
use App\Models\Service;

class LandingController extends Controller
{
    public function index()
    {
        $categories = ServiceCategory::with(['services' => fn ($q) => $q->listed()->with('packageTerm')->orderBy('service_id')])
            ->orderBy('category_id')->get()->filter(fn ($c) => $c->services->isNotEmpty());

        $dentists = Dentist::with('user', 'specialties')->where('is_accepting_bookings', 1)
            ->whereHas('user', fn ($q) => $q->where('is_active', 1))->get();

        return view('landing', [
            'categories' => $categories,
            'dentists' => $dentists,
            'serviceCount' => Service::bookable()->count(),
            'hours' => ClinicHour::orderBy('day_of_week')->get(),
        ]);
    }
}
