<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request, AuditService $audit): JsonResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (!Auth::attempt($credentials, true)) { $audit->record($request, 'auth.login_failed', null, ['email_hash' => hash('sha256', strtolower($credentials['email']))]); return response()->json(['message' => 'The email or password is incorrect.'], 422); }
        if ($request->user()->status !== 'active') { Auth::logout(); return response()->json(['message' => 'This account is not active.'], 403); }
        $request->session()->regenerate();
        if (!$request->user()->hasVerifiedEmail()) { $audit->record($request, 'auth.login_limited_unverified', $request->user()); return response()->json(['user' => $request->user(), 'verification_required' => true]); }
        if ($request->user()->hasTwoFactorEnabled()) { $userId = $request->user()->id; Auth::logout(); $request->session()->put('two_factor_pending_user_id', $userId); return response()->json(['message' => 'Enter your authentication code.', 'two_factor_required' => true], 202); }
        $request->session()->put('two_factor_verified_at', now()->timestamp);
        $audit->record($request, 'auth.login_succeeded', $request->user());
        return response()->json($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return response()->json(['message' => 'Signed out.']);
    }
}
