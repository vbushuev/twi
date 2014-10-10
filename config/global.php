<?php
$config['server']['port'] = 8888;
$config['server']['interface'] = '0.0.0.0';

$config['client']['host'] = '127.0.0.1';
$config['client']['port'] = 8889;

$config['abs']['url'] = 'http://pso.2tbank.ru:2222/PSO.svc/json/AddP2POperation/';
$config['abs']['timeout'] = 30;

if (!is_readable(__DIR__ . '/local.php')) {
    throw new \Exception('Local config (local.php) must exists');
}
require __DIR__ . '/local.php';

return $config;
