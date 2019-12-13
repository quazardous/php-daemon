<?php

if (!is_file(__DIR__ . '/config.php')) {
    throw new \RuntimeException('Please copy and adapt the file tests/config-dist.php to tests/config.php !');
}

include_once __DIR__ . '/config.php';

$loader = include __DIR__ . '/../vendor/autoload.php';

$loader->setPsr4('Quazardous\\Daemon\\Tests\\', [__DIR__ . '/src']);
