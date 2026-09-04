<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;

class CurrencyController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'between:2,10', 'unique:currencies,code'], 'name' => ['required', 'string', 'max:80'], 'symbol' => ['required', 'string', 'max:8'], 'type' => ['required', Rule::in(['fiat', 'crypto'])], 'precision' => ['required', 'integer', 'between:0,18'], 'market_price_usd' => ['required', 'numeric', 'gt:0'], 'is_tradeable' => ['sometimes', 'boolean']]);
        $data['code'] = strtoupper($data['code']);
        $currency = Currency::create($data + ['is_active' => true, 'is_tradeable' => $data['is_tradeable'] ?? true]); Cache::forget('currencies.active'); Cache::forget('admin.stats'); return response()->json($currency, 201);
    }
    public function update(Request $request, Currency $currency): JsonResponse
    {
        $data = $request->validate(['code' => ['sometimes', 'string', 'between:2,10', Rule::unique('currencies')->ignore($currency)], 'name' => ['sometimes', 'string', 'max:80'], 'symbol' => ['sometimes', 'string', 'max:8'], 'type' => ['sometimes', Rule::in(['fiat', 'crypto'])], 'precision' => ['sometimes', 'integer', 'between:0,18'], 'market_price_usd' => ['sometimes', 'numeric', 'gt:0'], 'is_active' => ['sometimes', 'boolean'], 'is_tradeable' => ['sometimes', 'boolean']]);
        if (isset($data['code'])) $data['code'] = strtoupper($data['code']);
        $currency->update($data); Cache::forget('currencies.active'); Cache::forget('admin.stats'); return response()->json($currency);
    }
}
