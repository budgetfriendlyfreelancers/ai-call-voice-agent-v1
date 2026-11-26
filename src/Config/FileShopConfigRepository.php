<?php
namespace VoiceShop\Config;
final class FileShopConfigRepository implements ShopConfigRepositoryInterface
{
    public function __construct(private string $directory, private ConfigValidator $validator) {}
    public function ids(): array { return array_map(static fn($p) => basename($p, '.json'), glob($this->directory . '/*.json') ?: []); }
    public function load(string $id): array
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/D', $id)) { throw new ConfigException(['Invalid shop identifier.']); }
        $path = $this->directory . '/' . $id . '.json';
        if (!is_file($path)) { throw new ConfigException(['Shop config not found: ' . $id]); }
        try { $config = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new ConfigException(['Invalid JSON: ' . $e->getMessage()]); }
        if (!is_array($config)) { throw new ConfigException(['Config must be an object.']); }
        $config = $this->stripComments($config);
        try { $result = $this->validator->validate($config); }
        catch (\Throwable $e) { throw new ConfigException(['Invalid config structure: ' . $e->getMessage()]); }
        if (($config['shop_id'] ?? '') !== $id) { $result->errors[] = 'shop_id must match filename.'; }
        if (!$result->valid()) { throw new ConfigException($result->errors); }
        return $config;
    }
    private function stripComments(array $data): array
    {
        foreach ($data as $key => $value) {
            if (str_starts_with((string)$key, '_comment')) { unset($data[$key]); }
            elseif (is_array($value)) { $data[$key] = $this->stripComments($value); }
        }
        return $data;
    }
}
