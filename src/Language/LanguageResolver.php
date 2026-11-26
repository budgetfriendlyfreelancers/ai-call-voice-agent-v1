<?php
namespace VoiceShop\Language;
final class LanguageResolver
{
    public function __construct(private string $logDirectory) {}
    public function language(array $c, string $code): string { return in_array($code, $c['enabled_languages'], true) ? $code : $c['default_language']; }
    public function resolve(array $c, string $code, string $key): mixed { return $this->explain($c, $code, $key)['value']; }
    public function explain(array $c, string $code, string $key): array
    {
        $active = $this->language($c, $code);
        foreach (array_unique([$active, $c['default_language']]) as $source) {
            $value = $c['languages'][$source];
            foreach (explode('.', $key) as $part) { $value = is_array($value) ? ($value[$part] ?? null) : null; }
            if ($value !== null) { return ['value' => $value, 'language' => $source, 'fallback' => $source !== $code]; }
        }
        if (!is_dir($this->logDirectory)) { mkdir($this->logDirectory, 0775, true); }
        file_put_contents($this->logDirectory . '/language.log', json_encode(['shop' => $c['shop_id'], 'language' => $code, 'missing' => $key], JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
        return ['value' => null, 'language' => null, 'fallback' => false];
    }
}
