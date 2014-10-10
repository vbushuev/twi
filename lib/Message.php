<?php
class Message
{
    const MARKER = 'A4M';
    const STATE_NEW = 'new';
    const STATE_INCOMPLETED = 'incompleted';
    const STATE_COMPLETED = 'completed';

    public $state = self::STATE_NEW;
    public $protocolVersion;
    public $rejectStatus;
    public $mti;
    public $primaryBitmap;
    public $secondaryBitmap;
    public $fields = [];
    private $pos = 2; 

    public function fetch(&$data)
    {
        try {
            $this->fetchData($data);
            return true;
        } catch (IncompleteDataException $e) {
            return false;
        }
    }

    private function fetchData(&$data)
    {
        if ($this->state === self::STATE_COMPLETED) {
            return;
        }
        if ($this->state === self::STATE_NEW) {
            $start = strpos($data, self::MARKER);
            $mlen = strlen(self::MARKER);
            if (false === $start) {
                $data = substr($data, -$mlen);
                throw new IncompleteDataException();
            }
            $this->state = self::STATE_INCOMPLETED;
            $data = substr($data, $start + $mlen);
        }
        if (null === $this->protocolVersion) {
            $this->protocolVersion = $this->fetchField($data, ['N',2,0]);
        }
        if (null === $this->rejectStatus) {
            $this->rejectStatus = $this->fetchField($data, ['N',3,0]);
        }
        if (null === $this->mti) {
            $this->mti = $this->fetchField($data, ['N',4,0]);
        }
        if (null === $this->primaryBitmap) {
            $this->primaryBitmap = $this->parseBitmap($this->fetchField($data, ['H',16,0]));
        }
        if (null === $this->secondaryBitmap && $this->primaryBitmap[0]) {
            $this->secondaryBitmap = $this->parseBitmap($this->fetchField($data, ['H',16,0]));
        }
        $bitmap = $this->primaryBitmap . $this->secondaryBitmap;
        $len = strlen($bitmap);
        $struct = $this->getStruct();
        while ($this->pos <= $len) {
            if ($bitmap[$this->pos-1]) {
                if (empty($struct[$this->pos])) {
                    if (max(array_keys($struct)) < $this->pos) {
                        // Ignore message tail
                        break;
                    }
                    throw new \Exception('Unknown data position: ' . $this->pos);
                }
                $field = $struct[$this->pos];
                if (is_array(current($field))) {
                    $origin = $data;
                    try {
                        foreach ($field as $key => $format) {
                            $this->fields[$this->pos][$key] = $this->fetchField($data, $format);
                        }
                    } catch (IncompleteDataException $e) {
                        unset($this->fields[$this->pos]);
                        $data = $origin;
                        throw $e;
                    }
                } else {
                    $this->fields[$this->pos] = $this->fetchField($data, $field);
                }
            }
            $this->pos++;
        }
        $this->state = self::STATE_COMPLETED;
    }

    private function parseBitmap($data)
    {
        $bitmap = base_convert($data, 16, 2);
        return str_repeat('0', 64 - strlen($bitmap)) . $bitmap;
    }

    private function fetchField(&$data, $format)
    {
        $llvar = $format[2];
        $llen = 0;
        $length = $format[1];
        if ($llvar) {
            $llen = strlen($length);
            if (strlen($data) <= $llen) {
                throw new IncompleteDataException();
            }
            $length = min((int)substr($data, 0, $llen), $length);
        }
        if (strlen($data) < ($length + $llen)) {
            throw new IncompleteDataException();
        }
        $result = trim(substr($data, $llen, $length));
        $data = substr($data, $length + $llen);
        return $result;
    }

    public function build()
    {
        $header = self::MARKER;
        $header .= $this->buildField($this->protocolVersion, ['N',2,0]);
        $header .= $this->buildField($this->rejectStatus, ['N',3,0]);
        $header .= $this->buildField($this->mti, ['N',4,0]);
        $struct = $this->getStruct();
        if (max(array_keys($this->fields)) > 64) {
            $this->primaryBitmap = '1';
            $this->secondaryBitmap = '';
            $blen = 128;
        } else {
            $this->primaryBitmap = '0';
            $this->secondaryBitmap = false;
            $blen = 64;
        }
        $data = '';
        for ($pos = 2 ; $pos <= $blen ; $pos++) {
            if (isset($this->fields[$pos])) {
                $format = $struct[$pos];
                if (is_array(current($format))) {
                    foreach ($format as $ppos => $fformat) {
                        $data .= $this->buildField($this->fields[$pos][$ppos], $fformat);
                    }
                } else {
                    $data .= $this->buildField($this->fields[$pos], $format);
                }
                $bit = '1';
            } else {
                $bit = '0';
            }
            if ($pos <= 64) {
                $this->primaryBitmap .= $bit;
            } else {
                $this->secondaryBitmap .= $bit;
            }
        }
        $bitmap = $this->buildField(base_convert($this->primaryBitmap,2,16), ['H',16,0]);
        if (false !== $this->secondaryBitmap) {
            $bitmap .= $this->buildField(base_convert($this->secondaryBitmap,2,16), ['H',16,0]);
        }
        return $header . $bitmap . $data;
    }

