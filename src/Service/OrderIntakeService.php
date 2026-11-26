<?php
namespace VoiceShop\Service;
use VoiceShop\Config\ShopConfigRepositoryInterface;
use VoiceShop\Repository\{OrderRepository,OrderItemRepository,StatusLogRepository,ShopRepository};
final class OrderIntakeService
{
    public function __construct(private \PDO $db, private ShopConfigRepositoryInterface $configs) {}
    public function ingest(array $payload, bool $demo = false, string $changedBy = 'system/webhook-sim'): IntakeResult
    {
        $orders = new OrderRepository($this->db);
        // A webhook owns a transaction spanning its audit row AND this existing intake path.
        $ownsTransaction = !$this->db->inTransaction();
        try {
            if (!is_string($payload['shop_id'] ?? null)) { throw new \InvalidArgumentException('shop_id is required.'); }
            $c = $this->configs->load($payload['shop_id']);
            if (!is_string($payload['call_id'] ?? null) || trim($payload['call_id']) === '') { throw new \InvalidArgumentException('call_id is required.'); }
            if ($existing = $orders->byCall(trim($payload['call_id']))) {
                if ($existing['shop_id'] !== $payload['shop_id']) { throw new \InvalidArgumentException('call_id belongs to a different shop.'); }
                return new IntakeResult(200, 'duplicate - already imported', $existing);
            }
            $p = (new PayloadValidator())->normalize($c, $payload);
            $p['_raw'] = $payload;
            $p['language'] = in_array($p['language'], $c['enabled_languages'], true) ? $p['language'] : $c['default_language'];
            $missing = (new MissingFieldsDetector())->detect($c, $p);
            $flags = $p['flags'];
            foreach ($p['items'] as $i) { if ($i['is_unknown_item']) { $flags[] = 'unknown_item'; } }
            $status = $missing ? 'NEEDS_DETAILS' : 'AWAITING_MERCHANT_APPROVAL';
            if ($ownsTransaction) { $this->db->beginTransaction(); }
            (new ShopRepository($this->db))->sync($c);
            $id = $orders->insert($p, $status, $missing, array_values(array_unique($flags)), $demo);
            $items = new OrderItemRepository($this->db);
            foreach ($p['items'] as $i) { $items->insert($id, $i); }
            (new StatusLogRepository($this->db))->add($id, 'DRAFT', $status, $changedBy);
            if ($ownsTransaction) { $this->db->commit(); }
            return new IntakeResult(201, 'Order imported.', $orders->find($id));
        } catch (\PDOException $e) {
            if (!$ownsTransaction) { throw $e; }
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            if (($e->errorInfo[1] ?? 0) === 1062 && ($existing = $orders->byCall(trim($payload['call_id'])))) {
                if ($existing['shop_id'] !== $payload['shop_id']) { return new IntakeResult(422, 'call_id belongs to a different shop.'); }
                return new IntakeResult(200, 'duplicate - already imported', $existing);
            }
            throw $e;
        } catch (\InvalidArgumentException | \VoiceShop\Config\ConfigException $e) {
            if ($ownsTransaction && $this->db->inTransaction()) { $this->db->rollBack(); }
            return new IntakeResult(422, $e->getMessage());
        }
        catch (\Throwable $e) { if ($ownsTransaction && $this->db->inTransaction()) { $this->db->rollBack(); } throw $e; }
    }
}
