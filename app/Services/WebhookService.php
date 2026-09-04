<?php
namespace App\Services;
use App\Jobs\DeliverWebhook;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Str;
class WebhookService
{
    public function dispatch(string $event, array $payload): void
    {
        Webhook::where('is_active', true)->whereJsonContains('events', $event)->each(function (Webhook $webhook) use ($event, $payload) { $delivery = WebhookDelivery::create(['webhook_id' => $webhook->id, 'delivery_id' => (string) Str::uuid(), 'event' => $event, 'status' => 'pending']); DeliverWebhook::dispatch($webhook->id, $delivery->id, $event, $payload); });
    }
}
