<?php
namespace App\Http\Controllers;
use App\Services\AuditService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function send(Request $request, AuditService $audit): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) return response()->json(['message' => 'Email is already verified.'], 422);
        $request->user()->sendEmailVerificationNotification(); $audit->record($request, 'auth.verification_sent', $request->user());
        return response()->json(['message' => 'Verification email sent.']);
    }
    public function verify(EmailVerificationRequest $request, AuditService $audit)
    {
        if (!$request->user()->hasVerifiedEmail()) { $request->fulfill(); $audit->record($request, 'auth.email_verified', $request->user()); }
        return redirect('/?verified=1');
    }
}
