<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class EnsureTwoFactorSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->hasTwoFactorEnabled() && !$request->session()->has('two_factor_verified_at')) return response()->json(['message' => 'Two-factor authentication is required.', 'two_factor_required' => true], 423);
        return $next($request);
    }
}
