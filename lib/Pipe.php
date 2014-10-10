<?php
class Pipe
{
    private $conn;
    private $message;
    private $buffer;
    private $listeners = [];

    public function connect($conn)
    {
        $conn->on('data', function ($data) {
            $this->receive($data);
        });
        $conn->on('error', function ($error, $conn) {
            echo "Error: " . $error;
        });
        $conn->on('close', function () {
            echo "Connection closed at " . date('Y-m-d H:i:s') . "\n";
        });
        $this->conn = $conn;
    }

    public function addListener(\Closure $listener)
    {
        $this->listeners[] = $listener;
    }

    public function receive($data)
    {
        $this->buffer .= $data;
        while ('' !== (string)$this->buffer) {
            if (!$this->message) {
                $this->message = new Message();
            }
            try {
                if (!$this->message->fetch($this->buffer)) {
                    break;
                }
                echo "New message received at " . date('Y-m-d H:i:s') . "\n";
                var_dump($this->message);
                $this->notify($this->message);
            } catch (\Exception $e) {
                error_log((string)$e);
            }
            $this->message = null;
        }
    }

    public function send(Message $message)
    {
        if (!$this->conn) {
            throw \ErrorException('Pipe is not connected');
        }
        echo "Sending response at " . date('Y-m-d H:i:s') . ":\n";
        $data = $message->build();
        $len = pack('S', strlen($data));
        $this->conn->write(strrev($len) . $data);
        var_dump($data);
        var_dump($message);
    }

    private function notify(Message $message)
    {
        foreach ($this->listeners as $listener) {
            $listener($message);
        }
    }
}
