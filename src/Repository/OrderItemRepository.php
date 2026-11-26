<?php
namespace VoiceShop\Repository;
final class OrderItemRepository
{
    public function __construct(private \PDO $db) {}
    public function forOrder(int $id): array { $s = $this->db->prepare('SELECT * FROM order_items WHERE order_id=? ORDER BY id'); $s->execute([$id]); return $s->fetchAll(); }
    public function insert(int $id, array $i): void { $this->db->prepare('INSERT INTO order_items (order_id,sku,qty,unit,options_json,raw_text,is_unknown_item) VALUES (?,?,?,?,?,?,?)')->execute([$id,$i['sku'],$i['qty'],$i['unit'],OrderRepository::json($i['options']),$i['raw_text'],$i['is_unknown_item']]); }
    public function edit(int $id, int $order, float $qty, string $unit): void { $this->db->prepare('UPDATE order_items SET qty=?,unit=? WHERE id=? AND order_id=?')->execute([$qty,$unit,$id,$order]); }
}
