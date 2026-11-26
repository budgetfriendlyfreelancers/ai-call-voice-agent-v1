<?php
namespace VoiceShop\Webhook;
final class ElevenLabsSignature
{
    // ElevenLabs SDK scheme: t=<Unix seconds>,v0=<HMAC-SHA256 hex>. No unsigned fallback.
    public function verify(string $raw, string $header, string $secret, ?int $now = null): bool
    {
        if (trim($secret) === '' || $header === '') { return false; }
        $timestamp = null; $signatures = [];
        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) { return false; }
            [$key, $value] = array_map('trim', $pair);
            if ($key === 't') {
                if ($timestamp !== null || !preg_match('/^[0-9]{1,12}$/D', $value)) { return false; }
                $timestamp = $value;
            } elseif ($key === 'v0' && preg_match('/^[a-fA-F0-9]{64}$/D', $value)) { $signatures[] = strtolower($value); }
        }
        // Same 30-minute tolerance as the official SDK; also reject far-future timestamps.
        if ($timestamp === null || abs(($now ?? time()) - (int)$timestamp) > 1800) { return false; }
        $expected = hash_hmac('sha256', $timestamp . '.' . $raw, $secret);
        foreach ($signatures as $signature) { if (hash_equals($expected, $signature)) { return true; } }
        return false;
    }
}
