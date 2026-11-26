<?php
namespace VoiceShop\Http;
final class View
{
    public static function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
    public static function render(string $template, array $data): void
    {
        extract($data, EXTR_SKIP); $e = [self::class, 'e'];
        ob_start(); require dirname(__DIR__, 2) . '/views/' . $template . '.php'; $content = ob_get_clean();
        require dirname(__DIR__, 2) . '/views/layout.php';
    }
}
