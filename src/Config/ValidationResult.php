<?php
namespace VoiceShop\Config;
final class ValidationResult
{
    public function __construct(public array $errors = [], public array $warnings = []) {}
    public function valid(): bool { return $this->errors === []; }
}
