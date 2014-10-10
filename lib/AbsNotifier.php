<?php
class AbsNotifier
{
    private $config;
    private $client;

    public function __construct(array $config)
    {
        $this->config = array_merge([
            'url' => '',
            'timeout' => 30,
        ], $config);
        $this->client = new \GuzzleHttp\Client();
    }

    public function notify(\Message $message, \Pipe $pipe)
    {
        $mti = (int)$message->mti;
        if (120 === $mti || 220 === $mti) {
            echo "Notifying ABS...\n";
            $year = date('Y');
            $month = substr($message->fields[7], 0, 2);
            if (date('n') < $month) {
                $year--;
            }
            $day = substr($message->fields[7], 2, 2);
            $hour = substr($message->fields[7], 4, 2);
            $min = substr($message->fields[7], 6, 2);
            $sec = substr($message->fields[7], 8, 2);
            $time = gmmktime($hour, $min, $sec, $month, $day, $year);
            $date = date('d.m.Y H:i:s', $time);
            switch ((int)$message->fields[3][1]) {
                case 10 :
                    $sign = -1;
                    break;
                default :
                    $sign = 1;
            }
            $data = array(
                'id' => (int)$message->fields[37],
                'PAN' => $message->fields[2],
                'AAC' => !empty($message->fields[38]) ? $message->fields[38] : '------',
                'OperationSum' => $sign * $message->fields[4] / 100,
                'DateOper' => $date,
                'CurrencyISO' => (int)$message->fields[49],
                'OperationType' => 'CASH-IN',
                'TerminalNumber' => $message->fields[41],
                'Merchant' => trim(preg_replace('/\s+/', ' ', implode(' ', $message->fields[43]))),
            );
            try {
                $request = $this->client->createRequest('POST', $this->config['url'], [
                    'json' => $data,
                    'timeout' => $this->config['timeout'],
                ]);
                echo 'NOTIFY ABS REQUEST: ' . $request->getBody() . "\n";
                $response = $this->client->send($request);
                echo 'NOTIFY ABS RESPONSE: ' . $response->getBody() . "\n";
                $data = $response->json();
                if (!empty($data['Add2tboxOperationResult']['ProcessedStatus'])) {
                    $responseMessage = clone $message;
                    $responseMessage->mti = $mti + 10;
                    for ($i = 41 ; $i <= 128 ; $i++) {
                        if (isset($responseMessage->fields[$i])) {
                            unset($responseMessage->fields[$i]);
                        }
                    }
                    $pipe->send($responseMessage);
                    return;
                }
            } catch (\Exception $e) {
                echo $e->getMessage() . "\n";
            }
            $s = serialize($message);
            $filename = date('YmdHis') . '_' . md5($s);
            echo "ABS REQUEST FAILED, MESSAGE SAVED AS " . $filename . "\n";
            file_put_contents(__DIR__ . '/../lost/' . $filename, $s);
        }
    }
}
