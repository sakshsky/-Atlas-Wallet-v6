<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Services\AuditService;
use App\Services\WebhookUrlValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class WebhookController extends Controller
{
    private const EVENTS = ['trade.completed', 'transfer.completed', 'deposit.completed', 'withdrawal.completed', 'kyc.approved', 'kyc.rejected', 'reversal.completed'];
    public function index(Request $request): JsonResponse { return response()->json(Webhook::where('user_id', $request->user()->id)->latest()->get()); }
    public function store(Request $request, WebhookUrlValidator $validator, AuditService $audit): JsonResponse
    {
        $data = $request->validate(['url' => ['required', 'url:https', 'max:2048'], 'events' => ['required', 'array', 'min:1'], 'events.*' => ['required', Rule::in(self::EVENTS)]]); $validator->assertSafe($data['url']); $secret = Str::random(64); $webhook = Webhook::create($data + ['user_id' => $request->user()->id, 'secret' => $secret, 'is_active' => true]); $audit->record($request, 'webhook.created', $webhook); return response()->json(['webhook' => $webhook, 'signing_secret' => $secret], 201);
    }
    public function destroy(Request $request, Webhook $webhook, AuditService $audit): JsonResponse { abort_unless($webhook->user_id === $request->user()->id, 404); $webhook->update(['is_active' => false]); $audit->record($request, 'webhook.disabled', $webhook); return response()->json(['message' => 'Webhook disabled.']); }
}
