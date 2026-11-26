<?php
namespace VoiceShop\Config;
final class WebhookSettings
{
    public function __construct(private array $env) {}
    public function secret(): string
    {
        $value = $this->env['ELEVENLABS_WEBHOOK_SECRET'] ?? '';
        return is_string($value) ? $value : '';
    }
    public function map(): array { return is_array($this->env['SHOP_AGENT_MAP'] ?? null) ? $this->env['SHOP_AGENT_MAP'] : []; }
    public function shopFor(string $agent): ?string
    {
        // Placeholder entries document setup, but must never route an incoming call.
        if (str_starts_with($agent, 'REPLACE_')) { return null; }
        $shop = $this->map()[$agent] ?? null;
        return is_string($shop) ? $shop : null;
    }
}
