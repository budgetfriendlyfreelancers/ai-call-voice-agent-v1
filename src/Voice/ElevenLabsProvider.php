<?php
namespace VoiceShop\Voice;
final class ElevenLabsProvider implements VoiceProviderInterface
{
    public function __construct(private string $apiKey) {}
    public function name(): string { return 'elevenlabs'; }
    /** Voice runs in the hosted ElevenLabs agent; standalone server TTS is not used by webhooks. */
    public function synthesize(string $text, string $voiceId, string $languageCode): string { throw new \LogicException('Standalone server TTS is not implemented; configure the hosted ElevenLabs voice agent.'); }
}
