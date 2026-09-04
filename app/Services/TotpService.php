<?php
namespace App\Services;
use Illuminate\Support\Str;

class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    public function generateSecret(int $length = 32): string { $secret = ''; for ($i = 0; $i < $length; $i++) $secret .= self::ALPHABET[random_int(0, 31)]; return $secret; }
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        if (!preg_match('/^\d{6}$/', $code)) return false;
        $counter = intdiv(time(), 30);
        for ($offset = -$window; $offset <= $window; $offset++) if (hash_equals($this->code($secret, $counter + $offset), $code)) return true;
        return false;
    }
    public function provisioningUri(string $issuer, string $email, string $secret): string
    {
        $label = rawurlencode($issuer.':'.$email);
        return 'otpauth://totp/'.$label.'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
    }
    public function recoveryCodes(int $count = 8): array { return collect(range(1, $count))->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))->all(); }
    private function code(string $secret, int $counter): string
    {
        $key = $this->decodeBase32($secret); $binary = pack('N2', intdiv($counter, 4294967296), $counter % 4294967296); $hash = hash_hmac('sha1', $binary, $key, true); $offset = ord($hash[19]) & 0x0f; $value = ((ord($hash[$offset]) & 0x7f) << 24) | ((ord($hash[$offset + 1]) & 0xff) << 16) | ((ord($hash[$offset + 2]) & 0xff) << 8) | (ord($hash[$offset + 3]) & 0xff); return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }
    private function decodeBase32(string $value): string
    {
        $bits = ''; foreach (str_split(strtoupper(rtrim($value, '='))) as $character) { $position = strpos(self::ALPHABET, $character); if ($position === false) return ''; $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT); }
        $decoded = ''; foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $decoded .= chr(bindec($byte)); return $decoded;
    }
}