    private function buildField($value, $format)
    {
        $result = '';
        $length = $format[1];
        $vlen = strlen($value);
        if ($vlen > $length) {
            throw new \InvalidArgumentException('Value length exceedes format');
        }
        if ($format[2]) {
            $llen = strlen($length);
            $result .= sprintf("%0{$llen}d", $vlen);
            $length = $vlen;
        }
        switch ($format[0]) {
            case 'N' :
            case 'H' :
                $result .= str_repeat('0', $length - $vlen) . strtoupper($value);
                break;
            case 'b' :
                $result .= $value . str_repeat('0', $length - $vlen);
                break;
            default :
                $result .= $value . str_repeat(' ', $length - $vlen);
                break;
        }
        return $result;
    }

    private function getStruct()
    {
        return [
            2 => ['A',19,1], // N
            3 => [
                1 => ['N',3,0],
                2 => ['N',2,0],
                3 => ['N',2,0],
            ],
            4 => ['N',12,0],
            6 => ['N',12,0],
            7 => ['N',10,0],
            10 => ['N',8,0],
            11 => ['N',6,0],
            12 => ['N',6,0],
            13 => ['N',4,0],
            18 => ['N',4,0],
            19 => ['N',3,0],
            22 => ['N',3,0],
            23 => ['N',3,0],
            25 => ['N',3,0],
            26 => ['N',4,0],
            28 => ['A',9,0], // A1+N8
            32 => ['N',11,1],
            33 => ['N',11,1],
            35 => ['A',37,1],
            37 => ['A',12,0],
            38 => ['A',6,0],
            39 => ['N',5,0],
            41 => ['A',16,1],
            43 => [
                1 => ['A',30,0],
                2 => ['A',30,0],
                3 => ['N',3,0],
                4 => ['N',3,0],
                5 => ['A',30,0],
                6 => ['A',30,0],
                7 => ['A',30,0],
                8 => ['N',3,0],
                9 => ['N',8,0],
                10 => ['A',10,0],
                11 => ['A',4,0],
                12 => ['A',25,0],
                13 => ['N',3,0],
                14 => ['N',9,0],
                15 => ['N',4,0],
            ],
            44 => [
                1 => ['A',1,0],
                2 => ['A',1,0],
            ],
            45 => ['A',76,1],
            48 => [
                1 => ['A',12,0],
                2 => ['A',19,0],
            ],
            49 => ['N',3,0],
            51 => ['N',3,0],
            52 => ['H',16,0],
            53 => ['b',48,0],
            54 => ['N',12,0],
            55 => ['b',255,1],
            61 => [
                1 => ['A',4,0],
                2 => ['A',10,0],
            ],
            62 => ['A',100,1],
            63 => ['H',16,0],
            64 => ['H',16,0],
            70 => ['N',3,0],
            95 => [
                1 => ['N',12,0],
                2 => ['N',12,0],
            ],
            100 => ['N',11,1],
            102 => ['A',30,1],
            103 => ['A',30,1],
            104 => ['N',4,0],
            105 => [
                1 => ['N',12,0],
                2 => ['N',12,0],
                3 => ['N',1,0],
            ],
            106 => [
                1 => ['N',3,0],
                2 => ['N',3,0],
                3 => ['N',12,0],
                4 => ['N',12,0],
                5 => ['N',8,0],
                6 => ['N',8,0],
            ],
            107 => ['A',12,0],
            108 => ['A',999,1],
            109 => [
                1 => ['N',3,0],
                2 => ['N',1,0],
                3 => ['N',30,0],
                4 => ['A',25,0],
                5 => ['N',3,0],
            ],
            110 => ['N',9,1],
            111 => ['A',99,1],
            114 => ['A',250,1],
            115 => ['A',99999,1],
            116 => ['A',99999,1],
            121 => [
                1 => ['N',2,0],
                2 => ['N',1,0],
                3 => ['N',3,0],
                4 => ['A',16,0],
                5 => ['A',16,0],
                6 => ['A',9,0],
            ],
            122 => [
                1 => ['N',3,0],
                2 => ['N',1,0],
                3 => ['N',4,0],
                4 => ['A',40,0],
            ],
            123 => ['A',999,1],
            124 => ['A',99999,1],
            125 => ['A',99999,1],
            126 => [
                1 => ['A',16,0],
                2 => ['A',9,0],
                3 => ['A',9,0],
            ],
            127 => ['A',99999,1],
            128 => ['H',16,0],
        ];
    }
	public function __toString(){
		$res="";
		foreach($this->fields as $field=>$val){
			$res.="[{$field}]{$val}";
		}
		return $res;
	}
}
