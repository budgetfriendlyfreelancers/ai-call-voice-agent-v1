<?php
namespace VoiceShop\Webhook;
final class ProductMatcher
{
    public function sku(array $config, string $name): string
    {
        $key = mb_strtolower(trim($name), 'UTF-8'); $matches = [];
        foreach ($config['products'] as $p) {
            $names = [$p['sku']];
            foreach ($config['languages'] as $block) {
                $localized = $block['products'][$p['sku']] ?? [];
                $names = array_merge($names, [$localized['display_name'] ?? ''], $localized['aliases'] ?? []);
            }
            foreach ($names as $candidate) {
                if ($key !== '' && $key === mb_strtolower(trim($candidate), 'UTF-8')) { $matches[] = $p['sku']; break; }
            }
        }
        // Ambiguous names stay free text rather than silently selecting the wrong product.
        return count($matches) === 1 ? $matches[0] : '';
    }
}
