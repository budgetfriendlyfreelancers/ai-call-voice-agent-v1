<?php
namespace VoiceShop\Config;
interface ShopConfigRepositoryInterface
{
    public function ids(): array;
    public function load(string $id): array;
}
