<?php
require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/global.php';

$pipe = new \Pipe();
$pipe->addListener(function ($message) use ($config, $pipe) {
    static $notifier;
    if (!$notifier) {
        $notifier = new \AbsNotifier($config['abs']);
    }
    $notifier->notify($message, $pipe);
});
$pipe->addListener(function ($message) use ($config, $pipe) {
    if ((int)$message->mti == 800) {
        $response = new \Message();
        $response->protocolVersion = $message->protocolVersion;
        $response->rejectStatus = 0;
        $response->mti = 810;
        $response->fields = $message->fields;
        switch ((int)$message->fields[70]) {
            case 4 : // Inquiry
                $responseCode = 72; // Offline
                break;
            default :
                $responseCode = 1; // Online
        }

        $response->fields[39] = $responseCode;
        $pipe->send($response);

        if (1 === $responseCode) {
            //$message->fields[70] = '001';
            $pipe->send($message);
        }
    }
});
