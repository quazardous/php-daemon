<?php

namespace Quazardous\Daemon\Tests;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

class Logger implements LoggerInterface
{
    use LoggerTrait;
    public function log($level, $message, array $context = array())
    {
        echo '[' . strtoupper($level) . '] ' . $message;
        if ($context) {
            echo " "; print_r($context);
        }
        echo "\n";
    }
}