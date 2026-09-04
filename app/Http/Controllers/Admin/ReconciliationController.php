<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\ReconciliationRun;
use App\Services\AuditService;
use App\Services\ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class ReconciliationController extends Controller
{
    public function index(): JsonResponse { return response()->json(ReconciliationRun::with('discrepancies.wallet.currency')->latest()->limit(30)->get()); }
    public function store(Request $request, ReconciliationService $service, AuditService $audit): JsonResponse { $run = $service->run(); $audit->record($request, 'reconciliation.completed', $run, ['status' => $run->status, 'discrepancies' => $run->discrepancy_count]); return response()->json($run, 201); }
}
