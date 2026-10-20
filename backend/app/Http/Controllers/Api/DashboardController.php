<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\DashboardReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['nullable', 'in:day,month,year'],
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], [
            'period.in' => 'Periode harus harian, bulanan, atau tahunan.',
            'date.date_format' => 'Tanggal tidak valid.',
            'date.before_or_equal' => 'Tanggal tidak boleh setelah hari ini.',
        ]);

        $report = new DashboardReport(
            $data['period'] ?? 'day',
            isset($data['date']) ? Carbon::parse($data['date']) : now(),
        );

        return response()->json(['status' => 'success', 'data' => $report->toArray()]);
    }
}
