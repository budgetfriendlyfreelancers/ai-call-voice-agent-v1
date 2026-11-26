<?php
namespace VoiceShop\Service;
use VoiceShop\Config\ShopConfigRepositoryInterface;
use VoiceShop\Repository\{OrderRepository,StatusLogRepository};
use VoiceShop\Domain\StatusFlow;
final class OrderStatusService
{
    public function __construct(private \PDO $db, private ShopConfigRepositoryInterface $configs) {}
    public function transition(int $id, string $to, bool $owner): void
    {
        $this->db->beginTransaction();
        try {
            $repo = new OrderRepository($this->db);
            $o = $repo->find($id, true) ?? throw new \InvalidArgumentException('Order not found.');
            $c = $this->configs->load($o['shop_id']);
            if (!(new StatusFlow())->allows($c, $o['status'], $to)) { throw new \InvalidArgumentException('Disallowed status transition.'); }
            if ($to === 'CONFIRMED' && !$owner) { throw new \InvalidArgumentException('Owner mode is required to approve.'); }
            $p = ['customer' => ['name' => $o['customer_name'],'phone' => $o['customer_phone'],'address' => json_decode($o['address_json'], true)], 'missing_fields' => json_decode($o['missing_fields_json'], true)];
            if ($to === 'AWAITING_MERCHANT_APPROVAL' && (new MissingFieldsDetector())->detect($c, $p)) { throw new \InvalidArgumentException('Required details are still missing.'); }
            $repo->status($id, $to);
            (new StatusLogRepository($this->db))->add($id, $o['status'], $to, $owner ? 'owner' : 'demo/operator');
            $this->db->commit();
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
    }
}
