<?php
namespace App\Jobs;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\WebhookUrlValidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;
class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 5; public array $backoff = [30, 120, 300, 900];
    public function __construct(public int $webhookId, public int $deliveryId, public string $event, public array $payload) { $this->afterCommit(); }
    public function handle(WebhookUrlValidator $validator): void
    {
        $webhook = Webhook::findOrFail($this->webhookId); if (!$webhook->is_active) return; $validator->assertSafe($webhook->url);
        $delivery = WebhookDelivery::findOrFail($this->deliveryId); $body = ['id' => $delivery->delivery_id, 'event' => $this->event, 'created_at' => $delivery->created_at->toIso8601String(), 'data' => $this->payload]; $json = json_encode($body, JSON_UNESCAPED_SLASHES);
        $response = Http::timeout(10)->withHeaders(['X-Atlas-Delivery' => $delivery->delivery_id, 'X-Atlas-Signature' => 'sha256='.hash_hmac('sha256', $json, $webhook->secret)])->withBody($json, 'application/json')->post($webhook->url);
        $delivery->update(['attempts' => $this->attempts(), 'response_status' => $response->status(), 'status' => $response->successful() ? 'delivered' : 'pending', 'delivered_at' => $response->successful() ? now() : null, 'error' => $response->successful() ? null : 'HTTP '.$response->status()]);
        if (!$response->successful()) throw new RuntimeException('Webhook delivery returned HTTP '.$response->status());
        $webhook->update(['last_triggered_at' => now()]);
    }
    public function failed(\Throwable $error): void { WebhookDelivery::whereKey($this->deliveryId)->update(['status' => 'failed', 'error' => mb_substr($error->getMessage(), 0, 1000)]); }
}
