<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TradingSetting;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradingSettingController extends Controller
{
    public function update(Request $request, AuditService $audit): JsonResponse
    {
        $data = $request->validate(['profit_percentage' => ['required', 'numeric', 'between:0,25']]);
        $setting = TradingSetting::current(); $setting->update(['profit_percentage' => $data['profit_percentage'], 'updated_by' => $request->user()->id]);
        $audit->record($request, 'trading.profit_percentage_updated', $setting, ['profit_percentage' => $setting->profit_percentage]);
        return response()->json($setting);
    }
}
