<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\MoneyMovement;
use App\Services\AuditService;
use App\Services\MoneyMovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Notifications\TransactionNotification;
use App\Services\WebhookService;
class MoneyMovementController extends Controller
{
    public function index(Request $request): JsonResponse { $query = MoneyMovement::with(['user:id,name,email,risk_level', 'wallet.currency', 'beneficiary', 'reviewer:id,name']); if ($request->filled('status')) $query->where('status', $request->string('status')); return response()->json($query->latest()->paginate(50)); }
    public function approve(Request $request, MoneyMovement $moneyMovement, MoneyMovementService $service, AuditService $audit): JsonResponse { $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]); $movement = $service->approve($moneyMovement, $request->user(), $data['notes'] ?? null); $audit->record($request, 'movement.approved', $movement); return response()->json($movement); }
    public function reject(Request $request, MoneyMovement $moneyMovement, MoneyMovementService $service, AuditService $audit): JsonResponse { $data = $request->validate(['notes' => ['required', 'string', 'min:10', 'max:1000']]); $movement = $service->reject($moneyMovement, $request->user(), $data['notes']); $audit->record($request, 'movement.rejected', $movement); return response()->json($movement); }
    public function complete(Request $request, MoneyMovement $moneyMovement, MoneyMovementService $service, AuditService $audit, WebhookService $webhooks): JsonResponse { $movement = $service->complete($moneyMovement, $request->user()); $audit->record($request, 'movement.settled', $movement); $movement->user->notify(new TransactionNotification(['id' => $movement->id, 'reference' => $movement->reference, 'amount' => $movement->amount, 'wallet_id' => $movement->wallet_id], $movement->direction.'_completed')); $webhooks->dispatch($movement->direction.'.completed', ['reference' => $movement->reference, 'amount' => $movement->amount, 'currency' => $movement->wallet->currency->code]); return response()->json($movement); }
}
