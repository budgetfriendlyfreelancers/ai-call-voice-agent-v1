<?php
namespace VoiceShop\Service;
final class IntakeResult
{
    public function __construct(public int $code, public string $message, public ?array $order = null) {}
}
