<?php
namespace App\Http\Middleware;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class IdempotentMoneyRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('Idempotency-Key');
        if (!preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $key)) return response()->json(['message' => 'A valid Idempotency-Key header is required.'], 422);
        $operation = $request->method().' '.$request->path();
        $hash = hash('sha256', json_encode($request->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $owner = (string) Str::uuid();

        return DB::transaction(function () use ($request, $next, $key, $operation, $hash, $owner) {
            DB::table('idempotency_keys')->insertOrIgnore(['user_id' => $request->user()->id, 'operation' => $operation, 'key' => $key, 'request_hash' => $hash, 'owner_token' => $owner, 'status' => 'processing', 'created_at' => now(), 'updated_at' => now()]);
            $record = IdempotencyKey::where('user_id', $request->user()->id)->where('operation', $operation)->where('key', $key)->lockForUpdate()->firstOrFail();
            if (!hash_equals($record->request_hash, $hash)) return response()->json(['message' => 'This idempotency key was already used with different request data.'], 409);
            if ($record->status === 'completed') return new JsonResponse($record->response_payload, $record->response_code);
            if ($record->owner_token !== $owner) return response()->json(['message' => 'This request is already being processed.'], 409);

            $response = $next($request);
            if ($response->getStatusCode() < 500) $record->update(['status' => 'completed', 'response_code' => $response->getStatusCode(), 'response_payload' => json_decode($response->getContent(), true) ?? ['message' => $response->getContent()]]);
            else $record->delete();
            return $response;
        }, 3);
    }
}
