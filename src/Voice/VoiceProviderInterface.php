<?php
namespace VoiceShop\Voice;
interface VoiceProviderInterface
{
    public function synthesize(string $text, string $voiceId, string $languageCode): string;
    public function name(): string;
}
