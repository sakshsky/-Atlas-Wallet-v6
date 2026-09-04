<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Services\AuditService;
use App\Services\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class TwoFactorController extends Controller
{
    public function enable(Request $request, TotpService $totp, AuditService $audit): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']]); $secret = $totp->generateSecret(); $codes = $totp->recoveryCodes();
        $request->user()->update(['two_factor_secret' => $secret, 'two_factor_recovery_codes' => array_map(fn ($code) => Hash::make($code), $codes), 'two_factor_confirmed_at' => null]);
        $audit->record($request, 'auth.2fa_setup_started', $request->user());
        return response()->json(['secret' => $secret, 'provisioning_uri' => $totp->provisioningUri(config('app.name'), $request->user()->email, $secret), 'recovery_codes' => $codes]);
    }
    public function confirm(Request $request, TotpService $totp, AuditService $audit): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]); $user = $request->user();
        if (!$user->two_factor_secret || !$totp->verify($user->two_factor_secret, $data['code'])) return response()->json(['message' => 'Invalid authentication code.'], 422);
        $user->update(['two_factor_confirmed_at' => now()]); $request->session()->put('two_factor_verified_at', now()->timestamp); $audit->record($request, 'auth.2fa_enabled', $user);
        return response()->json(['message' => 'Two-factor authentication enabled.']);
    }
    public function challenge(Request $request, TotpService $totp, AuditService $audit): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]); $userId = $request->session()->get('two_factor_pending_user_id'); abort_unless($userId, 422, 'No two-factor challenge is pending.');
        $user = User::findOrFail($userId); $valid = $totp->verify($user->two_factor_secret, $data['code']);
        if (!$valid) { $codes = $user->two_factor_recovery_codes ?? []; foreach ($codes as $index => $hash) if (Hash::check(strtoupper($data['code']), $hash)) { unset($codes[$index]); $user->update(['two_factor_recovery_codes' => array_values($codes)]); $valid = true; break; } }
        if (!$valid) return response()->json(['message' => 'Invalid authentication or recovery code.'], 422);
        Auth::login($user, true); $request->session()->regenerate(); $request->session()->forget('two_factor_pending_user_id'); $request->session()->put('two_factor_verified_at', now()->timestamp); $audit->record($request, 'auth.2fa_challenge_passed', $user);
        return response()->json($user);
    }
    public function disable(Request $request, AuditService $audit): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']]); $request->user()->update(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null]); $request->session()->forget('two_factor_verified_at'); $audit->record($request, 'auth.2fa_disabled', $request->user()); return response()->json(['message' => 'Two-factor authentication disabled.']);
    }
}
