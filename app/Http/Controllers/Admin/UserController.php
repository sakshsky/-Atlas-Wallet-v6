<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', 'string', 'min:8'], 'role' => ['required', Rule::in(['admin', 'user'])]]);
        $user = User::create($data + ['status' => 'active']); $user->sendEmailVerificationNotification(); Cache::forget('admin.stats'); return response()->json($user, 201);
    }
    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:120'], 'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user)], 'role' => ['sometimes', Rule::in(['admin', 'user'])], 'status' => ['sometimes', Rule::in(['active', 'suspended'])], 'risk_level' => ['sometimes', Rule::in(['low', 'medium', 'high'])], 'daily_withdrawal_limit' => ['sometimes', 'numeric', 'gte:0']]);
        $removesAdminAccess = ($data['role'] ?? $user->role) !== 'admin' || ($data['status'] ?? $user->status) !== 'active';
        if ($user->is($request->user()) && $removesAdminAccess) abort(422, 'You cannot remove your own administrator access.');
        if ($user->role === 'admin' && $user->status === 'active' && $removesAdminAccess && User::where('role', 'admin')->where('status', 'active')->count() <= 1) abort(422, 'At least one active administrator is required.');
        $user->update($data); Cache::forget('admin.stats'); return response()->json($user);
    }
}
