<?php
namespace App\Services;
use Illuminate\Validation\ValidationException;
class WebhookUrlValidator
{
    public function assertSafe(string $url): void
    {
        $parts = parse_url($url); $host = $parts['host'] ?? null; $port = $parts['port'] ?? 443;
        if (($parts['scheme'] ?? null) !== 'https' || !$host || $port !== 443 || isset($parts['user']) || isset($parts['pass'])) throw ValidationException::withMessages(['url' => 'Webhook URLs must use HTTPS on port 443 without embedded credentials.']);
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (!$addresses) throw ValidationException::withMessages(['url' => 'Webhook hostname could not be resolved.']);
        foreach ($addresses as $address) if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) throw ValidationException::withMessages(['url' => 'Webhook URLs cannot resolve to private or reserved networks.']);
    }
}
