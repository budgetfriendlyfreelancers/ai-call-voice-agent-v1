<?php
namespace VoiceShopTests;
final class ElevenLabsFixture
{
    public static function make(string $agent, string $callId, bool $missingAddress = false): array
    {
        $items = [['name' => 'rice', 'quantity' => 5, 'unit' => 'kg'], ['name' => 'dal', 'quantity' => 1, 'unit' => 'kg'], ['name' => 'oil', 'quantity' => 2, 'unit' => 'litre']];
        $address = ['house_no' => 'DEMO 12', 'street' => 'மாதிரி தெரு', 'area' => 'மாதிரி நகர்', 'landmark' => 'கற்பனை கடை அருகில்', 'pincode' => '000000'];
        $fields = ['customer_name' => 'சோதனை குமார் (fictional)', 'phone' => '0000000000', 'language' => 'ta-IN',
            'items' => json_encode($items, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'address' => $missingAddress ? null : json_encode($address, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'requested_time' => 'இன்று மாலை 6 மணி (demo)', 'delivery_choice' => 'home_delivery', 'payment_choice' => 'upi'];
        $collection = [];
        foreach ($fields as $key => $value) { $collection[$key] = ['data_collection_id' => $key, 'value' => $value, 'json_schema' => ['type' => 'string'], 'rationale' => 'Fictional local simulation.']; }
        return ['type' => 'post_call_transcription', 'event_timestamp' => time(), 'data' => ['agent_id' => $agent, 'conversation_id' => $callId, 'status' => 'done',
            'transcript' => [['role' => 'agent', 'message' => 'வணக்கம்! என்ன பொருள் வேண்டும்?', 'time_in_call_secs' => 0],
                ['role' => 'user', 'message' => 'அரிசி 5 கிலோ, பருப்பு 1 கிலோ, எண்ணெய் 2 லிட்டர் வேண்டும்.', 'time_in_call_secs' => 3]],
            'metadata' => ['start_time_unix_secs' => time() - 45, 'call_duration_secs' => 45],
            'analysis' => ['call_successful' => 'success', 'transcript_summary' => 'Fictional Tamil grocery order.', 'data_collection_results' => $collection]]];
    }
    public static function groceryAgent(array $env): string
    {
        foreach (($env['SHOP_AGENT_MAP'] ?? []) as $agent => $shop) {
            if ($shop === 'grocery_store_02' && !str_starts_with((string)$agent, 'REPLACE_')) { return $agent; }
        }
        throw new \RuntimeException('Configure a non-placeholder grocery agent mapping in config/env.php. For local simulation use tests/configure_webhook_demo.php.');
    }
}
