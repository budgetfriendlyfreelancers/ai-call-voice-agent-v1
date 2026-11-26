<?php
// Local CLI setup only. Generated credentials live only in the gitignored env.php.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$path = dirname(__DIR__) . '/config/env.php';
if (!is_file($path)) { fwrite(STDERR, "Configure config/env.php first.\n"); exit(1); }
$env = require $path;
if (empty($env['ELEVENLABS_WEBHOOK_SECRET'])) { $env['ELEVENLABS_WEBHOOK_SECRET'] = bin2hex(random_bytes(32)); }
if (empty($env['SHOP_AGENT_MAP'])) { $env['SHOP_AGENT_MAP'] = ['local_sim_grocery' => 'grocery_store_02', 'local_sim_panipuri' => 'panipuri_stall_01']; }
// This script never replaces a real secret or a nonempty agent map.
file_put_contents($path, "<?php\n// Private local settings. Never commit this file.\nreturn " . var_export($env, true) . ";\n", LOCK_EX);
echo "Local webhook settings present; secret value not printed. Replace local_sim_* mappings before real calls.\n";
