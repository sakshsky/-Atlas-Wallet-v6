<?php
namespace App\Http\Controllers;
use App\Models\Beneficiary;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class BeneficiaryController extends Controller
{
    public function index(Request $request): JsonResponse { return response()->json($request->user()->beneficiaries()->with('currency')->latest()->get()); }
    public function store(Request $request, AuditService $audit): JsonResponse
    {
        abort_unless($request->user()->hasTwoFactorEnabled(), 422, 'Enable two-factor authentication before adding a withdrawal destination.');
        $data = $request->validate(['currency_id' => ['required', 'exists:currencies,id'], 'label' => ['required', 'string', 'max:100'], 'destination' => ['required', 'string', 'max:500'], 'network' => ['nullable', 'string', 'max:30']]);
        $beneficiary = Beneficiary::create($data + ['user_id' => $request->user()->id, 'is_verified' => true, 'verified_at' => now()]); $audit->record($request, 'beneficiary.created', $beneficiary, ['currency_id' => $beneficiary->currency_id, 'network' => $beneficiary->network]); return response()->json($beneficiary->load('currency'), 201);
    }
    public function destroy(Request $request, Beneficiary $beneficiary, AuditService $audit): JsonResponse { abort_unless($beneficiary->user_id === $request->user()->id, 404); $audit->record($request, 'beneficiary.removed', $beneficiary); $beneficiary->delete(); return response()->json(['message' => 'Beneficiary removed.']); }
}
