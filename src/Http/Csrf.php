<?php
namespace VoiceShop\Http;
final class Csrf
{
    public static function token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
    public static function verify(mixed $token): void
    {
        if (!is_string($token) || !hash_equals(self::token(), $token)) { throw new \InvalidArgumentException('Session token expired or invalid. Reload the page.'); }
    }
}
