<?php
namespace VoiceShop\Webhook;
use VoiceShop\Config\{ShopConfigRepositoryInterface,WebhookSettings};
use VoiceShop\Repository\{CallRepository,ShopRepository};
use VoiceShop\Service\OrderIntakeService;
final class ElevenLabsWebhookService
{
    public function __construct(private \PDO $db, private ShopConfigRepositoryInterface $configs, private WebhookSettings $settings, private WebhookLog $log) {}
    public function process(array $event, string $raw): void
    {
        // Signed audio/failure events and unknown agents are acknowledged and logged, never retried.
        if (($event['type'] ?? null) !== 'post_call_transcription') { $this->log->write('skip_event_type'); return; }
        $data = $event['data'] ?? null;
        if (!is_array($data) || !is_string($data['agent_id'] ?? null)) { throw new \InvalidArgumentException('Invalid event.'); }
        $shop = $this->settings->shopFor($data['agent_id']);
        if ($shop === null) { $this->log->write('skip_unknown_agent'); return; }
        $callId = $data['conversation_id'] ?? null;
        if (!is_string($callId) || trim($callId) !== $callId || $callId === '' || mb_strlen($callId, 'UTF-8') > 255) { throw new \InvalidArgumentException('Invalid conversation ID.'); }
        $calls = new CallRepository($this->db);
        if ($calls->byCall($callId)) { $this->log->write('duplicate', $callId); return; }
        $config = $this->configs->load($shop);
        $extractor = new ElevenLabsExtraction();
        $extraction = $extractor->collection($data);
        $order = $extractor->order($config, $callId, $extraction);
        $transcript = $extractor->transcript($data);
        // Retry a unique-order race with manual intake once after rolling back our audit insert.
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            $this->db->beginTransaction();
            try {
                (new ShopRepository($this->db))->sync($config);
                $calls->insert($shop, $callId, $transcript, $raw, $extraction);
                $result = (new OrderIntakeService($this->db, $this->configs))->ingest($order, false, 'system/elevenlabs');
                if ($result->code >= 400) { throw new \InvalidArgumentException('Invalid extraction.'); }
                $this->db->commit(); $this->log->write('accepted', $callId); return;
            } catch (\PDOException $e) {
                if ($this->db->inTransaction()) { $this->db->rollBack(); }
                if (($e->errorInfo[1] ?? 0) !== 1062) { throw $e; }
                if ($calls->byCall($callId)) { $this->log->write('duplicate', $callId); return; }
                if ($attempt === 1) { throw $e; }
            } catch (\Throwable $e) {
                if ($this->db->inTransaction()) { $this->db->rollBack(); }
                throw $e;
            }
        }
    }
}
