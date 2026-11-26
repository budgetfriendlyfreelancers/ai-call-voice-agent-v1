<?php
namespace VoiceShop\Repository;
final class CallRepository
{
    public function __construct(private \PDO $db) {}
    public function byCall(string $callId): ?array
    {
        $s = $this->db->prepare('SELECT * FROM calls WHERE call_id=?'); $s->execute([$callId]);
        return $s->fetch() ?: null;
    }
    public function insert(string $shopId, string $callId, ?string $transcript, string $raw, array $extraction): void
    {
        $this->db->prepare('INSERT INTO calls (shop_id,call_id,transcript,payload,extraction) VALUES (?,?,?,?,?)')->execute([$shopId, $callId, $transcript, $raw, OrderRepository::json((object)$extraction)]);
    }
}
