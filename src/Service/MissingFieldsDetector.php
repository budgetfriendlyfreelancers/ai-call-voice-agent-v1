<?php
namespace VoiceShop\Service;
final class MissingFieldsDetector
{
    public function detect(array $c, array $p, bool $merge = true): array
    {
        $missing = $merge ? ($p['missing_fields'] ?? []) : [];
        foreach (['customer_name' => 'name', 'phone' => 'phone'] as $key => $field) {
            if (($c['required_fields'][$key]['required'] ?? false) && trim((string)($p['customer'][$field] ?? '')) === '') { $missing[] = $key; }
        }
        if ($c['required_fields']['address']['required'] ?? false) {
            foreach ($c['required_fields']['address']['must_have'] as $part) {
                if (trim((string)($p['customer']['address'][$part] ?? '')) === '') { $missing[] = 'address.' . $part; }
            }
        }
        return array_values(array_unique($missing));
    }
}
