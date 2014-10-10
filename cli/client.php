<?php
require_once __DIR__ . '/../config/bootstrap.php';
echo "Starting client pid=" .  getmypid() . " at " . date('Y-m-d H:i:s') . "...\n";
set_time_limit(0);
$host = $config['client']['host'];
$port = $config['client']['port'];
while (true) {
    $loop = React\EventLoop\Factory::create();
    $client = stream_socket_client("tcp://$host:$port", $errno, $errstr, -1);
    if (!$client) {
        echo "$errstr ($errno)\n";
        exit(1);
    }
    echo "Connected to $host:$port at " . date('Y-m-d H:i:s') . "\n";
    stream_set_timeout($client, -1);
    $conn = new React\Socket\Connection($client, $loop);
    $pipe->connect($conn);
    $loop->run();
}
