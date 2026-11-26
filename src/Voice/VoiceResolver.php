<?php
namespace VoiceShop\Voice;
final class VoiceResolver
{
    public function resolve(array $c, string $language): array
    {
        $code = in_array($language, $c['enabled_languages'], true) ? $language : $c['default_language'];
        $v = $c['languages'][$code]['voice'] ?? null;
        $fallback = $code !== $language || $v === null;
        if ($v === null || str_starts_with($v['options'][$v['selected']]['voice_id'], 'REPLACE_')) {
            $code = $c['default_language']; $v = $c['languages'][$code]['voice']; $fallback = true;
        }
        $id = $v['options'][$v['selected']]['voice_id'];
        return ['provider' => $c['voice_provider'], 'gender' => $v['selected'], 'voice_id' => $id, 'language' => $code, 'fallback' => $fallback, 'placeholder' => str_starts_with($id, 'REPLACE_')];
    }
}
