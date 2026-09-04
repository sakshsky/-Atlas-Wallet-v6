<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KycSubmission;
use App\Models\User;
use App\Services\AuditService;
use App\Notifications\KycStatusNotification;
use Illuminate\Support\Facades\Cache;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class KycReviewController extends Controller
{
    public function index(): JsonResponse { return response()->json(KycSubmission::with('user:id,name,email')->latest()->paginate(30)); }
    public function document(KycSubmission $kycSubmission, string $side)
    {
        abort_unless(in_array($side, ['front', 'back']), 404);
        $path = $side === 'front' ? $kycSubmission->document_front_path : $kycSubmission->document_back_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->download($path);
    }
    public function update(Request $request, KycSubmission $kycSubmission, AuditService $audit, WebhookService $webhooks): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])], 'review_notes' => ['nullable', 'string', 'max:1000']]);
        $reviewed = DB::transaction(function () use ($request, $kycSubmission, $data) {
            $submission = KycSubmission::lockForUpdate()->findOrFail($kycSubmission->id);
            abort_unless($submission->status === 'pending', 422, 'This submission has already been reviewed.');
            $user = User::lockForUpdate()->findOrFail($submission->user_id);
            $submission->update($data + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            $user->update(['kyc_status' => $data['status'], 'kyc_verified_at' => $data['status'] === 'approved' ? now() : null]);
            return $submission;
        }, 3);
        $audit->record($request, 'kyc.'.$data['status'], $reviewed);
        $reviewed->user->notify(new KycStatusNotification($data['status'], $data['review_notes'] ?? null)); Cache::forget('admin.stats');
        $webhooks->dispatch('kyc.'.$data['status'], ['submission_id' => $reviewed->id, 'user_id' => $reviewed->user_id, 'status' => $data['status']]);
        return response()->json($reviewed->fresh('user:id,name,email,kyc_status'));
    }
}
