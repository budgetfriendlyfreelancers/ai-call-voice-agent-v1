<?php
namespace VoiceShopTests;
final class WebhookClient
{
    public function __construct(private string $base, private string $secret)
    {
        if (!preg_match('~^http://(?:127\.0\.0\.1|localhost)(?::\d+)?(?:/[a-zA-Z0-9_/-]*)?$~D', $base)) { throw new \InvalidArgumentException('Simulation sends only to localhost.'); }
    }
    public function raw(string $raw, ?string $header = null): array
    {
        // Deliberately independent signing implementation: an incorrect verifier cannot self-validate.
        if ($header === null) { $time = time(); $header = 't=' . $time . ',v0=' . hash_hmac('sha256', $time . '.' . $raw, $this->secret); }
        $headers = ['Content-Type: application/json; charset=utf-8'];
        if ($header !== '') { $headers[] = 'ElevenLabs-Signature: ' . $header; }
        $ch = curl_init(rtrim($this->base, '/') . '/webhook/elevenlabs.php');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $raw, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
        $body = curl_exec($ch);
        if ($body === false) { $error = curl_error($ch); curl_close($ch); throw new \RuntimeException($error); }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        return [$status, $body];
    }
}
