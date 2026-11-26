<?php
declare(strict_types=1);
spl_autoload_register(static function (string $class): void {
    $prefix = 'VoiceShop\\';
    if (strncmp($class, $prefix, strlen($prefix)) === 0) {
        $path = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) { require $path; }
    }
});
date_default_timezone_set('Asia/Kolkata');
// PHP 8.0 compatibility (native array_is_list arrived in 8.1).
if (!function_exists('array_is_list')) {
    function array_is_list(array $array): bool { return $array === [] || array_keys($array) === range(0, count($array) - 1); }
}
