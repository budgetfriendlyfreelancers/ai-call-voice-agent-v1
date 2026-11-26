<?php
namespace VoiceShop\Service;
use VoiceShop\Language\LanguageResolver;
use VoiceShop\Domain\StatusFlow;
final class OrderPresenter
{
    public function __construct(private LanguageResolver $language) {}
    public function detail(array $o, array $items, array $c): array
    {
        $o['address'] = json_decode($o['address_json'], true);
        $o['missing'] = json_decode($o['missing_fields_json'], true); $o['flags'] = json_decode($o['flags_json'], true);
        $known = array_merge(['customer_name','phone'], array_map(fn($part) => 'address.' . $part, $c['required_fields']['address']['parts']));
        $o['extra_missing'] = array_values(array_diff($o['missing'], $known));
        $products = array_column($c['products'], null, 'sku'); $total = 0; $unpriced = false;
        foreach ($items as &$i) {
            $p = $products[$i['sku']] ?? null;
            $i['display_name'] = $p ? ($this->language->resolve($c, $o['language'], 'products.' . $i['sku'] . '.display_name') ?? $i['sku']) : ($i['raw_text'] ?: $i['sku']);
            $i['allowed_units'] = $p['allowed_units'] ?? [$i['unit']];
            $i['price'] = $p['price'] ?? null;
            $i['line_total'] = $p ? (float)$p['price'] * (float)$i['qty'] : null;
            $i['options'] = json_decode($i['options_json'], true);
            $total += $i['line_total'] ?? 0; $unpriced = $unpriced || $p === null;
        }
        unset($i);
        $previews = [];
        foreach (['order_received','order_confirmed','out_for_delivery'] as $key) { $previews[$key] = $this->language->explain($c, $o['language'], 'dashboard_customer_text.' . $key); }
        return ['order' => $o, 'items' => $items, 'config' => $c, 'total' => $total, 'unpriced' => $unpriced, 'previews' => $previews, 'next' => (new StatusFlow())->next($c, $o['status'])];
    }
    public function sections(array $rows): array
    {
        $sections = ["Today's Orders" => [], 'Needs Details' => [], 'Awaiting Approval' => []];
        foreach ($rows as $o) {
            $o['missing_count'] = count(json_decode($o['missing_fields_json'], true));
            if (substr($o['created_at'], 0, 10) === date('Y-m-d')) { $sections["Today's Orders"][] = $o; }
            if ($o['status'] === 'NEEDS_DETAILS') { $sections['Needs Details'][] = $o; }
            if ($o['status'] === 'AWAITING_MERCHANT_APPROVAL') { $sections['Awaiting Approval'][] = $o; }
        }
        return $sections;
    }
}
