<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['currency_id' => ['required', 'exists:currencies,id']]);
        abort_unless(Currency::findOrFail($data['currency_id'])->is_active, 422, 'Currency is inactive.');
        $wallet = $request->user()->wallets()->firstOrCreate(['currency_id' => $data['currency_id']], ['balance' => 0, 'status' => 'active']);
        return response()->json($wallet->load('currency'), $wallet->wasRecentlyCreated ? 201 : 200);
    }

    public function transactions(Request $request, int $wallet): JsonResponse
    {
        $owned = $request->user()->wallets()->findOrFail($wallet);
        return response()->json($owned->transactions()->latest()->paginate(25));
    }
}
