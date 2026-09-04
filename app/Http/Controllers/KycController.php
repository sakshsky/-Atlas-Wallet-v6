<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;
use Illuminate\Support\Facades\Cache;

class KycController extends Controller
{
    public function store(Request $request, AuditService $audit): JsonResponse
    {
        $data = $request->validate(['legal_name' => ['required', 'string', 'max:160'], 'date_of_birth' => ['required', 'date', 'before:-18 years'], 'country_code' => ['required', 'string', 'size:2'], 'document_type' => ['required', Rule::in(['passport', 'national_id', 'drivers_license'])], 'document_number' => ['required', 'string', 'max:80'], 'document_front' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'], 'document_back' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192']]);
        $front = $request->file('document_front')->store('kyc/'.$request->user()->id, 'local');
        $back = $request->file('document_back')?->store('kyc/'.$request->user()->id, 'local');
        try {
            $submission = DB::transaction(function () use ($request, $data, $front, $back) {
                $user = User::lockForUpdate()->findOrFail($request->user()->id);
                abort_if(in_array($user->kyc_status, ['pending', 'approved']), 422, 'A KYC review is already active or approved.');
                $payload = collect($data)->except(['document_front', 'document_back'])->all();
                $payload['country_code'] = strtoupper($payload['country_code']); $payload['document_front_path'] = $front; $payload['document_back_path'] = $back;
                $item = $user->kycSubmissions()->create($payload + ['status' => 'pending']);
                $user->update(['kyc_status' => 'pending', 'kyc_verified_at' => null]);
                return $item;
            }, 3);
        } catch (Throwable $error) { Storage::disk('local')->delete(array_filter([$front, $back])); throw $error; }
        $audit->record($request, 'kyc.submitted', $submission);
        Cache::forget('admin.stats');
        return response()->json($submission, 201);
    }
}
