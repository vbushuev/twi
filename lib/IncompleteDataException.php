<?php
class IncompleteDataException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Incomplete data');
    }
}
