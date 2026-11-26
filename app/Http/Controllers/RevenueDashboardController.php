<?php
namespace App\Http\Controllers;
use App\Services\Analytics\RevenueAnalytics;
use App\Support\Audit;
use App\Support\DateRange;
use Illuminate\Http\Request;
class RevenueDashboardController extends Controller
{
 public function index(){ Audit::log('view','pay_payments',null,'Admin opened revenue dashboard'); return view('admin.revenue'); }
 public function data(Request $request,RevenueAnalytics $analytics){ return response()->json($analytics->data(DateRange::fromRequest($request))); }
 public function export(Request $request,RevenueAnalytics $analytics){ Audit::log('view','pay_payments',null,'Admin exported revenue dashboard CSV'); $d=$analytics->data(DateRange::fromRequest($request)); return response()->streamDownload(function()use($d){$o=fopen('php://output','w');fputcsv($o,['Service','Quantity','Billed before discounts']);foreach($d['services'] as $row)fputcsv($o,[$row->label,$row->quantity,$row->amount]);fclose($o);},'revenue-services.csv',['Content-Type'=>'text/csv']); }
}
