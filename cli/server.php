<?php
require_once __DIR__ . '/../config/bootstrap.php';
echo "Starting server pid=" .  getmypid() . " at " . date('Y-m-d H:i:s') . "...\n";
set_time_limit(0);
$port = $config['server']['port'];
$interface = $config['server']['interface'];
register_shutdown_function(function () {
    echo "Server stopped at " . date('Y-m-d H:i:s') . "\n";
});
while (true) {
    $loop = React\EventLoop\Factory::create();
    $socket = new React\Socket\Server($loop);
    $socket->on('connection', function ($conn) use ($pipe) {
        echo "Client connected at " . date('Y-m-d H:i:s') . "\n";
        $pipe->connect($conn);
    });
    $socket->listen($port, $interface);
    echo "Listening for incoming connection on $interface:$port...\n";
    $loop->run();
}
