<?php
namespace VoiceShop\Webhook;
final class WebhookLog
{
    public function __construct(private string $directory) {}
    public function write(string $event, ?string $callId = null): void
    {
        // Never log bodies, signatures, secrets, customer details or exception traces.
        if (!is_dir($this->directory)) { @mkdir($this->directory, 0775, true); }
        $line = json_encode(['time' => date(DATE_ATOM), 'event' => $event, 'call_hash' => $callId === null ? null : hash('sha256', $callId)], JSON_UNESCAPED_UNICODE);
        @file_put_contents($this->directory . '/webhook.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
