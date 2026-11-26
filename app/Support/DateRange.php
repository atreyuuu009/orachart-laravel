<?php
namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;

class DateRange
{
    public static function fromRequest(Request $request): array
    {
        $request->validate(['range'=>'nullable|in:today,last7,this_month,last_month,this_year,custom','start'=>'nullable|date','end'=>'nullable|date','dentist'=>'nullable|integer','category'=>'nullable|integer','method'=>'nullable|in:cash,gcash,bank_transfer,card,other','sex'=>'nullable|in:male,female,other','type'=>'nullable|in:all,online,walkin']);
        $tz='Asia/Manila'; $preset=$request->input('range','this_month'); $today=Carbon::now($tz)->startOfDay();
        if($preset==='custom') { $start=Carbon::parse($request->input('start',$today),$tz)->startOfDay(); $end=Carbon::parse($request->input('end',$today),$tz)->startOfDay(); if($end->lt($start)) abort(422,'End date must not be before start date.'); if($start->diffInMonths($end)>24) abort(422,'Custom range is capped at 24 months.'); }
        else { [$start,$end]=match($preset){'today'=>[$today->copy(),$today->copy()],'last7'=>[$today->copy()->subDays(6),$today->copy()],'last_month'=>[$today->copy()->subMonthNoOverflow()->startOfMonth(),$today->copy()->subMonthNoOverflow()->endOfMonth()],'this_year'=>[$today->copy()->startOfYear(),$today->copy() ],default=>[$today->copy()->startOfMonth(),$today->copy()]}; }
        $days=$start->diffInDays($end)+1;
        return ['start'=>$start,'end'=>$end,'previous_start'=>$start->copy()->subDays($days),'previous_end'=>$start->copy()->subDay(),'preset'=>$preset,'filters'=>$request->only(['dentist','category','method','sex','type'])];
    }
}
