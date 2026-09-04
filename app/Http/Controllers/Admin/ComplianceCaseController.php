<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\ComplianceCase;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class ComplianceCaseController extends Controller
{
    public function index(): JsonResponse { return response()->json(ComplianceCase::with('user:id,name,email,risk_level')->latest()->paginate(50)); }
    public function update(Request $request, ComplianceCase $complianceCase, AuditService $audit): JsonResponse { $data = $request->validate(['status' => ['required', Rule::in(['reviewing', 'cleared', 'escalated', 'closed'])], 'resolution' => ['nullable', 'string', 'max:2000']]); if (in_array($data['status'], ['cleared', 'closed']) && blank($data['resolution'] ?? null)) abort(422, 'A resolution is required when closing a case.'); $complianceCase->update($data + ['assigned_to' => $request->user()->id, 'resolved_by' => in_array($data['status'], ['cleared', 'closed']) ? $request->user()->id : null, 'resolved_at' => in_array($data['status'], ['cleared', 'closed']) ? now() : null]); $audit->record($request, 'compliance.case_updated', $complianceCase, ['status' => $data['status']]); return response()->json($complianceCase->fresh('user')); }
}
