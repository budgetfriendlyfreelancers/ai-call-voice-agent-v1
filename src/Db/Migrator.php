<?php
namespace VoiceShop\Db;
final class Migrator
{
    public function runAll(\PDO $db, string $root): void
    {
        $this->run($db, $root . '/database/schema.sql');
        foreach (glob($root . '/database/migrations/*.sql') ?: [] as $path) { $this->run($db, $path); }
    }
    public function run(\PDO $db, string $path): void
    {
        foreach (explode(';', file_get_contents($path)) as $sql) {
            $sql = trim($sql);
            // Connection selects the configured DB, so tests can use an isolated DB.
            if ($sql !== '' && !preg_match('/^(CREATE DATABASE|USE )/i', $sql)) { $db->prepare($sql)->execute(); }
        }
    }
}
