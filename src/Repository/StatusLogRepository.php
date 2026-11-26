<?php
namespace VoiceShop\Repository;
final class StatusLogRepository
{
    public function __construct(private \PDO $db) {}
    public function add(int $id, string $from, string $to, string $by, string $note = ''): void { $this->db->prepare('INSERT INTO order_status_log (order_id,from_status,to_status,changed_by,changed_at,note) VALUES (?,?,?,?,NOW(),?)')->execute([$id,$from,$to,$by,$note]); }
    public function forOrder(int $id): array { $s = $this->db->prepare('SELECT * FROM order_status_log WHERE order_id=? ORDER BY id'); $s->execute([$id]); return $s->fetchAll(); }
}
