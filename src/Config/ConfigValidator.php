<?php
namespace VoiceShop\Config;
use VoiceShop\Domain\StatusFlow;
final class ConfigValidator
{
    public function validateWebhookSettings(array $env, array $shopIds): ValidationResult
    {
        $r = new ValidationResult();
        $secret = $env['ELEVENLABS_WEBHOOK_SECRET'] ?? '';
        if (!is_string($secret)) { $r->errors[] = 'ELEVENLABS_WEBHOOK_SECRET must be a string.'; }
        elseif (trim($secret) === '') { $r->warnings[] = 'Webhook signing secret is not configured; the endpoint returns 401.'; }
        $map = $env['SHOP_AGENT_MAP'] ?? [];
        if (!is_array($map)) { $r->errors[] = 'SHOP_AGENT_MAP must map agent IDs to shop IDs.'; return $r; }
        if (!$map) { $r->warnings[] = 'No ElevenLabs agents are mapped to shops.'; }
        foreach ($map as $agent => $shop) {
            if (!is_string($agent) || !preg_match('/^[a-zA-Z0-9_-]{1,255}$/D', $agent)) { $r->errors[] = 'Agent IDs must be nonempty identifiers of at most 255 characters.'; }
            elseif (str_starts_with($agent, 'REPLACE_')) { $r->warnings[] = 'Placeholder agent ID: ' . $agent . '. TODO(user): paste the real agent ID.'; }
            if (!is_string($shop) || !in_array($shop, $shopIds, true)) { $r->errors[] = 'An agent mapping references an unknown shop.'; }
        }
        return $r;
    }
    public function validate(array $c): ValidationResult
    {
        $r = new ValidationResult();
        $required = ['shop_id','shop_name','shop_type','inbound_number','default_language','enabled_languages','voice_provider','languages','products','required_fields','delivery','payment','whatsapp_enquiry_number','statuses','status_flow','rules'];
        foreach ($required as $key) { if (!array_key_exists($key, $c)) { $r->errors[] = "Missing top-level key: $key"; } }
        foreach (['shop_id','shop_name','shop_type','inbound_number','default_language','voice_provider','whatsapp_enquiry_number'] as $key) {
            if (!is_string($c[$key] ?? null) || trim($c[$key]) === '') { $r->errors[] = "$key must be a nonempty string."; }
        }
        foreach (['enabled_languages','languages','products','required_fields','delivery','payment','statuses','status_flow','rules'] as $key) {
            if (!is_array($c[$key] ?? null)) { $r->errors[] = "$key must be an array/object."; }
        }
        if ($r->errors) { return $r; }
        // Reject malformed nested types before traversing them, so health never emits PHP warnings.
        foreach ($c['languages'] as $code => $block) {
            if (!is_array($block)) { $r->errors[] = "$code must be an object."; continue; }
            foreach (['persona','greeting','closing','delivery_notice','agent_may_say_after_order'] as $key) {
                if (isset($block[$key]) && !is_string($block[$key])) { $r->errors[] = "$code.$key must be text."; }
            }
            foreach (['voice','products','dashboard_customer_text'] as $key) {
                if (isset($block[$key]) && !is_array($block[$key])) { $r->errors[] = "$code.$key must be an object."; }
            }
            if (is_array($block['voice'] ?? null)) {
                $voice = $block['voice'];
                if (!is_string($voice['selected'] ?? null) || !is_array($voice['options'] ?? null)) { $r->errors[] = "$code: invalid voice structure."; }
                foreach (is_array($voice['options'] ?? null) ? $voice['options'] : [] as $option) {
                    if (!is_array($option)) { $r->errors[] = "$code: voice option must be an object."; }
                }
            }
            foreach (is_array($block['products'] ?? null) ? $block['products'] : [] as $product) {
                if (!is_array($product)) { $r->errors[] = "$code: language product must be an object."; }
            }
        }
        foreach (['customer_name','phone','address'] as $key) {
            if (!is_array($c['required_fields'][$key] ?? null) || !is_bool($c['required_fields'][$key]['required'] ?? null)) { $r->errors[] = "required_fields.$key needs a boolean required value."; }
        }
        foreach (['statuses','enabled_languages'] as $key) {
            if (!array_is_list($c[$key]) || array_filter($c[$key], fn($v) => !is_string($v))) { $r->errors[] = "$key must be a list of strings."; }
        }
        foreach ($c['status_flow'] as $targets) {
            if (!is_array($targets) || array_filter($targets, fn($v) => !is_string($v))) { $r->errors[] = 'status_flow targets must be strings.'; }
        }
        $address = $c['required_fields']['address'] ?? [];
        foreach (['parts','must_have'] as $key) {
            if (!is_array($address[$key] ?? null) || array_filter($address[$key], fn($v) => !is_string($v))) { $r->errors[] = "address.$key must be a string list."; }
        }
        if ($r->errors) { return $r; }
        if (!preg_match('/^[a-zA-Z0-9_-]+$/D', $c['shop_id'])) { $r->errors[] = 'Invalid shop_id.'; }
        if (!in_array($c['default_language'], $c['enabled_languages'], true)) { $r->errors[] = 'default_language must be enabled.'; }
        foreach ($c['enabled_languages'] as $code) {
            if (!is_string($code) || !is_array($c['languages'][$code] ?? null)) { $r->errors[] = 'Each enabled language needs a block.'; }
        }
        $skus = [];
        foreach ($c['products'] as $p) {
            if (!is_array($p) || !is_string($p['sku'] ?? null)) { $r->errors[] = 'Product needs sku.'; continue; }
            $sku = $p['sku'];
            if (isset($skus[$sku])) { $r->errors[] = "Duplicate sku: $sku"; }
            $skus[$sku] = true;
            if (!in_array($p['price_visibility'] ?? '', ['owner_only','customer_ok','never_show'], true)) { $r->errors[] = "$sku: invalid price_visibility."; }
            if (!is_array($p['allowed_units'] ?? null) || !in_array($p['unit'] ?? null, $p['allowed_units'], true)) { $r->errors[] = "$sku: invalid units."; }
            if (!is_numeric($p['price'] ?? null) || $p['price'] < 0) { $r->errors[] = "$sku: invalid price."; }
        }
        $default = $c['languages'][$c['default_language']] ?? [];
        foreach (['voice','persona','greeting','closing','delivery_notice','agent_may_say_after_order','products','dashboard_customer_text'] as $key) {
            if (!isset($default[$key]) || $default[$key] === '') { $r->errors[] = "Default language missing $key."; }
        }
        foreach (['order_received','order_confirmed','out_for_delivery'] as $key) {
            if (!is_string($default['dashboard_customer_text'][$key] ?? null) || trim($default['dashboard_customer_text'][$key]) === '') { $r->errors[] = "Default dashboard_customer_text missing $key."; }
        }
        foreach ($skus as $sku => $_) {
            if (!is_string($default['products'][$sku]['display_name'] ?? null) || trim($default['products'][$sku]['display_name']) === '') { $r->errors[] = "Default product $sku needs display_name."; }
        }
        foreach ($c['languages'] as $code => $block) {
            if (!is_array($block)) { $r->errors[] = "$code must be an object."; continue; }
            if (isset($block['products']) && !is_array($block['products'])) { $r->errors[] = "$code products must be an object."; }
            foreach (is_array($block['products'] ?? null) ? $block['products'] : [] as $sku => $p) {
                if (!isset($skus[$sku])) { $r->errors[] = "$code: unknown product key $sku."; }
            }
            if (isset($block['voice'])) {
                $v = $block['voice'];
                if (!is_array($v) || !in_array($v['selected'] ?? '', ['male','female'], true) || !isset($v['options'][$v['selected'] ?? ''])) { $r->errors[] = "$code: invalid voice.selected/options."; }
                foreach (is_array($v['options'] ?? null) ? $v['options'] : [] as $gender => $option) {
                    $id = $option['voice_id'] ?? null;
                    if (!is_string($id) || $id === '') { $r->errors[] = "$code/$gender: missing voice_id."; }
                    elseif (str_starts_with($id, 'REPLACE_')) { $r->warnings[] = "$code/$gender: unfilled voice_id ($id)."; }
                }
            }
        }
        $statuses = $c['statuses']; $expected = StatusFlow::CODES;
        sort($statuses); sort($expected);
        if ($statuses !== $expected) { $r->errors[] = 'statuses must equal the six agreed codes.'; }
        foreach ($c['status_flow'] as $from => $targets) {
            if (!in_array($from, StatusFlow::CODES, true) || !is_array($targets) || array_diff($targets, StatusFlow::CODES)) { $r->errors[] = 'status_flow contains invalid statuses.'; }
        }
        $a = $c['required_fields']['address'] ?? [];
        if (!is_array($a['parts'] ?? null) || !is_array($a['must_have'] ?? null) || array_diff($a['must_have'], $a['parts'])) { $r->errors[] = 'address.must_have must be a subset of address.parts.'; }
        foreach (['delivery','payment'] as $key) {
            if (!is_array($c[$key]['options'] ?? null) || !$c[$key]['options']) { $r->errors[] = "$key.options must be nonempty."; }
        }
        if (!in_array($c['delivery']['default'] ?? null, $c['delivery']['options'] ?? [], true)) { $r->errors[] = 'Invalid delivery.default.'; }
        if (!is_int($c['rules']['max_items_per_order'] ?? null) || $c['rules']['max_items_per_order'] < 1) { $r->errors[] = 'Invalid max_items_per_order.'; }
        return $r;
    }
}
