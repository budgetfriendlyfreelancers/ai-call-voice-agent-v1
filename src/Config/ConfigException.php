<?php
namespace VoiceShop\Config;
final class ConfigException extends \RuntimeException
{
    public function __construct(public array $errors) { parent::__construct(implode('; ', $errors)); }
}
