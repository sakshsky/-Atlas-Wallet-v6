<?php
namespace App\Http\Controllers;
use App\Models\Beneficiary;
use App\Models\Wallet;
use App\Services\AuditService;
use App\Services\MoneyMovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
class MoneyMovementController extends Controller
{
    public function index(Request $request): JsonResponse { return response()->json($request->user()->moneyMovements()->with(['wallet.currency', 'beneficiary'])->latest()->paginate(25)); }
    public function deposit(Request $request, MoneyMovementService $service, AuditService $audit): JsonResponse { $data = $request->validate(['wallet_id' => ['required', 'exists:wallets,id'], 'amount' => ['required', 'numeric', 'gt:0']]); $movement = $service->requestDeposit($request->user(), Wallet::findOrFail($data['wallet_id']), (string) $data['amount']); $audit->record($request, 'deposit.requested', $movement); Cache::forget('admin.stats'); return response()->json($movement, 201); }
    public function withdraw(Request $request, MoneyMovementService $service, AuditService $audit): JsonResponse { $data = $request->validate(['wallet_id' => ['required', 'exists:wallets,id'], 'beneficiary_id' => ['required', 'exists:beneficiaries,id'], 'amount' => ['required', 'numeric', 'gt:0']]); $movement = $service->requestWithdrawal($request->user(), Wallet::findOrFail($data['wallet_id']), Beneficiary::findOrFail($data['beneficiary_id']), (string) $data['amount']); $audit->record($request, 'withdrawal.requested', $movement); Cache::forget('admin.stats'); return response()->json($movement, 201); }
}
