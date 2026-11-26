<?php
namespace VoiceShop\Http;
use VoiceShop\Config\{FileShopConfigRepository,ConfigValidator,ConfigException};
use VoiceShop\Db\{Database,Migrator};
use VoiceShop\Repository\{ShopRepository,OrderRepository,OrderItemRepository,StatusLogRepository};
use VoiceShop\Service\{OrderIntakeService,OrderStatusService,OrderEditService,OrderPresenter,DemoSeeder};
use VoiceShop\Language\LanguageResolver;
use VoiceShop\Voice\{VoiceResolver,VoiceProviderFactory};
final class App
{
    private FileShopConfigRepository $configs;
    private array $env = [];
    public function __construct(private string $root)
    {
        $this->configs = new FileShopConfigRepository($root . '/config/shops', new ConfigValidator());
        if (is_file($root . '/config/env.php')) { $this->env = require $root . '/config/env.php'; }
    }
    /** Manual imports share intake with the separately authenticated ElevenLabs webhook. */
    public function run(string $page): void
    {
        $language = new LanguageResolver($this->root . '/storage/logs');
        $data = ['title' => 'VoiceShop', 'shops' => [], 'health' => [], 'message' => '', 'error' => '', 'shopFilter' => is_string($_GET['shop'] ?? null) ? $_GET['shop'] : '', 'owner' => (bool)($_SESSION['owner'] ?? false)];
        foreach ($this->configs->ids() as $id) {
            try {
                $c = $this->configs->load($id); $data['shops'][$id] = $c;
                $v = (new ConfigValidator())->validate($c); $voices = [];
                foreach ($c['enabled_languages'] as $code) { $voices[$code] = (new VoiceResolver())->resolve($c, $code); }
                try { VoiceProviderFactory::make($c['voice_provider'], $this->env); } catch (\InvalidArgumentException $e) { $v->errors[] = $e->getMessage(); }
                $fallbacks = [];
                foreach ($c['enabled_languages'] as $code) { $fallbacks[$code] = $language->explain($c, $code, 'closing'); }
                $data['health'][$id] = ['config' => $c, 'errors' => $v->errors, 'warnings' => $v->warnings, 'voices' => $voices, 'fallbacks' => $fallbacks];
            } catch (ConfigException $e) { $data['health'][$id] = ['errors' => $e->errors, 'warnings' => [], 'voices' => [], 'fallbacks' => []]; }
        }
        $template = $page;
        $settings = new \VoiceShop\Config\WebhookSettings($this->env);
        $data['webhookHealth'] = (new ConfigValidator())->validateWebhookSettings($this->env, $this->configs->ids());
        $data['agentMap'] = $settings->map();
        $data['webhookSecretConfigured'] = trim($settings->secret()) !== '';
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') { Csrf::verify($_POST['csrf'] ?? null); }
            if ($page === 'owner') {
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') { throw new \InvalidArgumentException('POST required.'); }
                if (isset($_POST['disable'])) { $_SESSION['owner'] = false; }
                else {
                    $pin = $this->env['owner_pin'] ?? '';
                    if ($pin === '' || !is_string($_POST['pin'] ?? null) || !hash_equals((string)$pin, $_POST['pin'])) { throw new \InvalidArgumentException('Owner PIN is incorrect or not configured.'); }
                    session_regenerate_id(true); $_SESSION['owner'] = true;
                }
                header('Location: index.php'); return;
            }
            if (!in_array($page, ['orders','detail','import','health','setup'], true)) { throw new \InvalidArgumentException('Unknown page.'); }
            if ($page !== 'health') {
                if (!$this->env) { throw new \RuntimeException('Copy config/env.example.php to config/env.php and configure MySQL, then open Setup. Config Health is available without a database.'); }
                $db = Database::connect($this->env);
                if ($page === 'setup') {
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        (new Migrator())->runAll($db, $this->root);
                        foreach ($data['shops'] as $c) { (new ShopRepository($db))->sync($c); }
                        $data['message'] = implode(' | ', (new DemoSeeder($db, $this->configs))->run());
                    }
                } else {
                    foreach ($data['shops'] as $c) { (new ShopRepository($db))->sync($c); }
                }
                if ($page === 'orders') { $data['sections'] = (new OrderPresenter($language))->sections((new OrderRepository($db))->listing($data['shopFilter'])); }
                if ($page === 'import') {
                    $data['payload'] = is_string($_POST['payload'] ?? null) ? $_POST['payload'] : '';
                    $data['samples'] = [DemoSeeder::sample('panipuri_stall_01', false, 'SAMPLE-' . date('Ymd-His') . '-complete'), DemoSeeder::sample('grocery_store_02', true, 'SAMPLE-' . date('Ymd-His') . '-missing')];
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $p = json_decode($data['payload'], true, 512, JSON_THROW_ON_ERROR);
                        if (!is_array($p) || array_is_list($p)) { throw new \InvalidArgumentException('Order JSON must be an object.'); }
                        if (!isset($p['shop_id'])) { $p['shop_id'] = $_POST['shop_id'] ?? ''; }
                        $r = (new OrderIntakeService($db, $this->configs))->ingest($p); http_response_code($r->code);
                        $data[$r->code >= 400 ? 'error' : 'message'] = $r->message; $data['imported'] = $r->order;
                    }
                }
                if ($page === 'detail') {
                    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
                    if (!$id) { throw new \InvalidArgumentException('Invalid order id.'); }
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        if (($_POST['action'] ?? '') === 'status') { (new OrderStatusService($db, $this->configs))->transition($id, is_string($_POST['status'] ?? null) ? $_POST['status'] : '', $data['owner']); }
                        elseif (($_POST['action'] ?? '') === 'save') { (new OrderEditService($db, $this->configs))->save($id, $_POST); }
                        else { throw new \InvalidArgumentException('Unknown action.'); }
                        header('Location: index.php?page=detail&id=' . $id . '&saved=1'); return;
                    }
                    $o = (new OrderRepository($db))->find($id) ?? throw new \InvalidArgumentException('Order not found.');
                    $data += (new OrderPresenter($language))->detail($o, (new OrderItemRepository($db))->forOrder($id), $this->configs->load($o['shop_id']));
                    $data['history'] = (new StatusLogRepository($db))->forOrder($id);
                    if (isset($_GET['saved'])) { $data['message'] = 'Saved. Status changes are always explicit.'; }
                }
            }
        } catch (\PDOException $e) {
            http_response_code(503); $data['error'] = 'Database unavailable or tables missing. Check config/env.php, start MySQL and run Setup.'; $template = 'error';
        } catch (\Throwable $e) {
            http_response_code(422); $data['error'] = $e->getMessage(); $template = 'error';
        }
        View::render($template, $data);
    }
}
