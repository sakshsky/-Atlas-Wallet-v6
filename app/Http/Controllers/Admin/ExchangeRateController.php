<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    public function index(): JsonResponse { return response()->json(ExchangeRate::with(['fromCurrency', 'toCurrency'])->latest('effective_at')->paginate(30)); }
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['from_currency_id' => ['required', 'different:to_currency_id', 'exists:currencies,id'], 'to_currency_id' => ['required', 'exists:currencies,id'], 'rate' => ['required', 'numeric', 'gt:0'], 'effective_at' => ['nullable', 'date']]);
        $data['effective_at'] ??= now(); $data['created_by'] = $request->user()->id;
        return response()->json(ExchangeRate::create($data)->load(['fromCurrency', 'toCurrency']), 201);
    }
}
