<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\HseDashboardReport;
use App\Support\ReportPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HseDashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        [$period, $anchor, $until] = ReportPeriod::fromRequest($request, 'month');

        return response()->json(['status' => 'success', 'data' => (new HseDashboardReport($period, $anchor, $until))->toArray()]);
    }
}
