<?php
namespace VoiceShop\Http;
use VoiceShop\Config\{ConfigValidator,FileShopConfigRepository,WebhookSettings};
use VoiceShop\Db\Database;
use VoiceShop\Webhook\{ElevenLabsSignature,ElevenLabsWebhookService,WebhookLog};
final class ElevenLabsWebhookController
{
    public function __construct(private string $root) {}
    public function handle(string $raw, string $signature, string $method): array
    {
        $log = new WebhookLog($this->root . '/storage/logs');
        try {
            $env = is_file($this->root . '/config/env.php') ? require $this->root . '/config/env.php' : [];
            $settings = new WebhookSettings($env);
            if (!(new ElevenLabsSignature())->verify($raw, $signature, $settings->secret())) { return [401, ['error' => 'unauthorized']]; }
            if ($method !== 'POST') { return [405, ['error' => 'method_not_allowed']]; }
            if (strlen($raw) > 2097152) { return [413, ['error' => 'payload_too_large']]; }
            $event = json_decode($raw, true, 128, JSON_THROW_ON_ERROR);
            if (!is_array($event) || array_is_list($event)) { throw new \InvalidArgumentException('Invalid event.'); }
            $configs = new FileShopConfigRepository($this->root . '/config/shops', new ConfigValidator());
            if (!(new ConfigValidator())->validateWebhookSettings($env, $configs->ids())->valid()) { throw new \RuntimeException('Invalid settings.'); }
            // Unknown agents/types need no database connection and cannot cause a database failure.
            if (($event['type'] ?? null) !== 'post_call_transcription') { $log->write('skip_event_type'); return [200, ['ok' => true]]; }
            if (is_string($event['data']['agent_id'] ?? null) && $settings->shopFor($event['data']['agent_id']) === null) { $log->write('skip_unknown_agent'); return [200, ['ok' => true]]; }
            $db = Database::connect($env);
            (new ElevenLabsWebhookService($db, $configs, $settings, $log))->process($event, $raw);
            return [200, ['ok' => true]];
        } catch (\JsonException | \InvalidArgumentException $e) { $log->write('invalid_payload'); return [422, ['error' => 'invalid_payload']]; }
        catch (\Throwable $e) { $log->write('processing_failed'); return [503, ['error' => 'temporarily_unavailable']]; }
    }
}
