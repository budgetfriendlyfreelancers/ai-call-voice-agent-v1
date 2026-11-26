<?php
namespace VoiceShop\Db;
final class Database
{
    public static function connect(array $env): \PDO
    {
        $name = (string)($env['db_name'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $name)) { throw new \RuntimeException('Invalid DB name in config/env.php.'); }
        $pdo = new \PDO('mysql:host=' . $env['db_host'] . ';port=' . ($env['db_port'] ?? 3306) . ';dbname=' . $name . ';charset=utf8mb4', $env['db_user'], $env['db_password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_EMULATE_PREPARES => false, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $pdo->prepare("SET time_zone = '+05:30'")->execute();
        return $pdo;
    }
}
