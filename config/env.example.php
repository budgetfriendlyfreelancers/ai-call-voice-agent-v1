<?php
// Copy to env.php. This example contains no live credentials.
return [
    'db_host' => '127.0.0.1', 'db_port' => 3306,
    'db_name' => 'voiceshop', 'db_user' => '', 'db_password' => '',
    'owner_pin' => '', // Choose a PIN; blank disables owner mode.
    'elevenlabs_api_key' => '',
    'ELEVENLABS_WEBHOOK_SECRET' => '', // TODO(user): paste the ElevenLabs signing secret; never a voice API key.
    'SHOP_AGENT_MAP' => [
        // TODO(user): replace each placeholder key with that shop's real agent ID.
        'REPLACE_WITH_PANIPURI_AGENT_ID' => 'panipuri_stall_01',
        'REPLACE_WITH_GROCERY_AGENT_ID' => 'grocery_store_02',
    ],
];
