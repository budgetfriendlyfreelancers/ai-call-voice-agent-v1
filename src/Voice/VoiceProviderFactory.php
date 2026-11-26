<?php
namespace VoiceShop\Voice;
final class VoiceProviderFactory
{
    private const MAP = ['elevenlabs' => ElevenLabsProvider::class];
    public static function make(string $key, array $env = []): VoiceProviderInterface
    {
        $class = self::MAP[$key] ?? throw new \InvalidArgumentException('Unknown voice provider: ' . $key);
        return new $class((string)($env[$key . '_api_key'] ?? ''));
    }
}
