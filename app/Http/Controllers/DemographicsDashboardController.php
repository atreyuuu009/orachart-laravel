<?php
namespace App\Http\Controllers;
use App\Services\Analytics\DemographicsAnalytics;
use App\Support\Audit;
use App\Support\DateRange;
use Illuminate\Http\Request;
class DemographicsDashboardController extends Controller
{
 public function index(){Audit::log('view','pat_patients',null,'Admin opened patient demographics dashboard');return view('admin.demographics');}
 public function data(Request $request,DemographicsAnalytics $analytics){return response()->json($analytics->data(DateRange::fromRequest($request)));}
}
