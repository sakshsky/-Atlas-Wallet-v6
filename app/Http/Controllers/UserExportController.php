<?php
namespace App\Http\Controllers;
use App\Jobs\ExportUserData;
use App\Models\UserExport;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class UserExportController extends Controller
{
    public function index(Request $request): JsonResponse { return response()->json($request->user()->exports()->latest()->limit(20)->get()); }
    public function store(Request $request, AuditService $audit): JsonResponse { $export = UserExport::create(['reference' => (string) Str::uuid(), 'user_id' => $request->user()->id, 'status' => 'pending']); ExportUserData::dispatch($export->id); $audit->record($request, 'export.requested', $export); return response()->json($export, 202); }
    public function download(Request $request, UserExport $userExport) { abort_unless($userExport->user_id === $request->user()->id, 404); abort_unless($userExport->status === 'ready' && $userExport->expires_at?->isFuture() && $userExport->path && Storage::disk('local')->exists($userExport->path), 404); return Storage::disk('local')->download($userExport->path, 'atlas-wallet-export.json'); }
}
