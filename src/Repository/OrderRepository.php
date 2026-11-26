<?php
namespace VoiceShop\Repository;
final class OrderRepository
{
    public function __construct(private \PDO $db) {}
    public function find(int $id, bool $lock = false): ?array
    {
        $sql = $lock ? 'SELECT * FROM orders WHERE id=? FOR UPDATE' : 'SELECT * FROM orders WHERE id=?';
        $s = $this->db->prepare($sql); $s->execute([$id]); return $s->fetch() ?: null;
    }
    public function byCall(string $call): ?array { $s = $this->db->prepare('SELECT * FROM orders WHERE call_id=?'); $s->execute([$call]); return $s->fetch() ?: null; }
    public function listing(string $shop = ''): array
    {
        $s = $this->db->prepare('SELECT o.*,s.shop_name,(SELECT COUNT(*) FROM order_items i WHERE i.order_id=o.id) AS item_count FROM orders o JOIN shops s ON s.shop_id=o.shop_id WHERE (? = \'\' OR o.shop_id=?) ORDER BY o.created_at DESC,o.id DESC'); $s->execute([$shop,$shop]); return $s->fetchAll();
    }
    public function insert(array $p, string $status, array $missing, array $flags, bool $demo): int
    {
        $this->db->prepare('INSERT INTO orders (shop_id,call_id,language,status,customer_name,customer_phone,address_json,delivery_choice,payment_choice,requested_time,missing_fields_json,flags_json,order_json,is_demo,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')->execute([$p['shop_id'],$p['call_id'],$p['language'],$status,$p['customer']['name'],$p['customer']['phone'],self::json($p['customer']['address']),$p['delivery_choice'],$p['payment_choice'],$p['requested_time'],self::json($missing),self::json($flags),self::json($p['_raw'] ?? $p),$demo ? 1 : 0]);
        return (int)$this->db->lastInsertId();
    }
    public function edit(int $id, array $p, array $missing): void
    {
        $this->db->prepare('UPDATE orders SET customer_name=?,customer_phone=?,address_json=?,delivery_choice=?,payment_choice=?,requested_time=?,missing_fields_json=?,updated_at=NOW() WHERE id=?')->execute([$p['customer']['name'],$p['customer']['phone'],self::json($p['customer']['address']),$p['delivery_choice'],$p['payment_choice'],$p['requested_time'],self::json($missing),$id]);
    }
    public function status(int $id, string $status): void { $this->db->prepare('UPDATE orders SET status=?,updated_at=NOW() WHERE id=?')->execute([$status,$id]); }
    public static function json(mixed $v): string { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); }
}
