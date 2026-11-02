<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\DashboardReport;
use App\Support\ReportPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        [$period, $anchor, $until] = ReportPeriod::fromRequest($request, 'day');

        return response()->json(['status' => 'success', 'data' => (new DashboardReport($period, $anchor, $until))->toArray()]);
    }
}
