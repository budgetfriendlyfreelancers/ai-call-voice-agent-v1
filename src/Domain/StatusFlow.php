<?php
namespace VoiceShop\Domain;
final class StatusFlow
{
    public const CODES = ['DRAFT', 'NEEDS_DETAILS', 'AWAITING_MERCHANT_APPROVAL', 'CONFIRMED', 'READY_FOR_DELIVERY', 'COMPLETED'];
    public function next(array $config, string $from): array { return $config['status_flow'][$from] ?? []; }
    public function allows(array $config, string $from, string $to): bool { return in_array($to, $this->next($config, $from), true); }
    public static function label(string $status): string { return ucwords(strtolower(str_replace('_', ' ', $status))); }
}
