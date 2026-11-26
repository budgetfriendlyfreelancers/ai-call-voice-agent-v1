<?php
namespace VoiceShop\Repository;
final class ShopRepository
{
    public function __construct(private \PDO $db) {}
    public function sync(array $c): void
    {
        $this->db->prepare('INSERT INTO shops (shop_id,shop_name,config_json,is_demo,updated_at) VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE shop_name=VALUES(shop_name),config_json=VALUES(config_json),updated_at=NOW()')->execute([$c['shop_id'],$c['shop_name'],json_encode($c, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),in_array($c['shop_id'], ['panipuri_stall_01','grocery_store_02'], true) ? 1 : 0]);
    }
}
