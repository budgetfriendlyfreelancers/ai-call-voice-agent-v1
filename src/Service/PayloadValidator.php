<?php
namespace VoiceShop\Service;
final class PayloadValidator
{
    public function normalize(array $c, array $p): array
    {
        $p['call_id'] = $this->text($p['call_id'] ?? '', 'call_id', 255);
        if ($p['call_id'] === '') { throw new \InvalidArgumentException('call_id is required.'); }
        if (!is_array($p['customer'] ?? [])) { throw new \InvalidArgumentException('customer must be an object.'); }
        $customer = $p['customer'] ?? [];
        $p['customer'] = ['name' => $this->text($customer['name'] ?? '', 'name', 255), 'phone' => $this->text($customer['phone'] ?? '', 'phone', 50), 'address' => []];
        if (!is_array($customer['address'] ?? [])) { throw new \InvalidArgumentException('address must be an object.'); }
        foreach ($c['required_fields']['address']['parts'] as $part) { $p['customer']['address'][$part] = $this->text($customer['address'][$part] ?? '', 'address.' . $part, 255); }
        $p['language'] = $this->text($p['language'] ?? $c['default_language'], 'language', 30);
        foreach (['delivery' => 'delivery_choice','payment' => 'payment_choice'] as $configKey => $key) {
            $p[$key] = $this->text($p[$key] ?? ($c[$configKey]['default'] ?? ''), $key, 100);
            if ($p[$key] !== '' && !in_array($p[$key], $c[$configKey]['options'], true)) { throw new \InvalidArgumentException("Invalid $key."); }
        }
        $p['requested_time'] = $this->text($p['requested_time'] ?? '', 'requested_time', 255);
        foreach (['missing_fields','flags'] as $key) {
            if (!is_array($p[$key] ?? [])) { throw new \InvalidArgumentException("$key must be a list."); }
            $p[$key] = array_map(fn($v) => $this->text($v, $key, 255), $p[$key] ?? []);
        }
        if (!is_array($p['items'] ?? null) || !array_is_list($p['items']) || !$p['items'] || count($p['items']) > $c['rules']['max_items_per_order']) { throw new \InvalidArgumentException('Items must be a nonempty list within max_items_per_order.'); }
        $products = array_column($c['products'], null, 'sku');
        foreach ($p['items'] as &$i) {
            if (!is_array($i)) { throw new \InvalidArgumentException('Each item must be an object.'); }
            $i['sku'] = $this->text($i['sku'] ?? '', 'sku', 100);
            $i['raw_text'] = $this->text($i['raw_text'] ?? '', 'raw_text', 2000);
            $i['unit'] = $this->text($i['unit'] ?? ($products[$i['sku']]['unit'] ?? 'item'), 'unit', 50);
            $i['qty'] = self::quantity($i['qty'] ?? null);
            $product = $products[$i['sku']] ?? null;
            if ($product && !in_array($i['unit'], $product['allowed_units'], true)) { throw new \InvalidArgumentException('Invalid unit for ' . $i['sku']); }
            if (!$product && $i['raw_text'] === '' && $i['sku'] === '') { throw new \InvalidArgumentException('Unknown item needs raw_text or sku.'); }
            if (!$product && $c['rules']['unknown_item_action'] !== 'note_as_free_text_and_flag') { throw new \InvalidArgumentException('Unsupported unknown_item_action.'); }
            $i['is_unknown_item'] = $product ? 0 : 1;
            if (!is_array($i['options'] ?? [])) { throw new \InvalidArgumentException('Item options must be an object.'); }
            $i['options'] = $i['options'] ?? [];
            foreach ($i['options'] as $key => $value) { $i['options'][$key] = $this->text($value, 'option', 255); }
        }
        unset($i);
        return $p;
    }
    public static function quantity(mixed $v): float
    {
        if (!is_numeric($v) || !is_finite((float)$v) || $v <= 0 || $v > 999999999 || abs((float)$v - round((float)$v, 3)) > 0.0000001) { throw new \InvalidArgumentException('Quantity must be positive, at most 999999999, with up to 3 decimals.'); }
        return (float)$v;
    }
    private function text(mixed $v, string $field, int $limit): string
    {
        if (!is_string($v) || mb_strlen($v, 'UTF-8') > $limit) { throw new \InvalidArgumentException("$field must be text, at most $limit characters."); }
        return trim($v);
    }
}
