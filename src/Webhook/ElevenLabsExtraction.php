<?php
namespace VoiceShop\Webhook;
final class ElevenLabsExtraction
{
    public function collection(array $data): array
    {
        $analysis = $data['analysis'] ?? [];
        if (!is_array($analysis)) { throw new \InvalidArgumentException('Invalid analysis.'); }
        $collection = $analysis['data_collection_results'] ?? [];
        if (!is_array($collection) || ($collection !== [] && array_is_list($collection))) { throw new \InvalidArgumentException('Invalid collection.'); }
        return $collection;
    }
    public function order(array $config, string $callId, array $collection): array
    {
        $fields = [];
        foreach ($collection as $key => $entry) {
            if (!is_array($entry) || !array_key_exists('value', $entry)) { throw new \InvalidArgumentException('Collection entries need value.'); }
            $fields[$key] = $entry['value'];
        }
        // ElevenLabs string data-collection fields can hold JSON for structured values.
        $address = $this->structure($fields['address'] ?? null, false);
        $items = $this->structure($fields['items'] ?? null, true);
        $mapped = []; $matcher = new ProductMatcher();
        foreach ($items as $item) {
            if (!is_array($item)) { throw new \InvalidArgumentException('Invalid item.'); }
            $name = $this->text($item['name'] ?? $item['raw_text'] ?? '');
            $sku = $this->text($item['sku'] ?? '');
            $mapped[] = ['sku' => $sku !== '' ? $sku : $matcher->sku($config, $name), 'qty' => $item['quantity'] ?? $item['qty'] ?? null,
                'unit' => $this->text($item['unit'] ?? ''), 'options' => $item['options'] ?? [], 'raw_text' => $name];
        }
        return ['shop_id' => $config['shop_id'], 'call_id' => $callId, 'language' => $this->text($fields['language'] ?? $config['default_language']),
            'customer' => ['name' => $this->text($fields['customer_name'] ?? ''), 'phone' => $this->text($fields['phone'] ?? ''), 'address' => $address],
            'items' => $mapped, 'requested_time' => $this->text($fields['requested_time'] ?? ''),
            'delivery_choice' => $this->text($fields['delivery_choice'] ?? $config['delivery']['default']),
            'payment_choice' => $this->text($fields['payment_choice'] ?? ''), 'missing_fields' => [], 'flags' => []];
    }
    public function transcript(array $data): ?string
    {
        if (!isset($data['transcript'])) { return null; }
        if (!is_array($data['transcript']) || !array_is_list($data['transcript'])) { throw new \InvalidArgumentException('Invalid transcript.'); }
        $lines = [];
        foreach ($data['transcript'] as $turn) {
            if (!is_array($turn)) { throw new \InvalidArgumentException('Invalid turn.'); }
            $message = $this->text($turn['message'] ?? '');
            if ($message !== '') { $lines[] = $this->text($turn['role'] ?? 'unknown') . ': ' . $message; }
        }
        $text = implode("\n", $lines);
        if (strlen($text) > 65535) { throw new \InvalidArgumentException('Transcript exceeds TEXT capacity.'); }
        return $text;
    }
    private function text(mixed $value): string
    {
        if ($value === null) { return ''; }
        if (!is_string($value)) { throw new \InvalidArgumentException('Expected text.'); }
        return $value;
    }
    private function structure(mixed $value, bool $list): array
    {
        if ($value === null || $value === '') { return []; }
        if (is_string($value)) { $value = json_decode($value, true, 64, JSON_THROW_ON_ERROR); }
        if (!is_array($value) || ($value !== [] && array_is_list($value) !== $list)) { throw new \InvalidArgumentException('Invalid structured field.'); }
        return $value;
    }
}
