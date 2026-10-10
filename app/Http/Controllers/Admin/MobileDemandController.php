<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Travel\MobileDemandGate;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MobileDemandController extends Controller
{
    public function __invoke(Request $request,MobileDemandGate $gate): View
    {
        $validated=$request->validate(['days'=>['nullable','integer','min:30','max:180']]);
        return view('admin.travel.mobile-gate',[
            'report'=>$gate->evaluate((int)($validated['days']??90)),
        ]);
    }
}
